<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    public const CACHE_KEY_SUB_PREFIX = 'paystack_pending:';

    public const CACHE_KEY_ORDER_PREFIX = 'paystack_pending_order:';

    protected array $config = [];

    public function __construct()
    {
        $this->config = PaymentGateway::isEnabled('paystack')
            ? PaymentGateway::getConfig('paystack')
            : [];
    }

    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('paystack');
    }

    public function getPublicKey(): string
    {
        return (string) ($this->config['public_key'] ?? '');
    }

    public function getCurrency(): string
    {
        return strtolower((string) ($this->config['currency'] ?? 'ngn'));
    }

    /**
     * Convert major currency units to Paystack subunits (kobo, pesewas, cents, etc.).
     */
    public function amountToSubunit(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url?: string, reference?: string, error?: string}
     */
    public function initializeTransaction(
        string $email,
        int $amountSubunit,
        string $reference,
        string $callbackUrl,
        array $metadata = [],
        ?array $configOverride = null
    ): array {
        $cfg = is_array($configOverride) && ! empty($configOverride['secret_key']) ? $configOverride : $this->config;
        $secret = $cfg['secret_key'] ?? '';
        if (! $secret) {
            return ['error' => 'Paystack secret key not configured'];
        }
        $currency = strtoupper((string) ($cfg['currency'] ?? $this->getCurrency()));

        if ($amountSubunit <= 0) {
            return ['error' => 'Amount must be greater than zero'];
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->post('https://api.paystack.co/transaction/initialize', [
                    'email' => $email,
                    'amount' => $amountSubunit,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => $metadata,
                    'currency' => $currency,
                ]);

            if (! $response->successful()) {
                $message = $response->json('message') ?? $response->body();
                Log::error('Paystack initialize failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['error' => is_string($message) ? $message : 'Paystack initialization failed'];
            }

            $data = $response->json('data') ?? [];

            return [
                'authorization_url' => $data['authorization_url'] ?? null,
                'reference' => $data['reference'] ?? $reference,
            ];
        } catch (\Throwable $e) {
            Log::error('Paystack initialize error: '.$e->getMessage());

            return ['error' => 'Could not connect to Paystack'];
        }
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function verifyTransaction(string $reference, ?array $configOverride = null): array
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['secret_key']) ? $configOverride : $this->config;
        $secret = $cfg['secret_key'] ?? '';
        if (! $secret) {
            return ['success' => false, 'error' => 'Paystack secret key not configured'];
        }

        try {
            $response = Http::withToken($secret)
                ->acceptJson()
                ->get('https://api.paystack.co/transaction/verify/'.$reference);

            if (! $response->successful()) {
                return ['success' => false, 'error' => $response->json('message') ?? 'Verification failed'];
            }

            $data = $response->json('data') ?? [];
            if (($data['status'] ?? '') !== 'success') {
                return ['success' => false, 'error' => 'Payment not successful'];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Throwable $e) {
            Log::error('Paystack verify error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not verify payment'];
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = $this->config['secret_key'] ?? '';
        if (! $secret || $signature === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    /**
     * Create a Paystack payment link for a customer order (platform config only).
     *
     * @return array{success: bool, url?: string, reference?: string, error?: string}
     */
    public function createPaymentLinkForOrder(Order $order, string $callbackUrl, ?array $configOverride = null): array
    {
        if (! self::isEnabled() && empty($configOverride['secret_key'])) {
            return ['success' => false, 'error' => 'Paystack is not configured.'];
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Order total must be greater than zero.'];
        }

        $email = $this->resolveOrderCustomerEmail($order);
        $reference = 'essem_ord_'.$order->id.'_'.uniqid();

        $result = $this->initializeTransaction(
            $email,
            $this->amountToSubunit($amount),
            $reference,
            $callbackUrl,
            [
                'order_id' => $order->id,
                'type' => 'order',
            ],
            $configOverride
        );

        if (! empty($result['error']) || empty($result['authorization_url'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not create payment link.'];
        }

        return [
            'success' => true,
            'url' => $result['authorization_url'],
            'reference' => $result['reference'] ?? $reference,
        ];
    }

    /**
     * Paystack requires a valid email. WhatsApp/storefront orders often have phone only.
     */
    public function resolveOrderCustomerEmail(Order $order): string
    {
        $email = trim((string) ($order->customer_email ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $phone = trim((string) ($order->customer_phone ?? ''));
        if ($phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone);
            if ($digits !== '') {
                return $digits.'@customers.essemchat.com';
            }
        }

        return 'order-'.$order->id.'@orders.essemchat.com';
    }
}
