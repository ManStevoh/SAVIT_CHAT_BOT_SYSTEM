<?php

namespace App\Services\Agent\Tools;

use App\Models\Event;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Events\EventService;
use App\Support\MoneyFormatter;

final class ShareEventLinkTool implements AgentTool
{
    public function __construct(
        protected EventService $events,
    ) {}

    public function name(): string
    {
        return 'share_event_link';
    }

    public function description(): string
    {
        return 'Share the public registration link, session times, price, and payment details for a published event. Use when a customer asks to register, wants an event link, or asks how to pay for a workshop, bootcamp, or ticketed gathering.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Optional event title search. If omitted, returns upcoming published events.',
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

        $query = trim((string) ($arguments['query'] ?? ''));
        $events = Event::where('company_id', $company->id)
            ->where('status', Event::STATUS_PUBLISHED)
            ->with(['sessions', 'ticketTypes'])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('title', 'like', '%'.$query.'%')
                        ->orWhere('description', 'like', '%'.$query.'%');
                });
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        if ($events->isEmpty()) {
            return ['success' => false, 'message' => 'No published events matched.', 'events' => []];
        }

        $payment = $this->events->paymentDetails($company);

        return [
            'success' => true,
            'payment_details' => $payment['details'],
            'events' => $events->map(function (Event $event) use ($company) {
                $ticket = $event->ticketTypes->first();
                $session = $event->sessions->first();
                $price = $ticket ? (float) $ticket->price : 0;

                return [
                    'id' => (string) $event->id,
                    'title' => $event->title,
                    'url' => $this->events->publicUrl($event),
                    'session' => $session?->displayTitle(),
                    'starts_at' => $session?->starts_at?->timezone($event->timezone ?: 'Africa/Nairobi')->format('l j M Y, g:i A'),
                    'price' => MoneyFormatter::formatForCompany($price, $company),
                    'is_free' => $ticket ? ((bool) $ticket->is_free || $price <= 0) : false,
                ];
            })->values()->all(),
        ];
    }
}
