<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\DomainEvent;
use Illuminate\Support\Facades\Log;

/**
 * Transactional outbox for platform domain events (v1) with notification + webhook fan-out.
 */
final class DomainEventDispatcher
{
    public function __construct(
        protected NotificationDispatcher $notifications,
        protected WebhookDeliveryService $webhooks,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $eventType, array $payload, ?int $companyId = null): DomainEvent
    {
        return DomainEvent::create([
            'company_id' => $companyId,
            'event_type' => mb_substr($eventType, 0, 80),
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    public function processPending(int $limit = 50): int
    {
        $events = DomainEvent::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($events as $event) {
            try {
                $this->handle($event);
                $event->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                    'attempts' => $event->attempts + 1,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $event->update([
                    'status' => $event->attempts >= 3 ? 'failed' : 'pending',
                    'attempts' => $event->attempts + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                Log::warning('Domain event dispatch failed', [
                    'event_id' => $event->id,
                    'type' => $event->event_type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->webhooks->processPending($limit);

        return $processed;
    }

    private function handle(DomainEvent $event): void
    {
        Log::info('Domain event dispatched', [
            'id' => $event->id,
            'type' => $event->event_type,
            'company_id' => $event->company_id,
        ]);

        if (! $event->company_id) {
            return;
        }

        $company = Company::find($event->company_id);
        if (! $company) {
            return;
        }

        $templateKey = match ($event->event_type) {
            'payment.received' => 'payment.received',
            'subscription.expired' => 'subscription.expiring',
            default => null,
        };

        if ($templateKey) {
            $this->notifications->dispatch($company, $templateKey, array_merge(
                $event->payload ?? [],
                ['owner_email' => $company->email],
            ));
        }

        $this->webhooks->queueForCompany($company, $event->event_type, $event->payload ?? []);
    }
}
