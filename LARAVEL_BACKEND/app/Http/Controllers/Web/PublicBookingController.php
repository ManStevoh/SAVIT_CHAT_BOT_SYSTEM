<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicBookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
    ) {}

    public function show(string $slug, Request $request)
    {
        $settings = BookingSetting::where('public_slug', $slug)->where('is_enabled', true)->firstOrFail();
        $company = Company::findOrFail($settings->company_id);

        $product = null;
        if ($request->filled('product')) {
            $product = Product::where('company_id', $company->id)
                ->where('id', $request->integer('product'))
                ->where('bookable', true)
                ->where('status', 'active')
                ->first();
        }

        $order = null;
        if ($request->filled('order')) {
            $order = Order::where('company_id', $company->id)
                ->where('id', $request->integer('order'))
                ->where('payment_status', 'paid')
                ->first();
        }

        $from = now();
        $to = now()->addDays(max(1, (int) $settings->max_days_ahead));
        $slots = $this->bookings->availableSlots($company, $product, $from, $to);

        $bookableProducts = Product::where('company_id', $company->id)
            ->where('bookable', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'booking_duration_minutes', 'description'])
            ->map(fn (Product $p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'durationMinutes' => $p->booking_duration_minutes ?: $settings->default_duration_minutes,
                'description' => $p->description,
            ])
            ->values()
            ->all();

        return Inertia::render('book/page', [
            'company' => [
                'name' => $company->name,
            ],
            'slug' => $slug,
            'timezone' => $settings->timezone,
            'slots' => $slots,
            'products' => $bookableProducts,
            'selectedProductId' => $product ? (string) $product->id : null,
            'orderId' => $order?->id,
            'prefill' => [
                'name' => $order?->customer_name,
                'phone' => $order?->customer_phone,
            ],
        ]);
    }

    public function store(string $slug, Request $request)
    {
        $settings = BookingSetting::where('public_slug', $slug)->where('is_enabled', true)->firstOrFail();
        $company = Company::findOrFail($settings->company_id);

        $validated = $request->validate([
            'startsAt' => 'required|date',
            'productId' => 'nullable|integer|exists:products,id',
            'orderId' => 'nullable|integer|exists:orders,id',
            'customerName' => 'required|string|max:255',
            'customerEmail' => 'nullable|email|max:255',
            'customerPhone' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:2000',
        ]);

        $product = null;
        if (! empty($validated['productId'])) {
            $product = Product::where('company_id', $company->id)
                ->where('id', $validated['productId'])
                ->where('bookable', true)
                ->firstOrFail();
        }

        $order = null;
        if (! empty($validated['orderId'])) {
            $order = Order::where('company_id', $company->id)
                ->where('id', $validated['orderId'])
                ->where('payment_status', 'paid')
                ->first();
        }

        try {
            $booking = $this->bookings->createBooking($company, $validated, $product, $order);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['startsAt' => $e->getMessage()]);
        }

        return redirect()->to(url('/book/'.$slug.'/confirmation/'.$booking->manage_token));
    }

    public function confirmation(string $slug, string $token)
    {
        $settings = BookingSetting::where('public_slug', $slug)->firstOrFail();
        $booking = Booking::where('company_id', $settings->company_id)
            ->where('manage_token', $token)
            ->firstOrFail();

        return Inertia::render('book/confirmation', [
            'company' => ['name' => $booking->company?->name ?? $settings->company?->name],
            'booking' => [
                'title' => $booking->title,
                'startsAt' => $booking->starts_at->toIso8601String(),
                'endsAt' => $booking->ends_at->toIso8601String(),
                'status' => $booking->status,
                'customerName' => $booking->customer_name,
                'googleCalendarUrl' => $this->bookings->googleCalendarUrl($booking),
                'icsUrl' => url('/bookings/'.$booking->id.'/ics?token='.$booking->manage_token),
                'timezone' => $settings->timezone,
            ],
        ]);
    }

    public function calendarFeed(string $slug, Request $request)
    {
        $settings = BookingSetting::where('public_slug', $slug)->firstOrFail();
        if ($request->query('token') !== $settings->calendar_feed_token) {
            abort(403);
        }
        $company = Company::findOrFail($settings->company_id);
        $bookings = Booking::where('company_id', $company->id)
            ->where('starts_at', '>=', now()->subDays(7))
            ->orderBy('starts_at')
            ->limit(500)
            ->get();

        return response($this->bookings->calendarFeedIcs($company, $bookings), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="bookings.ics"',
        ]);
    }

    public function bookingIcs(Booking $booking, Request $request)
    {
        if ($request->query('token') !== $booking->manage_token) {
            abort(403);
        }

        return response($this->bookings->bookingToIcs($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="booking-'.$booking->id.'.ics"',
        ]);
    }

    public function slots(string $slug, Request $request)
    {
        $settings = BookingSetting::where('public_slug', $slug)->where('is_enabled', true)->firstOrFail();
        $company = Company::findOrFail($settings->company_id);
        $product = null;
        if ($request->filled('product')) {
            $product = Product::where('company_id', $company->id)
                ->where('id', $request->integer('product'))
                ->where('bookable', true)
                ->first();
        }
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now();
        $to = $request->filled('to')
            ? Carbon::parse($request->query('to'))
            : now()->addDays((int) $settings->max_days_ahead);

        return response()->json([
            'slots' => $this->bookings->availableSlots($company, $product, $from, $to),
            'timezone' => $settings->timezone,
        ]);
    }
}
