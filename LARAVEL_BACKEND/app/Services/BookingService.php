<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingSetting;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Services\Platform\EntitlementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        protected EntitlementService $entitlements,
    ) {}

    public function ensureSettings(Company $company): BookingSetting
    {
        $existing = BookingSetting::where('company_id', $company->id)->first();
        if ($existing) {
            return $existing;
        }

        return BookingSetting::create([
            'company_id' => $company->id,
            'timezone' => config('app.timezone', 'Africa/Nairobi'),
            'default_duration_minutes' => 30,
            'buffer_minutes' => 0,
            'min_notice_minutes' => 60,
            'max_days_ahead' => 30,
            'public_slug' => $this->uniqueSlug($company->name ?: 'book'),
            'calendar_feed_token' => Str::random(40),
            'is_enabled' => true,
        ]);
    }

    /**
     * @param  list<array{weekday:int,startTime:string,endTime:string}>  $windows
     */
    public function syncAvailability(Company $company, array $windows): void
    {
        DB::transaction(function () use ($company, $windows) {
            BookingAvailability::where('company_id', $company->id)->delete();
            foreach ($windows as $window) {
                $weekday = (int) ($window['weekday'] ?? -1);
                $start = (string) ($window['startTime'] ?? $window['start_time'] ?? '');
                $end = (string) ($window['endTime'] ?? $window['end_time'] ?? '');
                if ($weekday < 0 || $weekday > 6 || $start === '' || $end === '') {
                    continue;
                }
                BookingAvailability::create([
                    'company_id' => $company->id,
                    'weekday' => $weekday,
                    'start_time' => $start,
                    'end_time' => $end,
                ]);
            }
        });
    }

    /**
     * @return list<array{start:string,end:string}>
     */
    public function availableSlots(Company $company, ?Product $product, Carbon $from, Carbon $to): array
    {
        $settings = $this->ensureSettings($company);
        if (! $settings->is_enabled) {
            return [];
        }

        $tz = $settings->timezone ?: 'UTC';
        $duration = (int) ($product?->booking_duration_minutes ?: $settings->default_duration_minutes);
        $duration = max(5, $duration);
        $buffer = max(0, (int) $settings->buffer_minutes);
        $minNotice = max(0, (int) $settings->min_notice_minutes);

        $windows = BookingAvailability::where('company_id', $company->id)->get()->groupBy('weekday');
        if ($windows->isEmpty()) {
            return [];
        }

        $existing = Booking::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->get(['starts_at', 'ends_at']);

        $slots = [];
        $cursor = $from->copy()->timezone($tz)->startOfDay();
        $endDay = $to->copy()->timezone($tz)->startOfDay();
        $now = now()->timezone($tz)->addMinutes($minNotice);

        while ($cursor->lte($endDay)) {
            $weekday = (int) $cursor->dayOfWeek;
            foreach ($windows->get($weekday, []) as $window) {
                $dayStart = Carbon::parse($cursor->toDateString().' '.$window->start_time, $tz);
                $dayEnd = Carbon::parse($cursor->toDateString().' '.$window->end_time, $tz);
                $slotStart = $dayStart->copy();
                while ($slotStart->copy()->addMinutes($duration)->lte($dayEnd)) {
                    $slotEnd = $slotStart->copy()->addMinutes($duration);
                    if ($slotStart->gte($now)) {
                        $conflict = $existing->contains(function (Booking $b) use ($slotStart, $slotEnd, $buffer) {
                            $bStart = $b->starts_at->copy()->subMinutes($buffer);
                            $bEnd = $b->ends_at->copy()->addMinutes($buffer);

                            return $slotStart->lt($bEnd) && $slotEnd->gt($bStart);
                        });
                        if (! $conflict) {
                            $slots[] = [
                                'start' => $slotStart->clone()->utc()->toIso8601String(),
                                'end' => $slotEnd->clone()->utc()->toIso8601String(),
                            ];
                        }
                    }
                    $slotStart->addMinutes($duration + $buffer);
                }
            }
            $cursor->addDay();
        }

        return $slots;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createBooking(Company $company, array $payload, ?Product $product = null, ?Order $order = null, ?OrderProduct $line = null): Booking
    {
        if (! $this->entitlements->allowsBookings($company)) {
            throw new \RuntimeException('Bookings are not enabled on your plan.');
        }

        $limit = $this->entitlements->maxBookingsPerMonth($company);
        if ($limit !== null) {
            $used = Booking::query()
                ->where('company_id', $company->id)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->where('starts_at', '>=', now()->startOfMonth())
                ->where('starts_at', '<=', now()->endOfMonth())
                ->count();
            if ($used >= $limit) {
                throw new \RuntimeException('Monthly booking limit reached for this plan.');
            }
        }

        $settings = $this->ensureSettings($company);
        $startsAt = Carbon::parse($payload['startsAt'] ?? $payload['starts_at'])->utc();
        $duration = (int) ($product?->booking_duration_minutes ?: $settings->default_duration_minutes);
        $endsAt = isset($payload['endsAt']) || isset($payload['ends_at'])
            ? Carbon::parse($payload['endsAt'] ?? $payload['ends_at'])->utc()
            : $startsAt->copy()->addMinutes(max(5, $duration));

        $slots = $this->availableSlots($company, $product, $startsAt->copy()->subMinute(), $endsAt->copy()->addMinute());
        $exact = collect($slots)->first(function (array $slot) use ($startsAt) {
            return Carbon::parse($slot['start'])->equalTo($startsAt);
        });
        if (! $exact) {
            throw new \RuntimeException('That time slot is no longer available.');
        }

        $booking = Booking::create([
            'company_id' => $company->id,
            'product_id' => $product?->id,
            'order_id' => $order?->id,
            'order_product_id' => $line?->id,
            'customer_name' => (string) ($payload['customerName'] ?? $payload['customer_name'] ?? 'Guest'),
            'customer_email' => $payload['customerEmail'] ?? $payload['customer_email'] ?? null,
            'customer_phone' => $payload['customerPhone'] ?? $payload['customer_phone'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => Booking::STATUS_CONFIRMED,
            'title' => $payload['title'] ?? ($product?->name ?: 'Meeting'),
            'notes' => $payload['notes'] ?? null,
            'ics_uid' => (string) Str::uuid(),
            'manage_token' => Str::random(40),
        ]);

        $this->notifyWebhook($settings, $booking);

        return $booking;
    }

    public function cancelBooking(Booking $booking): Booking
    {
        if (! $booking->isCancellable()) {
            throw new \RuntimeException('This booking cannot be cancelled.');
        }
        $booking->update(['status' => Booking::STATUS_CANCELLED]);
        $settings = BookingSetting::where('company_id', $booking->company_id)->first();
        if ($settings) {
            $this->notifyWebhook($settings, $booking->fresh());
        }

        return $booking->fresh();
    }

    public function publicBookingUrl(Company $company, ?Product $product = null, ?Order $order = null): string
    {
        $settings = $this->ensureSettings($company);
        $url = url('/book/'.$settings->public_slug);
        $query = [];
        if ($product) {
            $query['product'] = $product->id;
        }
        if ($order) {
            $query['order'] = $order->id;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    public function calendarFeedUrl(Company $company): string
    {
        $settings = $this->ensureSettings($company);

        return url('/book/'.$settings->public_slug.'/calendar.ics?token='.$settings->calendar_feed_token);
    }

    public function bookingToIcs(Booking $booking): string
    {
        $booking->loadMissing('company');
        $uid = $booking->ics_uid;
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $start = $booking->starts_at->utc()->format('Ymd\THis\Z');
        $end = $booking->ends_at->utc()->format('Ymd\THis\Z');
        $summary = $this->icsEscape($booking->title ?: 'Booking');
        $desc = $this->icsEscape($booking->notes ?: ('Booking with '.($booking->company?->name ?? 'RelayIQ')));
        $status = $booking->status === Booking::STATUS_CANCELLED ? 'CANCELLED' : 'CONFIRMED';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RelayIQ//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$start,
            'DTEND:'.$end,
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$desc,
            'STATUS:'.$status,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    /**
     * @param  iterable<Booking>  $bookings
     */
    public function calendarFeedIcs(Company $company, iterable $bookings): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RelayIQ//Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->icsEscape($company->name.' Bookings'),
        ];
        foreach ($bookings as $booking) {
            if ($booking->status === Booking::STATUS_CANCELLED) {
                continue;
            }
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$booking->ics_uid;
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->icsEscape($booking->title ?: 'Booking');
            $lines[] = 'DESCRIPTION:'.$this->icsEscape(($booking->customer_name ?: '').' '.($booking->notes ?: ''));
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        $lines[] = '';

        return implode("\r\n", $lines);
    }

    public function googleCalendarUrl(Booking $booking): string
    {
        $start = $booking->starts_at->utc()->format('Ymd\THis\Z');
        $end = $booking->ends_at->utc()->format('Ymd\THis\Z');
        $params = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $booking->title ?: 'Booking',
            'dates' => $start.'/'.$end,
            'details' => $booking->notes ?: '',
        ]);

        return 'https://calendar.google.com/calendar/render?'.$params;
    }

    private function notifyWebhook(BookingSetting $settings, Booking $booking): void
    {
        $url = trim((string) $settings->calendar_webhook_url);
        if ($url === '') {
            return;
        }
        try {
            Http::timeout(5)->post($url, [
                'event' => 'booking.'.$booking->status,
                'booking' => [
                    'id' => $booking->id,
                    'title' => $booking->title,
                    'startsAt' => $booking->starts_at->toIso8601String(),
                    'endsAt' => $booking->ends_at->toIso8601String(),
                    'status' => $booking->status,
                    'customerName' => $booking->customer_name,
                    'customerEmail' => $booking->customer_email,
                    'customerPhone' => $booking->customer_phone,
                    'ics' => $this->bookingToIcs($booking),
                    'googleCalendarUrl' => $this->googleCalendarUrl($booking),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Booking webhook failed', ['booking_id' => $booking->id, 'error' => $e->getMessage()]);
        }
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'book';
        $candidate = $slug;
        $i = 1;
        while (BookingSetting::where('public_slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    private function icsEscape(string $value): string
    {
        return str_replace(["\n", ',', ';'], ['\\n', '\\,', '\\;'], $value);
    }
}
