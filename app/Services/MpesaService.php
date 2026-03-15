<?php

namespace App\Services;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected array $config = [];

    public function __construct()
    {
        $this->config = $this->getConfig();
    }

    protected function getConfig(): array
    {
        if (! PaymentGateway::isEnabled('mpesa')) {
            return [];
        }

        return PaymentGateway::getConfig('mpesa');
    }


    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('mpesa');
    }

    /**
     * Get OAuth access token from Daraja API.
     */
    public function getAccessToken(): ?string
    {
        $consumerKey = $this->config['consumer_key'] ?? '';
        $consumerSecret = $this->config['consumer_secret'] ?? '';
        if (! $consumerKey || ! $consumerSecret) {
            Log::warning('M-Pesa: consumer key or secret not configured');

            return null;
        }

        $baseUrl = $this->baseUrl();
        $url = $baseUrl.'/oauth/v1/generate?grant_type=client_credentials';

        try {
            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->get($url);

            if (! $response->successful()) {
                Log::error('M-Pesa OAuth failed', ['status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $data = $response->json();
            return $data['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::error('M-Pesa OAuth error: '.$e->getMessage());

            return null;
        }
    }

    protected function baseUrl(): string
    {
        $env = $this->config['env'] ?? 'sandbox';

        return $env === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Initiate STK push (Lipa Na M-Pesa Online).
     * Phone must be in format 254XXXXXXXXX (no +).
     *
     * @return array{CheckoutRequestID?: string, ResponseCode?: string, ResponseDescription?: string, MerchantRequestID?: string, error?: string}
     */
    public function stkPush(string $phone, float $amount, string $accountReference, string $transactionDesc, string $callbackUrl): array
    {
        $shortcode = $this->config['shortcode'] ?? '';
        $passkey = $this->config['passkey'] ?? '';
        if (! $shortcode || ! $passkey) {
            return ['error' => 'M-Pesa shortcode or passkey not configured'];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return ['error' => 'Could not obtain M-Pesa access token'];
        }

        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            $phone = '254'.$phone;
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        }

        $payload = [
            'BusinessShortcode' => (int) $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round($amount),
            'PartyA' => (int) $phone,
            'PartyB' => (int) $shortcode,
            'PhoneNumber' => (int) $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => substr($accountReference, 0, 12),
            'TransactionDesc' => substr($transactionDesc, 0, 13),
        ];

        $url = $this->baseUrl().'/mpesa/stkpush/v1/processrequest';

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            $body = $response->json();
            if (! $response->successful()) {
                Log::error('M-Pesa STK push failed', ['status' => $response->status(), 'body' => $body]);
                return [
                    'error' => $body['errorMessage'] ?? $body['error'] ?? 'STK push request failed',
                    'ResponseCode' => (string) ($body['errorCode'] ?? $response->status()),
                ];
            }

            return [
                'CheckoutRequestID' => $body['CheckoutRequestID'] ?? null,
                'MerchantRequestID' => $body['MerchantRequestID'] ?? null,
                'ResponseCode' => (string) ($body['ResponseCode'] ?? '0'),
                'ResponseDescription' => $body['ResponseDescription'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('M-Pesa STK push error: '.$e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
