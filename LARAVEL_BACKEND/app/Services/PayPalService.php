<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    public const CACHE_KEY_SUB_PREFIX = 'paypal_pending_sub:';

    public const CACHE_KEY_ORDER_PREFIX = 'paypal_pending_order:';

    public const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';

    public const LIVE_URL = 'https://api-m.paypal.com';

    protected array $config = [];

    public function __construct()
    {
        $this->config = PaymentGateway::isEnabled('paypal')
            ? PaymentGateway::getConfig('paypal')
            : [];
    }

    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('paypal');
    }

    public function getCurrency(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return strtoupper((string) ($cfg['currency'] ?? 'USD'));
    }

    public function getEnv(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return strtolower((string) ($cfg['env'] ?? 'sandbox'));
    }

    public function getBaseUrl(?array $configOverride = null): string
    {
        return $this->getEnv($configOverride) === 'production'
            ? self::LIVE_URL
            : self::SANDBOX_URL;
    }

    public function getClientId(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['client_id'])
            ? $configOverride
            : $this->config;

        return (string) ($cfg['client_id'] ?? '');
    }

    public function getClientSecret(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) && ! empty($configOverride['client_secret'])
            ? $configOverride
            : $this->config;

        return (string) ($cfg['client_secret'] ?? '');
    }

    public function getWebhookId(?array $configOverride = null): string
    {
        $cfg = is_array($configOverride) ? $configOverride : $this->config;

        return (string) ($cfg['webhook_id'] ?? '');
    }

    /**
     * Get OAuth2 Bearer Access Token with caching.
     *
     * @return array{success: bool, access_token?: string, error?: string}
     */
    public function getAccessToken(?array $configOverride = null): array
    {
        $clientId = $this->getClientId($configOverride);
        $clientSecret = $this->getClientSecret($configOverride);
        $baseUrl = $this->getBaseUrl($configOverride);

        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'error' => 'PayPal client_id or client_secret is not configured.'];
        }

        $cacheKey = 'paypal_oauth_token_'.md5($clientId.$clientSecret.$baseUrl);
        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && $cachedToken !== '') {
            return ['success' => true, 'access_token' => $cachedToken];
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->acceptJson()
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                $errorMsg = $response->json('error_description') ?? $response->json('error') ?? $response->body();
                Log::error('PayPal OAuth token request failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => is_string($errorMsg) ? $errorMsg : 'Failed to obtain PayPal access token.'];
            }

            $tokenData = $response->json() ?? [];
            $accessToken = $tokenData['access_token'] ?? null;
            $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

            if (! is_string($accessToken) || $accessToken === '') {
                return ['success' => false, 'error' => 'PayPal returned an empty access token.'];
            }

            $ttl = max(60, $expiresIn - 120);
            Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));

            return ['success' => true, 'access_token' => $accessToken];
        } catch (\Throwable $e) {
            Log::error('PayPal getAccessToken exception: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to PayPal OAuth service.'];
        }
    }

    /**
     * Create an order on PayPal API v2.
     *
     * @param  array<string, mixed>  $orderPayload
     * @return array{success: bool, id?: string, approve_url?: string, data?: array<string, mixed>, error?: string}
     */
    public function createOrder(array $orderPayload, ?array $configOverride = null): array
    {
        $tokenRes = $this->getAccessToken($configOverride);
        if (! ($tokenRes['success'] ?? false) || empty($tokenRes['access_token'])) {
            return ['success' => false, 'error' => $tokenRes['error'] ?? 'PayPal authentication failed.'];
        }

        $baseUrl = $this->getBaseUrl($configOverride);
        $accessToken = $tokenRes['access_token'];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->post("{$baseUrl}/v2/checkout/orders", $orderPayload);

            if (! $response->successful()) {
                $msg = $response->json('message') ?? $response->json('details.0.description') ?? $response->body();
                Log::error('PayPal createOrder failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => is_string($msg) ? $msg : 'Failed to create PayPal order.'];
            }

            $json = $response->json() ?? [];
            $paypalOrderId = $json['id'] ?? null;
            $links = is_array($json['links'] ?? null) ? $json['links'] : [];
            $approveUrl = null;

            foreach ($links as $link) {
                if (($link['rel'] ?? '') === 'approve') {
                    $approveUrl = $link['href'] ?? null;
                    break;
                }
            }

            if (! $paypalOrderId || ! $approveUrl) {
                return ['success' => false, 'error' => 'PayPal did not return an approval link.'];
            }

            return [
                'success' => true,
                'id' => $paypalOrderId,
                'approve_url' => $approveUrl,
                'data' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal createOrder exception: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to PayPal checkout service.'];
        }
    }

    /**
     * Capture payment for an approved PayPal order.
     *
     * @return array{success: bool, paid?: bool, data?: array<string, mixed>, error?: string}
     */
    public function captureOrder(string $paypalOrderId, ?array $configOverride = null): array
    {
        $paypalOrderId = trim($paypalOrderId);
        if ($paypalOrderId === '') {
            return ['success' => false, 'error' => 'Missing PayPal order ID.'];
        }

        $tokenRes = $this->getAccessToken($configOverride);
        if (! ($tokenRes['success'] ?? false) || empty($tokenRes['access_token'])) {
            return ['success' => false, 'error' => $tokenRes['error'] ?? 'PayPal authentication failed.'];
        }

        $baseUrl = $this->getBaseUrl($configOverride);
        $accessToken = $tokenRes['access_token'];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture", (object) []);

            $json = $response->json() ?? [];
            $status = strtoupper((string) ($json['status'] ?? ''));

            if (! $response->successful()) {
                // If already captured, verify with GET
                if ($response->status() === 422 && str_contains(json_encode($json), 'ORDER_ALREADY_CAPTURED')) {
                    return $this->getOrderDetails($paypalOrderId, $configOverride);
                }

                $msg = $json['message'] ?? $json['details'][0]['description'] ?? $response->body();
                Log::error('PayPal captureOrder failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => is_string($msg) ? $msg : 'PayPal payment capture failed.'];
            }

            $isPaid = ($status === 'COMPLETED');

            return [
                'success' => true,
                'paid' => $isPaid,
                'data' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal captureOrder exception: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to PayPal capture service.'];
        }
    }

    /**
     * Get details of a PayPal order.
     *
     * @return array{success: bool, paid?: bool, data?: array<string, mixed>, error?: string}
     */
    public function getOrderDetails(string $paypalOrderId, ?array $configOverride = null): array
    {
        $paypalOrderId = trim($paypalOrderId);
        if ($paypalOrderId === '') {
            return ['success' => false, 'error' => 'Missing PayPal order ID.'];
        }

        $tokenRes = $this->getAccessToken($configOverride);
        if (! ($tokenRes['success'] ?? false) || empty($tokenRes['access_token'])) {
            return ['success' => false, 'error' => $tokenRes['error'] ?? 'PayPal authentication failed.'];
        }

        $baseUrl = $this->getBaseUrl($configOverride);
        $accessToken = $tokenRes['access_token'];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get("{$baseUrl}/v2/checkout/orders/{$paypalOrderId}");

            if (! $response->successful()) {
                Log::error('PayPal getOrderDetails failed', ['status' => $response->status(), 'body' => $response->body()]);

                return ['success' => false, 'error' => $response->json('message') ?? 'Could not retrieve PayPal order details.'];
            }

            $json = $response->json() ?? [];
            $status = strtoupper((string) ($json['status'] ?? ''));
            $isPaid = ($status === 'COMPLETED');

            return [
                'success' => true,
                'paid' => $isPaid,
                'data' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('PayPal getOrderDetails exception: '.$e->getMessage());

            return ['success' => false, 'error' => 'Could not connect to PayPal order details service.'];
        }
    }

    /**
     * Create a PayPal payment link for a customer Storefront Order.
     *
     * @return array{success: bool, url?: string, reference?: string, paypal_order_id?: string, error?: string}
     */
    public function createPaymentLinkForOrder(Order $order, string $callbackUrl, ?array $configOverride = null): array
    {
        if (! self::isEnabled() && empty($configOverride['client_id'])) {
            return ['success' => false, 'error' => 'PayPal is not configured.'];
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Order total must be greater than zero.'];
        }

        $currency = $this->getCurrency($configOverride);
        $reference = 'essem_pp_'.$order->id.'_'.uniqid();
        $brandName = $order->company?->name ?? 'Order Checkout';

        $cancelUrl = $order->pay_token
            ? url("/pay/{$order->pay_token}?change=1")
            : url('/orders/payment-complete?cancelled=1');

        $returnUrl = $callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').'reference='.urlencode($reference);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $reference,
                    'description' => 'Payment for Order #'.$order->order_number,
                    'custom_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => mb_substr($brandName, 0, 127),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $res = $this->createOrder($payload, $configOverride);
        if (! ($res['success'] ?? false) || empty($res['approve_url'])) {
            return ['success' => false, 'error' => $res['error'] ?? 'Could not create PayPal checkout session.'];
        }

        return [
            'success' => true,
            'url' => $res['approve_url'],
            'reference' => $reference,
            'paypal_order_id' => $res['id'] ?? null,
        ];
    }

    /**
     * Create a PayPal checkout session for a platform plan subscription.
     */
    public function createCheckoutSession(Company $company, Plan $plan): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $amount = (float) ($plan->price_amount ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $currency = $this->getCurrency();
        $reference = 'sub_pp_'.$company->id.'_'.$plan->id.'_'.uniqid();

        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $returnUrl = url('/api/paypal/callback?flow=subscription&reference='.urlencode($reference).'&plan_id='.$plan->id.'&company_id='.$company->id);
        $cancelUrl = $frontendUrl.'/dashboard/subscription?checkout=cancelled';

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $reference,
                    'description' => 'Subscription: '.$plan->name.' for '.$company->name,
                    'custom_id' => 'company_'.$company->id.'_plan_'.$plan->id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ],
            ],
            'application_context' => [
                'brand_name' => config('app.name', 'RelayIQ'),
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $res = $this->createOrder($payload);
        if (! ($res['success'] ?? false) || empty($res['approve_url'])) {
            Log::error('PayPal subscription checkout creation failed', ['error' => $res['error'] ?? null]);

            return null;
        }

        Cache::put(self::CACHE_KEY_SUB_PREFIX.$reference, [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'amount' => $amount,
            'currency' => $currency,
            'paypal_order_id' => $res['id'] ?? null,
        ], now()->addMinutes(60));

        return $res['approve_url'];
    }

    /**
     * Resolve email for customer order checkout.
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
