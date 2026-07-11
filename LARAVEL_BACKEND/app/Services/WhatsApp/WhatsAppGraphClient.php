<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppGraphClient
{
    public function get(string $path, string $accessToken, array $query = []): array
    {
        $url = WhatsAppPlatformConfig::graphUrl() . '/' . ltrim($path, '/');

        $response = Http::withToken($accessToken)->timeout(25)->get($url, $query);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json() ?? [],
            'body' => $response->body(),
        ];
    }

    public function post(string $path, string $accessToken, array $body = []): array
    {
        $url = WhatsAppPlatformConfig::graphUrl() . '/' . ltrim($path, '/');

        $response = Http::withToken($accessToken)->timeout(25)->post($url, $body);

        return $this->formatResponse($response->status(), $response->json(), $response->body());
    }

    /**
     * POST with query-string parameters (Meta credit-sharing endpoints use this pattern).
     */
    public function postWithQuery(string $path, string $accessToken, array $query = []): array
    {
        $url = WhatsAppPlatformConfig::graphUrl() . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $response = Http::withToken($accessToken)->timeout(25)->post($url);

        return $this->formatResponse($response->status(), $response->json(), $response->body());
    }

    public function delete(string $path, string $accessToken): array
    {
        $url = WhatsAppPlatformConfig::graphUrl() . '/' . ltrim($path, '/');

        $response = Http::withToken($accessToken)->timeout(25)->delete($url);

        return $this->formatResponse($response->status(), $response->json(), $response->body());
    }

    public function exchangeCodeForToken(string $code): ?string
    {
        $appId = WhatsAppPlatformConfig::embeddedAppId();
        $appSecret = WhatsAppPlatformConfig::embeddedAppSecret();
        $redirectUri = WhatsAppPlatformConfig::embeddedRedirectUri();

        if ($appId === '' || $appSecret === '' || $redirectUri === '') {
            return null;
        }

        try {
            $response = Http::timeout(25)->get(WhatsAppPlatformConfig::graphUrl() . '/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

            if (! $response->successful()) {
                Log::warning('WhatsApp token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::warning('WhatsApp token exchange error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function verifyPhoneNumber(string $phoneNumberId, string $accessToken): ?array
    {
        $result = $this->get($phoneNumberId, $accessToken, [
            'fields' => 'id,display_phone_number,verified_name,quality_rating',
        ]);

        if (! $result['ok'] || ! is_array($result['data'])) {
            return null;
        }

        return $result['data'];
    }

    public function discoverPhoneData(string $accessToken): array
    {
        $result = $this->get('me/whatsapp_business_accounts', $accessToken, [
            'fields' => 'id,name,phone_numbers{id,display_phone_number,verified_name,quality_rating}',
            'limit' => 1,
        ]);

        if (! $result['ok']) {
            return [];
        }

        $firstWaba = $result['data']['data'][0] ?? null;
        if (! is_array($firstWaba)) {
            return [];
        }

        $phones = $firstWaba['phone_numbers']['data'] ?? $firstWaba['phone_numbers'] ?? [];
        $firstPhone = is_array($phones) ? ($phones[0] ?? null) : null;

        return [
            'whatsappBusinessAccountId' => $firstWaba['id'] ?? null,
            'phoneNumberId' => is_array($firstPhone) ? ($firstPhone['id'] ?? null) : null,
            'displayPhoneNumber' => is_array($firstPhone) ? ($firstPhone['display_phone_number'] ?? null) : null,
            'qualityRating' => is_array($firstPhone) ? ($firstPhone['quality_rating'] ?? null) : null,
        ];
    }

    public function subscribeWabaWebhooks(string $wabaId, string $accessToken): array
    {
        $verifyToken = WhatsAppPlatformConfig::webhookVerifyToken();
        $callbackUrl = WhatsAppPlatformConfig::webhookCallbackUrl();

        $body = [];
        if ($verifyToken !== '' && $callbackUrl !== '') {
            $body['override_callback_uri'] = $callbackUrl;
            $body['verify_token'] = $verifyToken;
        }

        return $this->post("{$wabaId}/subscribed_apps", $accessToken, $body);
    }

    public function unsubscribeWabaWebhooks(string $wabaId, string $accessToken): array
    {
        return $this->delete("{$wabaId}/subscribed_apps", $accessToken);
    }

    public function registerPhoneNumber(string $phoneNumberId, string $accessToken, string $pin): array
    {
        return $this->post("{$phoneNumberId}/register", $accessToken, [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
    }

    public function getWabaOwnerBusinessId(string $wabaId, string $accessToken): ?string
    {
        $result = $this->get($wabaId, $accessToken, [
            'fields' => 'owner_business_info',
        ]);

        if (! $result['ok']) {
            return null;
        }

        $owner = $result['data']['owner_business_info'] ?? null;

        return is_array($owner) ? (string) ($owner['id'] ?? '') ?: null : null;
    }

    public function isAlreadyRegisteredError(array $result): bool
    {
        $message = strtolower((string) ($result['data']['error']['message'] ?? ''));
        $code = (int) ($result['data']['error']['code'] ?? 0);

        return $code === 133015
            || str_contains($message, 'already registered')
            || str_contains($message, 'already exists');
    }

    public function isAlreadySubscribedError(array $result): bool
    {
        $message = strtolower((string) ($result['data']['error']['message'] ?? ''));

        return str_contains($message, 'already subscribed');
    }

    protected function formatResponse(int $status, mixed $json, string $body): array
    {
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => is_array($json) ? $json : [],
            'body' => $body,
        ];
    }
}
