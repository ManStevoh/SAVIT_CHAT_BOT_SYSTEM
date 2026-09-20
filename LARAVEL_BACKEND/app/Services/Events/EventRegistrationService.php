<?php

namespace App\Services\Events;

use App\Models\Company;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventCheckIn;
use App\Models\EventRegistration;
use App\Models\EventSession;
use App\Models\EventTicketType;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRegistrationService
{
    public function __construct(
        protected EventService $events,
    ) {}

    public function occupiedQuantityForSession(EventSession $session): int
    {
        return (int) EventRegistration::query()
            ->where('event_session_id', $session->id)
            ->where(function ($q) {
                $q->where('status', EventRegistration::STATUS_CONFIRMED)
                    ->orWhere(function ($hold) {
                        $hold->where('status', EventRegistration::STATUS_PENDING_PAYMENT)
                            ->where(function ($exp) {
                                $exp->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now());
                            });
                    });
            })
            ->sum('quantity');
    }

    public function occupiedQuantityForTicket(EventTicketType $ticket): int
    {
        return (int) EventRegistration::query()
            ->where('event_ticket_type_id', $ticket->id)
            ->where(function ($q) {
                $q->where('status', EventRegistration::STATUS_CONFIRMED)
                    ->orWhere(function ($hold) {
                        $hold->where('status', EventRegistration::STATUS_PENDING_PAYMENT)
                            ->where(function ($exp) {
                                $exp->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now());
                            });
                    });
            })
            ->sum('quantity');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{registration: EventRegistration, order: Order, payUrl: string, free: bool}
     */
    public function register(Company $company, Event $event, array $data): array
    {
        if (! $this->events->eventsEnabled($company)) {
            throw ValidationException::withMessages(['event' => 'Events are disabled for this store.']);
        }
        if (! $event->registrationIsOpen()) {
            throw ValidationException::withMessages(['event' => 'Registration is not open for this event.']);
        }

        $sessionId = (int) ($data['sessionId'] ?? $data['event_session_id'] ?? 0);
        $ticketId = (int) ($data['ticketTypeId'] ?? $data['event_ticket_type_id'] ?? 0);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $buyerName = trim((string) ($data['buyerName'] ?? $data['customerName'] ?? $data['name'] ?? ''));
        $buyerEmail = $this->nullable(trim((string) ($data['buyerEmail'] ?? $data['customerEmail'] ?? $data['email'] ?? '')));
        $buyerPhone = $this->nullable(trim((string) ($data['buyerPhone'] ?? $data['customerPhone'] ?? $data['phone'] ?? '')));

        if ($buyerName === '') {
            throw ValidationException::withMessages(['buyerName' => 'Name is required.']);
        }
        if (! $buyerEmail && ! $buyerPhone) {
            throw ValidationException::withMessages(['buyerEmail' => 'Email or phone is required.']);
        }

        return DB::transaction(function () use (
            $company, $event, $sessionId, $ticketId, $quantity, $buyerName, $buyerEmail, $buyerPhone, $data
        ) {
            $session = EventSession::where('company_id', $company->id)
                ->where('event_id', $event->id)
                ->where('id', $sessionId)
                ->lockForUpdate()
                ->first();
            if (! $session || $session->status !== EventSession::STATUS_SCHEDULED) {
                throw ValidationException::withMessages(['sessionId' => 'That session is not available.']);
            }

            $ticket = EventTicketType::where('company_id', $company->id)
                ->where('event_id', $event->id)
                ->where('id', $ticketId)
                ->lockForUpdate()
                ->first();
            if (! $ticket || ! $ticket->isOnSale()) {
                throw ValidationException::withMessages(['ticketTypeId' => 'That ticket is not on sale.']);
            }
            if ($ticket->event_session_id && (int) $ticket->event_session_id !== (int) $session->id) {
                throw ValidationException::withMessages(['ticketTypeId' => 'That ticket is not valid for this session.']);
            }

            $min = max(1, (int) $ticket->min_per_order);
            $max = max($min, (int) $ticket->max_per_order);
            if ($quantity < $min || $quantity > $max) {
                throw ValidationException::withMessages(['quantity' => "Quantity must be between {$min} and {$max}."]);
            }

            $sessionRemaining = $this->events->sessionRemaining($session);
            if ($sessionRemaining !== null && $sessionRemaining < $quantity) {
                throw ValidationException::withMessages(['sessionId' => 'This session is sold out.']);
            }
            $ticketRemaining = $this->events->ticketRemaining($ticket);
            if ($ticket->quantity_total !== null && $ticketRemaining < $quantity) {
                throw ValidationException::withMessages(['ticketTypeId' => 'Those tickets are sold out.']);
            }

            $isFree = $ticket->is_free || (float) $ticket->price <= 0;
            $unitPrice = $isFree ? 0.0 : (float) $ticket->price;
            $lineTotal = round($unitPrice * $quantity, 2);

            $this->events->syncTicketProduct($ticket->fresh('event'));
            $ticket->refresh();

            $orderNumber = 'EVT-'.strtoupper(Str::random(6));
            $order = Order::create([
                'company_id' => $company->id,
                'order_number' => $orderNumber,
                'customer_name' => $buyerName,
                'customer_email' => $buyerEmail,
                'customer_phone' => $buyerPhone ?? '',
                'fulfillment_type' => 'ticket',
                'subtotal' => $lineTotal,
                'tax_total' => 0,
                'delivery_fee' => 0,
                'discount_total' => 0,
                'total' => $lineTotal,
                'status' => 'pending',
                'payment_status' => $isFree ? 'paid' : 'pending',
                'source' => 'event',
            ]);
            $order->ensurePublicTokens();

            $line = OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $ticket->product_id,
                'name' => $ticket->name.' — '.$event->title,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'line_subtotal' => $lineTotal,
                'fulfillment_data' => [
                    'productType' => 'event',
                    'fulfillmentType' => 'ticket',
                    'eventId' => $event->id,
                    'eventSessionId' => $session->id,
                    'eventTicketTypeId' => $ticket->id,
                    'requiresDeliveryAddress' => false,
                ],
            ]);

            $registration = EventRegistration::create([
                'company_id' => $company->id,
                'event_id' => $event->id,
                'event_session_id' => $session->id,
                'event_ticket_type_id' => $ticket->id,
                'order_id' => $order->id,
                'order_product_id' => $line->id,
                'buyer_name' => $buyerName,
                'buyer_email' => $buyerEmail,
                'buyer_phone' => $buyerPhone,
                'quantity' => $quantity,
                'status' => $isFree ? EventRegistration::STATUS_CONFIRMED : EventRegistration::STATUS_PENDING_PAYMENT,
                'hold_expires_at' => $isFree ? null : now()->addMinutes(EventService::HOLD_MINUTES),
                'answers' => is_array($data['answers'] ?? null) ? $data['answers'] : null,
            ]);

            if ($isFree) {
                $this->issueTickets($registration->fresh());
                $order->update(['status' => 'confirmed', 'payment_status' => 'paid', 'payment_method' => 'free']);
            }

            $this->events->syncTicketProduct($ticket->fresh('event'));

            return [
                'registration' => $registration->fresh(['attendees', 'session', 'ticketType']),
                'order' => $order->fresh(),
                'payUrl' => url('/pay/'.$order->pay_token),
                'free' => $isFree,
            ];
        });
    }

    public function confirmPaidOrder(Order $order): void
    {
        $registrations = EventRegistration::where('order_id', $order->id)->get();
        foreach ($registrations as $registration) {
            if ($registration->status === EventRegistration::STATUS_CANCELLED) {
                continue;
            }
            $registration->update([
                'status' => EventRegistration::STATUS_CONFIRMED,
                'hold_expires_at' => null,
            ]);
            $this->issueTickets($registration->fresh());
            if ($registration->ticketType) {
                $this->events->syncTicketProduct($registration->ticketType->fresh('event'));
            }
        }
    }

    public function issueTickets(EventRegistration $registration): void
    {
        $existing = $registration->attendees()->count();
        $needed = max(0, (int) $registration->quantity - $existing);
        for ($i = 0; $i < $needed; $i++) {
            EventAttendee::create([
                'company_id' => $registration->company_id,
                'event_registration_id' => $registration->id,
                'event_id' => $registration->event_id,
                'event_session_id' => $registration->event_session_id,
                'name' => $registration->buyer_name,
                'email' => $registration->buyer_email,
                'phone' => $registration->buyer_phone,
                'ticket_code' => $this->uniqueTicketCode(),
                'qr_secret' => bin2hex(random_bytes(16)),
                'status' => EventAttendee::STATUS_VALID,
            ]);
        }
    }

    public function cancel(EventRegistration $registration): EventRegistration
    {
        if ($registration->status === EventRegistration::STATUS_CANCELLED) {
            return $registration;
        }
        $registration->update(['status' => EventRegistration::STATUS_CANCELLED, 'hold_expires_at' => null]);
        $registration->attendees()->update(['status' => EventAttendee::STATUS_CANCELLED]);
        if ($registration->ticketType) {
            $this->events->syncTicketProduct($registration->ticketType->fresh('event'));
        }

        return $registration->fresh();
    }

    public function checkIn(EventAttendee $attendee, ?User $by = null, string $source = 'dashboard'): EventAttendee
    {
        if ($attendee->status === EventAttendee::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['ticket' => 'This ticket is cancelled.']);
        }
        if ($attendee->checked_in_at) {
            return $attendee;
        }

        $attendee->update([
            'checked_in_at' => now(),
            'status' => EventAttendee::STATUS_USED,
        ]);
        EventCheckIn::create([
            'company_id' => $attendee->company_id,
            'event_attendee_id' => $attendee->id,
            'checked_in_by' => $by?->id,
            'source' => $source,
            'checked_in_at' => now(),
        ]);

        return $attendee->fresh();
    }

    public function attendeeIcs(EventAttendee $attendee): string
    {
        $attendee->loadMissing(['event', 'session', 'company']);
        $event = $attendee->event;
        $session = $attendee->session;
        $uid = $attendee->ticket_code.'@relayiq.events';
        $stamp = now()->utc()->format('Ymd\THis\Z');
        $start = $session?->starts_at?->utc()->format('Ymd\THis\Z') ?? $stamp;
        $end = $session?->ends_at?->utc()->format('Ymd\THis\Z') ?? $stamp;
        $summary = $this->icsEscape($event?->title ?: 'Event');
        $desc = $this->icsEscape(($session?->displayTitle() ?: '').' — ticket '.$attendee->ticket_code);
        $location = $this->icsEscape($session?->venue_name ?: ($event?->venue_name ?: ''));

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//RelayIQ//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp,
            'DTSTART:'.$start,
            'DTEND:'.$end,
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$desc,
            'LOCATION:'.$location,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    public function googleCalendarUrl(EventAttendee $attendee): string
    {
        $attendee->loadMissing(['event', 'session']);
        $session = $attendee->session;
        $event = $attendee->event;
        $start = $session?->starts_at?->utc()->format('Ymd\THis\Z');
        $end = $session?->ends_at?->utc()->format('Ymd\THis\Z');
        $params = http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event?->title ?: 'Event',
            'dates' => $start.'/'.$end,
            'details' => 'Ticket '.$attendee->ticket_code,
            'location' => $session?->venue_name ?: ($event?->venue_name ?: ''),
        ]);

        return 'https://calendar.google.com/calendar/render?'.$params;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRegistration(EventRegistration $registration): array
    {
        $registration->loadMissing(['session', 'ticketType', 'attendees', 'order']);

        return [
            'id' => (string) $registration->id,
            'eventId' => (string) $registration->event_id,
            'sessionId' => (string) $registration->event_session_id,
            'sessionTitle' => $registration->session?->displayTitle(),
            'sessionStartsAt' => $registration->session?->starts_at?->toIso8601String(),
            'ticketTypeId' => (string) $registration->event_ticket_type_id,
            'ticketName' => $registration->ticketType?->name,
            'orderId' => $registration->order_id ? (string) $registration->order_id : null,
            'orderNumber' => $registration->order?->order_number,
            'payUrl' => $registration->order?->pay_token ? url('/pay/'.$registration->order->pay_token) : null,
            'buyerName' => $registration->buyer_name,
            'buyerEmail' => $registration->buyer_email,
            'buyerPhone' => $registration->buyer_phone,
            'quantity' => $registration->quantity,
            'status' => $registration->status,
            'holdExpiresAt' => $registration->hold_expires_at?->toIso8601String(),
            'answers' => $registration->answers,
            'attendees' => $registration->attendees->map(fn (EventAttendee $a) => $this->serializeAttendee($a))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeAttendee(EventAttendee $attendee, bool $includeJoinUrl = false): array
    {
        $attendee->loadMissing(['event', 'session']);
        $joinUrl = null;
        if ($includeJoinUrl && $attendee->status === EventAttendee::STATUS_VALID) {
            $joinUrl = $attendee->session?->online_url ?: $attendee->event?->online_url;
        }

        return [
            'id' => (string) $attendee->id,
            'name' => $attendee->name,
            'email' => $attendee->email,
            'phone' => $attendee->phone,
            'ticketCode' => $attendee->ticket_code,
            'ticketUrl' => $attendee->publicUrl(),
            'icsUrl' => url('/ticket/'.$attendee->ticket_code.'/ics'),
            'googleCalendarUrl' => $this->googleCalendarUrl($attendee),
            'checkedInAt' => $attendee->checked_in_at?->toIso8601String(),
            'status' => $attendee->status,
            'joinUrl' => $joinUrl,
            'eventTitle' => $attendee->event?->title,
            'sessionTitle' => $attendee->session?->displayTitle(),
            'startsAt' => $attendee->session?->starts_at?->toIso8601String(),
            'endsAt' => $attendee->session?->ends_at?->toIso8601String(),
            'venueName' => $attendee->session?->venue_name ?: $attendee->event?->venue_name,
        ];
    }

    private function uniqueTicketCode(): string
    {
        do {
            $code = 'EVT-'.strtoupper(Str::random(8));
        } while (EventAttendee::where('ticket_code', $code)->exists());

        return $code;
    }

    private function icsEscape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $value);
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
