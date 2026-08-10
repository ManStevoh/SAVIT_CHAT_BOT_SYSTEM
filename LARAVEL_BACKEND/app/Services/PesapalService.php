<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PesapalService
{
    public const CACHE_KEY_SUB_PREFIX = 'pesapal_pending:';

    public const CACHE_KEY_ORDER_PREFIX = 'pesapal_pending_order:';

    protected array $config = [];

    public function __construct()
    {
        $this->config = PaymentGateway::isEnabled('pesapal')
            ? PaymentGateway::getConfig('pesapal')
            : [];
    }

    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('pesapal');
    }

    public function getBaseUrl(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;
        $env = strtolower((string) ($cfg['env'] ?? 'sandbox'));

        if ($env === 'production' || $env === 'live') {
            return 'https://pay.pesapal.com/v3';
        }

        return 'https://cybqa.pesapal.com/pesapalv3';
    }

    public function getCurrency(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return strtoupper((string) ($cfg['currency'] ?? 'KES'));
    }

    /**
     * Request bearer token from Pesapal API v3.
     *
     * @return array{success: bool, token?: string, error?: string}
     */
    public function getAuthToken(?array $configOverride = null): array
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['consumer_key']) ? $configOverride : $this->config;
        $key = (string) ($cfg['consumer_key'] ?? '');
        $secret = (string) ($cfg['consumer_secret'] ?? '');

        if ($key === '' || $secret === '') {
            return ['success' => false, 'error' => 'Pesapal consumer key or secret key not configured'];
        }

        $baseUrl = $this->getBaseUrl($cfg);
        $cacheKey = 'pesapal_auth_token_'.md5($key.$secret.$baseUrl);

        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return ['success' => true, 'token' => $cachedToken];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->post($baseUrl.'/api/Auth/RequestToken', [
                    'consumer_key' => $key,
                    'consumer_secret' => $secret,
                ]);

            if (! $response->successful()) {
                $json = $response->json() ?? [];
                $errCode = $json['error']['code'] ?? $json['code'] ?? null;
                $errMsg = $json['error']['message'] ?? $json['message'] ?? null;

                $msg = ! empty($errMsg) ? $errMsg : (! empty($errCode) ? str_replace('_', ' ', (string) $errCode) : null);

                if ($errCode === 'invalid_consumer_key_or_secret_provided') {
                    $msg = 'Invalid Pesapal Consumer Key or Secret. If using Live credentials, please set Environment Mode to "Production".';
                }

                if (empty($msg)) {
                    $msg = 'Pesapal authentication failed (HTTP '.$response->status().')';
                }

                Log::error('Pesapal Auth/RequestToken failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => (string) $msg];
            }

            $token = $response->json('token');
            if (! is_string($token) || $token === '') {
                return ['success' => false, 'error' => 'Pesapal did not return a valid auth token'];
            }

            // Cache token for 4 minutes (tokens last 5 minutes)
            Cache::put($cacheKey, $token, now()->addMinutes(4));

            return ['success' => true, 'token' => $token];
        } catch (\Throwable $e) {
            Log::error('Pesapal RequestToken error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to Pesapal'];
        }
    }

    /**
     * Register IPN URL with Pesapal.
     *
     * @return array{success: bool, ipn_id?: string, error?: string}
     */
    public function registerIpnUrl(string $ipnUrl, ?array $configOverride = null): array
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['consumer_key']) ? $configOverride : $this->config;
        if (! empty($cfg['ipn_id'])) {
            return ['success' => true, 'ipn_id' => (string) $cfg['ipn_id']];
        }

        $auth = $this->getAuthToken($cfg);
        if (! $auth['success'] || empty($auth['token'])) {
            return ['success' => false, 'error' => $auth['error'] ?? 'Authentication failed'];
        }

        $baseUrl = $this->getBaseUrl($cfg);

        try {
            $response = Http::withToken($auth['token'])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/api/URLSetup/RegisterIPN', [
                    'url' => $ipnUrl,
                    'ipn_notification_type' => 'GET',
                ]);

            if (! $response->successful()) {
                Log::error('Pesapal RegisterIPN failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => $response->json('message') ?? 'Register IPN failed'];
            }

            $ipnId = $response->json('ipn_id');
            if (! is_string($ipnId) || $ipnId === '') {
                return ['success' => false, 'error' => 'Pesapal did not return an IPN ID'];
            }

            return ['success' => true, 'ipn_id' => $ipnId];
        } catch (\Throwable $e) {
            Log::error('Pesapal RegisterIPN error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not register IPN with Pesapal'];
        }
    }

    /**
     * Submit an order request to Pesapal API v3.
     *
     * @param  array<string, mixed>  $orderData
     * @return array{success: bool, redirect_url?: string, order_tracking_id?: string, merchant_reference?: string, error?: string}
     */
    public function submitOrderRequest(array $orderData, ?array $configOverride = null): array
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['consumer_key']) ? $configOverride : $this->config;
        $auth = $this->getAuthToken($cfg);
        if (! $auth['success'] || empty($auth['token'])) {
            return ['success' => false, 'error' => $auth['error'] ?? 'Authentication failed'];
        }

        $ipnId = $orderData['notification_id'] ?? ($cfg['ipn_id'] ?? null);
        if (! $ipnId) {
            $defaultIpnUrl = url('/api/pesapal/ipn');
            $ipnRes = $this->registerIpnUrl($defaultIpnUrl, $cfg);
            if (! $ipnRes['success'] || empty($ipnRes['ipn_id'])) {
                return ['success' => false, 'error' => 'Could not register IPN: '.($ipnRes['error'] ?? 'Unknown error')];
            }
            $ipnId = $ipnRes['ipn_id'];
        }

        $baseUrl = $this->getBaseUrl($cfg);
        $currency = strtoupper((string) ($orderData['currency'] ?? $this->getCurrency($cfg)));
        $amount = (float) ($orderData['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than zero'];
        }

        $merchantReference = (string) ($orderData['id'] ?? ('pesapal_ref_'.uniqid()));
        $callbackUrl = (string) ($orderData['callback_url'] ?? rtrim(config('app.frontend_url', 'http://localhost:3000'), '/').'/orders/payment-complete');
        $billing = $orderData['billing_address'] ?? [];

        $payload = [
            'id' => $merchantReference,
            'currency' => $currency,
            'amount' => $amount,
            'description' => (string) ($orderData['description'] ?? 'Order Payment'),
            'callback_url' => $callbackUrl,
            'notification_id' => $ipnId,
            'billing_address' => [
                'email_address' => (string) ($billing['email_address'] ?? 'customer@essemchat.com'),
                'phone_number' => (string) ($billing['phone_number'] ?? ''),
                'country_code' => (string) ($billing['country_code'] ?? 'KE'),
                'first_name' => (string) ($billing['first_name'] ?? 'Customer'),
                'last_name' => (string) ($billing['last_name'] ?? ''),
            ],
        ];

        try {
            $response = Http::withToken($auth['token'])
                ->acceptJson()
                ->asJson()
                ->post($baseUrl.'/api/Transactions/SubmitOrderRequest', $payload);

            if (! $response->successful()) {
                $msg = $response->json('message') ?? $response->json('error.message') ?? $response->body();
                Log::error('Pesapal SubmitOrderRequest failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => is_string($msg) ? $msg : 'Payment initialization failed'];
            }

            $json = $response->json();
            $redirectUrl = $json['redirect_url'] ?? null;
            $orderTrackingId = $json['order_tracking_id'] ?? null;

            if (! is_string($redirectUrl) || $redirectUrl === '') {
                return ['success' => false, 'error' => $json['error']['message'] ?? 'Pesapal did not return a checkout URL'];
            }

            return [
                'success' => true,
                'redirect_url' => $redirectUrl,
                'order_tracking_id' => $orderTrackingId,
                'merchant_reference' => $merchantReference,
            ];
        } catch (\Throwable $e) {
            Log::error('Pesapal SubmitOrderRequest error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not submit order to Pesapal'];
        }
    }

    /**
     * Query transaction status from Pesapal API v3.
     *
     * @return array{success: bool, paid?: bool, status?: string, data?: array<string, mixed>, error?: string}
     */
    public function getTransactionStatus(string $orderTrackingId, ?array $configOverride = null): array
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['consumer_key']) ? $configOverride : $this->config;
        $auth = $this->getAuthToken($cfg);
        if (! $auth['success'] || empty($auth['token'])) {
            return ['success' => false, 'error' => $auth['error'] ?? 'Authentication failed'];
        }

        $baseUrl = $this->getBaseUrl($cfg);

        try {
            $response = Http::withToken($auth['token'])
                ->acceptJson()
                ->get($baseUrl.'/api/Transactions/GetTransactionStatus', [
                    'orderTrackingId' => $orderTrackingId,
                ]);

            if (! $response->successful()) {
                Log::error('Pesapal GetTransactionStatus failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => $response->json('message') ?? 'Could not check transaction status'];
            }

            $data = $response->json() ?? [];
            $statusDesc = strtolower((string) ($data['payment_status_description'] ?? ''));
            $statusCode = (int) ($data['status_code'] ?? -1);

            $isPaid = ($statusDesc === 'completed') || ($statusCode === 1);

            return [
                'success' => true,
                'paid' => $isPaid,
                'status' => $statusDesc,
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Pesapal GetTransactionStatus error: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not verify transaction status'];
        }
    }

    /**
     * Create a Pesapal payment link for a customer order.
     *
     * @return array{success: bool, url?: string, reference?: string, order_tracking_id?: string, error?: string}
     */
    public function createPaymentLinkForOrder(Order $order, string $callbackUrl, ?array $configOverride = null): array
    {
        if (! self::isEnabled() && empty($configOverride['consumer_key'])) {
            return ['success' => false, 'error' => 'Pesapal is not configured.'];
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Order total must be greater than zero.'];
        }

        $email = $this->resolveOrderCustomerEmail($order);
        $phone = (string) ($order->customer_phone ?? '');
        $name = (string) ($order->customer_name ?? 'Customer');
        $nameParts = explode(' ', trim($name), 2);
        $firstName = $nameParts[0] ?: 'Customer';
        $lastName = $nameParts[1] ?? '';

        $reference = 'essem_ord_'.$order->id.'_'.uniqid();
        $currency = $this->getCurrency($configOverride);

        $result = $this->submitOrderRequest([
            'id' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Order #'.$order->order_number,
            'callback_url' => $callbackUrl,
            'billing_address' => [
                'email_address' => $email,
                'phone_number' => $phone,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'country_code' => 'KE',
            ],
        ], $configOverride);

        if (! $result['success'] || empty($result['redirect_url'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not create Pesapal payment link.'];
        }

        return [
            'success' => true,
            'url' => $result['redirect_url'],
            'reference' => $result['merchant_reference'] ?? $reference,
            'order_tracking_id' => $result['order_tracking_id'] ?? null,
        ];
    }

    /**
     * Resolve email for customer orders (Pesapal billing address requires an email).
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
