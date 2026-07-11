<?php

namespace App\Services\Agent\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shipping quote — external API when configured, otherwise zone heuristic.
 */
final class ShippingQuoteService
{
    /**
     * @return array{success: bool, quote?: array<string, mixed>, source: string, message?: string}
     */
    public function quote(int $companyId, string $destination, float $orderTotal, ?float $weightKg = null): array
    {
        $apiUrl = config('agent.external.shipping_api_url');
        $apiKey = config('agent.external.shipping_api_key');

        if ($apiUrl) {
            try {
                $response = Http::timeout(15)
                    ->when($apiKey, fn ($r) => $r->withToken($apiKey))
                    ->post($apiUrl, [
                        'company_id' => $companyId,
                        'destination' => $destination,
                        'order_total' => $orderTotal,
                        'weight_kg' => $weightKg,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    if (is_array($data)) {
                        return [
                            'success' => true,
                            'quote' => [
                                'amount' => (float) ($data['amount'] ?? $data['cost'] ?? 0),
                                'currency' => (string) ($data['currency'] ?? 'USD'),
                                'eta_days' => (int) ($data['eta_days'] ?? $data['days'] ?? 3),
                                'carrier' => (string) ($data['carrier'] ?? 'partner'),
                            ],
                            'source' => 'external_api',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Shipping API failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->heuristicQuote($destination, $orderTotal, $weightKg);
    }

    /**
     * @return array{success: bool, quote: array<string, mixed>, source: string}
     */
    private function heuristicQuote(string $destination, float $orderTotal, ?float $weightKg): array
    {
        $base = match (true) {
            $orderTotal >= 10000 => 0.0,
            $orderTotal >= 5000 => 250.0,
            default => 400.0,
        };
        if ($weightKg !== null && $weightKg > 5) {
            $base += ($weightKg - 5) * 50;
        }
        $destLower = mb_strtolower(trim($destination));
        if (str_contains($destLower, 'nairobi') || str_contains($destLower, 'cbd')) {
            $eta = 1;
        } elseif ($destLower !== '') {
            $eta = 3;
            $base += 150;
        } else {
            $eta = 2;
        }

        return [
            'success' => true,
            'quote' => [
                'amount' => round($base, 2),
                'currency' => 'KES',
                'eta_days' => $eta,
                'carrier' => 'standard',
                'note' => 'Estimated quote — confirm at checkout.',
            ],
            'source' => 'heuristic',
        ];
    }
}
