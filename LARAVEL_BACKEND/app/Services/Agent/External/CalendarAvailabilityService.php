<?php

namespace App\Services\Agent\External;

use App\Models\Company;
use App\Models\Product;
use App\Services\BookingService;
use Carbon\Carbon;

/**
 * Calendar / appointment availability from company working hours & BookingEngine.
 */
final class CalendarAvailabilityService
{
    public function __construct(
        protected ?BookingService $bookingService = null,
    ) {
        $this->bookingService = $bookingService ?? app(BookingService::class);
    }

    /**
     * @return array{timezone: ?string, publicBookingUrl?: string, slots: list<array<string, mixed>>}
     */
    public function availability(Company $company, int $daysAhead = 7, ?int $productId = null): array
    {
        $settings = $company->settings;
        $timezone = $settings?->timezone ?? config('app.timezone', 'UTC');
        $from = Carbon::now($timezone);
        $to = $from->copy()->addDays(max(1, $daysAhead))->endOfDay();

        $product = null;
        if ($productId) {
            $product = Product::where('company_id', $company->id)->where('id', $productId)->first();
        }

        // Try booking engine slots first
        $bookingSlots = [];
        try {
            $bookingSlots = $this->bookingService->availableSlots($company, $product, $from, $to);
        } catch (\Throwable) {
            $bookingSlots = [];
        }

        $publicUrl = null;
        try {
            $publicUrl = $this->bookingService->publicBookingUrl($company, $product);
        } catch (\Throwable) {
            $publicUrl = null;
        }

        if ($bookingSlots !== []) {
            // Group slots by date for clear AI reading
            $formattedSlots = [];
            foreach (array_slice($bookingSlots, 0, 30) as $slot) {
                $start = Carbon::parse($slot['start'])->timezone($timezone);
                $end = Carbon::parse($slot['end'])->timezone($timezone);
                $formattedSlots[] = [
                    'date' => $start->toDateString(),
                    'day' => $start->englishDayOfWeek,
                    'startTime' => $start->format('H:i'),
                    'endTime' => $end->format('H:i'),
                    'slotStartIso' => $slot['start'],
                    'available' => true,
                ];
            }

            return [
                'timezone' => $timezone,
                'publicBookingUrl' => $publicUrl,
                'slots' => $formattedSlots,
            ];
        }

        // Fallback to working hours
        $workingHours = $settings?->working_hours ?? [];
        $slots = [];
        for ($i = 0; $i < $daysAhead; $i++) {
            $day = $from->copy()->addDays($i);
            $dayName = strtolower($day->englishDayOfWeek);
            $hours = $this->hoursForDay($workingHours, $dayName);

            $slots[] = [
                'date' => $day->toDateString(),
                'day' => $day->englishDayOfWeek,
                'open' => $hours['open'] ?? '09:00',
                'close' => $hours['close'] ?? '17:00',
                'available' => $hours['enabled'] ?? true,
            ];
        }

        return [
            'timezone' => $timezone,
            'publicBookingUrl' => $publicUrl,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $workingHours
     * @return array{enabled: bool, open: string, close: string}
     */
    private function hoursForDay(array $workingHours, string $dayName): array
    {
        if ($workingHours === []) {
            return ['enabled' => true, 'open' => '09:00', 'close' => '17:00'];
        }

        foreach ($workingHours as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entryDay = strtolower((string) ($entry['day'] ?? $entry['weekday'] ?? ''));
            if ($entryDay === $dayName || $entryDay === substr($dayName, 0, 3)) {
                return [
                    'enabled' => (bool) ($entry['enabled'] ?? $entry['open'] ?? true),
                    'open' => (string) ($entry['open'] ?? $entry['from'] ?? '09:00'),
                    'close' => (string) ($entry['close'] ?? $entry['to'] ?? '17:00'),
                ];
            }
        }

        return ['enabled' => false, 'open' => '09:00', 'close' => '17:00'];
    }
}

