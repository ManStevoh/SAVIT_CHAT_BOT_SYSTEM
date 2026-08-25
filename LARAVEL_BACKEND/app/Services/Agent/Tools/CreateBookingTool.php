<?php

namespace App\Services\Agent\Tools;

use App\Models\Booking;
use App\Models\Product;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class CreateBookingTool implements AgentTool
{
    public function __construct(
        protected BookingService $bookings,
    ) {}

    public function name(): string
    {
        return 'create_booking';
    }

    public function description(): string
    {
        return 'Reserve and confirm a service appointment / booking time slot for the customer.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'starts_at' => [
                    'type' => 'string',
                    'description' => 'Selected start date and time for the booking (e.g. "2026-08-25 14:00" or ISO8601 string).',
                ],
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'Optional service product ID being booked.',
                ],
                'customer_name' => [
                    'type' => 'string',
                    'description' => 'Customer name for the reservation (optional, defaults to chat profile name).',
                ],
                'customer_phone' => [
                    'type' => 'string',
                    'description' => 'Customer contact phone (optional, defaults to customer WhatsApp phone).',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Special requests, notes, or preferences for the appointment.',
                ],
            ],
            'required' => ['starts_at'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $company = $context->company;
        $settings = $this->bookings->ensureSettings($company);

        if (! $settings->is_enabled) {
            return [
                'success' => false,
                'message' => 'Online bookings are currently disabled for this business.',
            ];
        }

        $startsAtRaw = trim((string) ($arguments['starts_at'] ?? ''));
        if ($startsAtRaw === '') {
            return [
                'success' => false,
                'message' => 'Please provide a valid start date and time for the booking.',
            ];
        }

        try {
            $tz = $settings->timezone ?: config('app.timezone', 'UTC');
            $startsAt = Carbon::parse($startsAtRaw, $tz);

            $productId = isset($arguments['product_id']) ? (int) $arguments['product_id'] : null;
            $product = null;
            if ($productId) {
                $product = Product::where('company_id', $company->id)->where('id', $productId)->first();
            }

            $customerName = trim((string) ($arguments['customer_name'] ?? '')) ?: ($context->customerName ?: 'Customer');
            $customerPhone = trim((string) ($arguments['customer_phone'] ?? '')) ?: ($context->customerPhone ?: null);
            $notes = isset($arguments['notes']) ? trim((string) $arguments['notes']) : null;

            $booking = $this->bookings->createBooking($company, [
                'starts_at' => $startsAt->toIso8601String(),
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'notes' => $notes,
                'title' => $product?->name ? ($product->name.' Appointment') : 'Service Appointment',
            ], $product);

            $paymentRequirement = $settings->payment_requirement ?? 'at_venue';
            $gCalUrl = $this->bookings->googleCalendarUrl($booking);
            $icsUrl = url('/bookings/'.$booking->id.'/ics?token='.$booking->manage_token);

            return [
                'success' => true,
                'booking_id' => (string) $booking->id,
                'title' => $booking->title,
                'service' => $product?->name,
                'starts_at' => $booking->starts_at->timezone($tz)->format('Y-m-d H:i (l)'),
                'ends_at' => $booking->ends_at->timezone($tz)->format('H:i'),
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'status' => $booking->status,
                'payment_requirement' => $paymentRequirement,
                'payment_note' => $paymentRequirement === 'at_venue'
                    ? 'Pay in person at venue.'
                    : ($paymentRequirement === 'required' ? 'Payment required online.' : 'Pay online or in person.'),
                'google_calendar_url' => $gCalUrl,
                'calendar_download_url' => $icsUrl,
            ];
        } catch (\Throwable $e) {
            Log::warning('CreateBookingTool execution error: '.$e->getMessage(), [
                'company_id' => $company->id,
                'arguments' => $arguments,
            ]);

            return [
                'success' => false,
                'message' => 'Unable to complete booking: '.$e->getMessage(),
            ];
        }
    }
}
