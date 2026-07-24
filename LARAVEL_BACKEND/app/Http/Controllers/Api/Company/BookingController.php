<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingSetting;
use App\Models\Product;
use App\Services\BookingService;
use App\Services\Platform\EntitlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected EntitlementService $entitlements,
    ) {}

    public function settings(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        if (! $this->entitlements->allowsBookings($company)) {
            return response()->json([
                'success' => false,
                'message' => 'Bookings are not included in your plan.',
                'code' => 'bookings_required',
            ], 403);
        }

        $settings = $this->bookings->ensureSettings($company);
        $availability = BookingAvailability::where('company_id', $company->id)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->map(fn (BookingAvailability $a) => [
                'weekday' => $a->weekday,
                'startTime' => substr((string) $a->start_time, 0, 5),
                'endTime' => substr((string) $a->end_time, 0, 5),
            ])
            ->values()
            ->all();

        return response()->json([
            'settings' => $this->settingsToArray($settings),
            'availability' => $availability,
            'publicBookingUrl' => url('/book/'.$settings->public_slug),
            'calendarFeedUrl' => $this->bookings->calendarFeedUrl($company),
            'maxBookingsPerMonth' => $this->entitlements->maxBookingsPerMonth($company),
            'bookingsThisMonth' => Booking::query()
                ->where('company_id', $company->id)
                ->where('status', '!=', Booking::STATUS_CANCELLED)
                ->where('starts_at', '>=', now()->startOfMonth())
                ->count(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        if (! $this->entitlements->allowsBookings($company)) {
            return response()->json([
                'success' => false,
                'message' => 'Bookings are not included in your plan.',
                'code' => 'bookings_required',
            ], 403);
        }

        $validated = $request->validate([
            'timezone' => 'sometimes|string|max:64',
            'defaultDurationMinutes' => 'sometimes|integer|min:5|max:480',
            'bufferMinutes' => 'sometimes|integer|min:0|max:240',
            'minNoticeMinutes' => 'sometimes|integer|min:0|max:10080',
            'maxDaysAhead' => 'sometimes|integer|min:1|max:365',
            'publicSlug' => 'sometimes|string|max:80|alpha_dash',
            'calendarWebhookUrl' => 'nullable|url|max:2048',
            'isEnabled' => 'sometimes|boolean',
            'availability' => 'sometimes|array',
            'availability.*.weekday' => 'required_with:availability|integer|min:0|max:6',
            'availability.*.startTime' => 'required_with:availability|date_format:H:i',
            'availability.*.endTime' => 'required_with:availability|date_format:H:i|after:availability.*.startTime',
        ]);

        $settings = $this->bookings->ensureSettings($company);
        $updates = [];
        if (array_key_exists('timezone', $validated)) {
            $updates['timezone'] = $validated['timezone'];
        }
        if (array_key_exists('defaultDurationMinutes', $validated)) {
            $updates['default_duration_minutes'] = $validated['defaultDurationMinutes'];
        }
        if (array_key_exists('bufferMinutes', $validated)) {
            $updates['buffer_minutes'] = $validated['bufferMinutes'];
        }
        if (array_key_exists('minNoticeMinutes', $validated)) {
            $updates['min_notice_minutes'] = $validated['minNoticeMinutes'];
        }
        if (array_key_exists('maxDaysAhead', $validated)) {
            $updates['max_days_ahead'] = $validated['maxDaysAhead'];
        }
        if (array_key_exists('calendarWebhookUrl', $validated)) {
            $updates['calendar_webhook_url'] = $validated['calendarWebhookUrl'] ?: null;
        }
        if (array_key_exists('isEnabled', $validated)) {
            $updates['is_enabled'] = (bool) $validated['isEnabled'];
        }
        if (array_key_exists('publicSlug', $validated)) {
            $slug = strtolower($validated['publicSlug']);
            $taken = BookingSetting::where('public_slug', $slug)
                ->where('company_id', '!=', $company->id)
                ->exists();
            if ($taken) {
                return response()->json(['success' => false, 'message' => 'That booking slug is taken.'], 422);
            }
            $updates['public_slug'] = $slug;
        }
        if ($updates !== []) {
            $settings->update($updates);
        }

        if (array_key_exists('availability', $validated)) {
            $this->bookings->syncAvailability($company, $validated['availability']);
        }

        return $this->settings($request);
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        if (! $this->entitlements->allowsBookings($company)) {
            return response()->json([
                'success' => false,
                'message' => 'Bookings are not included in your plan.',
                'code' => 'bookings_required',
            ], 403);
        }

        $query = Booking::where('company_id', $company->id)->with('product:id,name')->orderBy('starts_at');
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->boolean('upcoming')) {
            $query->where('starts_at', '>=', now())->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED]);
        }

        $items = $query->limit(200)->get()->map(fn (Booking $b) => $this->bookingToArray($b))->values()->all();

        return response()->json(['bookings' => $items]);
    }

    public function updateStatus(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);
        if ($validated['status'] === Booking::STATUS_CANCELLED) {
            $this->bookings->cancelBooking($booking);
        } else {
            $booking->update(['status' => $validated['status']]);
        }

        return response()->json([
            'success' => true,
            'booking' => $this->bookingToArray($booking->fresh('product')),
        ]);
    }

    private function settingsToArray(BookingSetting $settings): array
    {
        return [
            'timezone' => $settings->timezone,
            'defaultDurationMinutes' => $settings->default_duration_minutes,
            'bufferMinutes' => $settings->buffer_minutes,
            'minNoticeMinutes' => $settings->min_notice_minutes,
            'maxDaysAhead' => $settings->max_days_ahead,
            'publicSlug' => $settings->public_slug,
            'calendarWebhookUrl' => $settings->calendar_webhook_url,
            'isEnabled' => $settings->is_enabled,
        ];
    }

    private function bookingToArray(Booking $booking): array
    {
        return [
            'id' => (string) $booking->id,
            'title' => $booking->title,
            'productId' => $booking->product_id ? (string) $booking->product_id : null,
            'productName' => $booking->product?->name,
            'customerName' => $booking->customer_name,
            'customerEmail' => $booking->customer_email,
            'customerPhone' => $booking->customer_phone,
            'startsAt' => $booking->starts_at?->toIso8601String(),
            'endsAt' => $booking->ends_at?->toIso8601String(),
            'status' => $booking->status,
            'notes' => $booking->notes,
            'googleCalendarUrl' => $this->bookings->googleCalendarUrl($booking),
            'icsUrl' => url('/bookings/'.$booking->id.'/ics?token='.$booking->manage_token),
        ];
    }
}
