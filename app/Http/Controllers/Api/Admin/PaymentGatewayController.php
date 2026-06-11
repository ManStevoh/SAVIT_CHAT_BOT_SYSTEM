<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    /**
     * List all payment gateways (slug, name, is_enabled, config keys only - no secrets in list).
     */
    public function index(): JsonResponse
    {
        $gateways = PaymentGateway::orderBy('slug')->get();
        $data = $gateways->map(function (PaymentGateway $g) {
            $config = $g->config ?? [];
            $masked = $this->maskSecrets($g->slug, $config);

            return [
                'id' => (string) $g->id,
                'slug' => $g->slug,
                'name' => $g->name,
                'isEnabled' => (bool) $g->is_enabled,
                'config' => $masked,
            ];
        });

        return response()->json($data->values()->all());
    }

    /**
     * Update a payment gateway (is_enabled and/or config).
     */
    public function update(Request $request, string $slug): JsonResponse
    {
        $gateway = PaymentGateway::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'isEnabled' => 'sometimes|boolean',
            'config' => 'sometimes|array',
        ]);

        if (array_key_exists('isEnabled', $validated)) {
            $gateway->is_enabled = (bool) $validated['isEnabled'];
        }

        if (array_key_exists('config', $validated)) {
            $current = $gateway->config ?? [];
            $incoming = $validated['config'];
            foreach ($incoming as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if ($this->isSecretKey($gateway->slug, $key) && $this->isMaskedValue((string) $value)) {
                    continue;
                }
                $current[$key] = $value;
            }
            $gateway->config = $current;
        }

        $gateway->save();
        PaymentGateway::clearConfigCache($slug);

        $config = $gateway->config ?? [];
        $masked = $this->maskSecrets($gateway->slug, $config);

        return response()->json([
            'success' => true,
            'gateway' => [
                'id' => (string) $gateway->id,
                'slug' => $gateway->slug,
                'name' => $gateway->name,
                'isEnabled' => (bool) $gateway->is_enabled,
                'config' => $masked,
            ],
        ]);
    }

    protected function isSecretKey(string $slug, string $key): bool
    {
        return in_array($key, match ($slug) {
            'stripe' => ['secret', 'webhook_secret'],
            'mpesa' => ['consumer_secret', 'passkey'],
            'paystack' => ['secret_key'],
            default => [],
        }, true);
    }

    protected function isMaskedValue(string $value): bool
    {
        return str_starts_with($value, '••••') || $value === '';
    }

    /**
     * Mask secret keys for API response (show only last 4 chars or placeholder).
     */
    protected function maskSecrets(string $slug, array $config): array
    {
        $secretKeys = match ($slug) {
            'stripe' => ['secret', 'webhook_secret'],
            'mpesa' => ['consumer_secret', 'passkey'],
            'paystack' => ['secret_key'],
            default => [],
        };

        $out = [];
        foreach ($config as $key => $value) {
            if (in_array($key, $secretKeys, true) && is_string($value) && strlen($value) > 4) {
                $out[$key] = '••••••••'.substr($value, -4);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
