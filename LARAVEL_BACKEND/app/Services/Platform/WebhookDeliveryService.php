<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class WebhookDeliveryService
{
    public function queueForCompany(Company $company, string $eventType, array $payload): int
    {
        $endpoints = WebhookEndpoint::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        $queued = 0;
        foreach ($endpoints as $endpoint) {
            $events = $endpoint->events ?? [];
            if ($events !== [] && ! in_array($eventType, $events, true) && ! in_array('*', $events, true)) {
                continue;
            }

            WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'company_id' => $company->id,
                'event_type' => $eventType,
                'status' => 'pending',
                'payload' => $payload,
            ]);
            $queued++;
        }

        return $queued;
    }

    public function processPending(int $limit = 50): int
    {
        $deliveries = WebhookDelivery::where('status', 'pending')
            ->with('endpoint')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        foreach ($deliveries as $delivery) {
            $endpoint = $delivery->endpoint;
            if (! $endpoint || ! $endpoint->is_active) {
                $delivery->update(['status' => 'failed', 'error' => 'Endpoint inactive']);
                continue;
            }

            try {
                $body = json_encode([
                    'event' => $delivery->event_type,
                    'company_id' => $delivery->company_id,
                    'payload' => $delivery->payload,
                    'timestamp' => now()->toIso8601String(),
                ], JSON_THROW_ON_ERROR);

                $signature = hash_hmac('sha256', $body, $endpoint->secret);

                $response = Http::timeout(10)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Savit-Signature' => $signature,
                        'X-Savit-Event' => $delivery->event_type,
                        'X-Savit-Delivery-Id' => (string) $delivery->id,
                    ])
                    ->withBody($body, 'application/json')
                    ->post($endpoint->url);

                $delivery->update([
                    'status' => $response->successful() ? 'delivered' : 'failed',
                    'response_code' => $response->status(),
                    'delivered_at' => $response->successful() ? now() : null,
                    'error' => $response->successful() ? null : mb_substr($response->body(), 0, 500),
                    'attempt' => $delivery->attempt + 1,
                ]);
                $processed++;
            } catch (\Throwable $e) {
                $delivery->update([
                    'status' => $delivery->attempt >= 3 ? 'failed' : 'pending',
                    'error' => mb_substr($e->getMessage(), 0, 500),
                    'attempt' => $delivery->attempt + 1,
                ]);
            }
        }

        return $processed;
    }

    public function createEndpoint(Company $company, string $url, array $events): WebhookEndpoint
    {
        return WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => mb_substr($url, 0, 500),
            'secret' => Str::random(32),
            'events' => $events,
            'is_active' => true,
        ]);
    }
}
