<?php

namespace App\Services\Agent\Tools;

use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;

final class SendEventTicketTool implements AgentTool
{
    public function __construct(
        protected EventService $events,
        protected EventRegistrationService $registrations,
    ) {}

    public function name(): string
    {
        return 'send_event_ticket';
    }

    public function description(): string
    {
        return 'Look up a paid event ticket for this customer (by phone) and return the ticket link, calendar file, and session details. Use after payment or when the customer asks for their ticket or registration confirmation.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ticket_code' => [
                    'type' => 'string',
                    'description' => 'Optional ticket code if the customer already has one.',
                ],
            ],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $company = $context->company;
        if (! $this->events->eventsEnabled($company)) {
            return ['success' => false, 'message' => 'Events are not enabled for this business.'];
        }

        $code = strtoupper(trim((string) ($arguments['ticket_code'] ?? '')));
        $phone = preg_replace('/\D+/', '', (string) ($context->customerPhone ?? ''));

        $attendee = null;
        if ($code !== '') {
            $attendee = EventAttendee::where('company_id', $company->id)
                ->where('ticket_code', $code)
                ->with(['event', 'session', 'registration'])
                ->first();
        }
        if (! $attendee && $phone !== '') {
            $attendee = EventAttendee::where('company_id', $company->id)
                ->where(function ($q) use ($phone) {
                    $q->where('phone', 'like', '%'.$phone)
                        ->orWhereHas('registration', fn ($r) => $r->where('buyer_phone', 'like', '%'.$phone));
                })
                ->whereIn('status', [EventAttendee::STATUS_VALID, EventAttendee::STATUS_USED])
                ->orderByDesc('id')
                ->with(['event', 'session', 'registration'])
                ->first();
        }

        if (! $attendee) {
            $pending = EventRegistration::where('company_id', $company->id)
                ->where('status', EventRegistration::STATUS_PENDING_PAYMENT)
                ->when($phone !== '', fn ($q) => $q->where('buyer_phone', 'like', '%'.$phone))
                ->with('order')
                ->orderByDesc('id')
                ->first();
            if ($pending?->order?->pay_token) {
                return [
                    'success' => false,
                    'pending_payment' => true,
                    'message' => 'Registration is waiting for payment.',
                    'pay_url' => url('/pay/'.$pending->order->pay_token),
                ];
            }

            return ['success' => false, 'message' => 'No ticket found for this customer.'];
        }

        if ($attendee->registration && $attendee->registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return [
                'success' => false,
                'pending_payment' => true,
                'message' => 'This registration is not paid yet.',
                'pay_url' => $attendee->registration->order_id && $attendee->registration->order
                    ? url('/pay/'.$attendee->registration->order->pay_token)
                    : null,
            ];
        }

        return [
            'success' => true,
            'ticket' => $this->registrations->serializeAttendee($attendee, true),
        ];
    }
}
