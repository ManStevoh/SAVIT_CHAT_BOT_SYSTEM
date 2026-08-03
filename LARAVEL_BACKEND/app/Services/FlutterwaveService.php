<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveService
{
    public const CACHE_KEY_SUB_PREFIX = 'flutterwave_pending:';

    public const CACHE_KEY_ORDER_PREFIX = 'flutterwave_pending_order:';

    public const BASE_URL = 'https://api.flutterwave.com/v3';

    protected array $config = [];

    public function __construct()
    {
        $this->config = PaymentGateway::isEnabled('flutterwave')
            ? PaymentGateway::getConfig('flutterwave')
            : [];
    }

    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('flutterwave');
    }

    public function getCurrency(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return strtoupper((string) ($cfg['currency'] ?? 'KES'));
    }

    /**
     * Get secret key from configuration.
     */
    protected function getSecretKey(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['secret_key']) ? $configOverride : $this->config;

        return (string) ($cfg['secret_key'] ?? '');
    }

    /**
     * Get secret hash for webhook signature validation.
     */
    public function getSecretHash(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return (string) ($cfg['secret_hash'] ?? $cfg['secret_key'] ?? '');
    }

    /**
     * Initialize a payment session with Flutterwave API v3.
     *
     * @param  array<string, mixed>  $paymentData
     * @return array{success: bool, link?: string, tx_ref?: string, error?: string}
     */
    public function initializePayment(array $paymentData, ?array $configOverride = null): array
    {
        $secretKey = $this->getSecretKey($configOverride);
        if ($secretKey === '') {
            return ['success' => false, 'error' => 'Flutterwave secret key not configured'];
        }

        $amount = (float) ($paymentData['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than zero'];
        }

        $txRef = (string) ($paymentData['tx_ref'] ?? ('flw_ref_'.uniqid()));
        $currency = strtoupper((string) ($paymentData['currency'] ?? $this->getCurrency($configOverride)));
        $callbackUrl = (string) ($paymentData['redirect_url'] ?? rtrim(config('app.frontend_url', 'http://localhost:3000'), '/').'/orders/payment-complete');
        $customer = $paymentData['customer'] ?? [];

        $payload = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => $callbackUrl,
            'customer' => [
                'email' => (string) ($customer['email'] ?? 'customer@essemchat.com'),
                'phonenumber' => (string) ($customer['phonenumber'] ?? ''),
                'name' => (string) ($customer['name'] ?? 'Customer'),
            ],
            'customizations' => [
                'title' => (string) ($paymentData['title'] ?? 'Order Payment'),
                'description' => (string) ($paymentData['description'] ?? 'Payment for order'),
            ],
        ];

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->asJson()
                ->post(self::BASE_URL.'/payments', $payload);

            if (! $response->successful()) {
                $msg = $response->json('message') ?? $response->body();
                Log::error('Flutterwave payments initialize failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => is_string($msg) ? $msg : 'Flutterwave initialization failed'];
            }

            $json = $response->json() ?? [];
            $link = $json['data']['link'] ?? null;
            if (! is_string($link) || $link === '') {
                return ['success' => false, 'error' => 'Flutterwave did not return a payment link'];
            }

            return [
                'success' => true,
                'link' => $link,
                'tx_ref' => $txRef,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave initializePayment error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to Flutterwave'];
        }
    }

    /**
     * Verify a transaction with Flutterwave API v3 by Transaction ID.
     *
     * @return array{success: bool, paid?: bool, data?: array<string, mixed>, error?: string}
     */
    public function verifyTransaction(string $transactionId, ?array $configOverride = null): array
    {
        $secretKey = $this->getSecretKey($configOverride);
        if ($secretKey === '') {
            return ['success' => false, 'error' => 'Flutterwave secret key not configured'];
        }

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->get(self::BASE_URL."/transactions/{$transactionId}/verify");

            if (! $response->successful()) {
                Log::error('Flutterwave verifyTransaction failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => $response->json('message') ?? 'Could not verify transaction'];
            }

            $data = $response->json('data') ?? [];
            $status = strtolower((string) ($data['status'] ?? ''));
            $isPaid = ($status === 'successful');

            return [
                'success' => true,
                'paid' => $isPaid,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Flutterwave verifyTransaction error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not verify Flutterwave transaction'];
        }
    }

    /**
     * Create a Flutterwave payment link for an Order.
     *
     * @return array{success: bool, url?: string, reference?: string, error?: string}
     */
    public function createPaymentLinkForOrder(Order $order, string $callbackUrl, ?array $configOverride = null): array
    {
        if (! self::isEnabled() && empty($configOverride['secret_key'])) {
            return ['success' => false, 'error' => 'Flutterwave is not configured.'];
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Order total must be greater than zero.'];
        }

        $email = $this->resolveOrderCustomerEmail($order);
        $phone = (string) ($order->customer_phone ?? '');
        $name = (string) ($order->customer_name ?? 'Customer');
        $reference = 'essem_flw_'.$order->id.'_'.uniqid();
        $currency = $this->getCurrency($configOverride);

        $result = $this->initializePayment([
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => $callbackUrl,
            'title' => 'Order #'.$order->order_number,
            'description' => 'Payment for Order #'.$order->order_number,
            'customer' => [
                'email' => $email,
                'phonenumber' => $phone,
                'name' => $name,
            ],
        ], $configOverride);

        if (! $result['success'] || empty($result['link'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not create Flutterwave payment link.'];
        }

        return [
            'success' => true,
            'url' => $result['link'],
            'reference' => $result['tx_ref'] ?? $reference,
        ];
    }

    /**
     * Resolve email for order billing.
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
