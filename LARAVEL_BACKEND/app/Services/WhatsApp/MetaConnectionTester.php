<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Diagnose platform Meta / WhatsApp Embedded Signup credentials against Graph API.
 */
class MetaConnectionTester
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     success: bool,
     *     message: string,
     *     failedStep: string|null,
     *     details: array<string, mixed>
     * }
     */
    public function test(array $input): array
    {
        $started = microtime(true);
        $appId = $this->resolveString($input['whatsappEmbeddedAppId'] ?? null, 'whatsapp_embedded_app_id', (string) config('whatsapp.embedded_signup_app_id', ''));
        $configId = $this->resolveString($input['whatsappEmbeddedConfigId'] ?? null, 'whatsapp_embedded_config_id', (string) config('whatsapp.embedded_signup_config_id', ''));
        $embeddedSecret = $this->resolveSecret($input['whatsappEmbeddedAppSecret'] ?? null, 'whatsapp_embedded_app_secret', (string) config('whatsapp.embedded_signup_app_secret', ''));
        $webhookSecret = $this->resolveSecret($input['metaAppSecret'] ?? null, 'meta_app_secret', (string) config('whatsapp.app_secret', ''));
        $verifyToken = $this->resolveString($input['whatsappWebhookVerifyToken'] ?? null, 'whatsapp_webhook_verify_token', (string) config('whatsapp.webhook_verify_token', ''));
        $redirectUri = $this->resolveString($input['whatsappEmbeddedRedirectUri'] ?? null, 'whatsapp_embedded_redirect_uri', '');
        $creditToken = $this->resolveSecret($input['whatsappCreditSharingSystemToken'] ?? null, 'whatsapp_credit_sharing_system_token', '');

        $checks = [
            $this->check('app_id', 'Meta App ID', 'whatsappEmbeddedAppId'),
            $this->check('embedded_secret', 'Meta App Secret (token exchange)', 'whatsappEmbeddedAppSecret'),
            $this->check('webhook_secret', 'Meta App Secret (webhooks)', 'metaAppSecret'),
            $this->check('config_id', 'Embedded Signup Config ID', 'whatsappEmbeddedConfigId'),
            $this->check('verify_token', 'Webhook verify token', 'whatsappWebhookVerifyToken'),
            $this->check('redirect_uri', 'OAuth redirect URI', 'whatsappEmbeddedRedirectUri'),
            $this->check('credit_token', 'Solution Partner system token', 'whatsappCreditSharingSystemToken'),
        ];

        if ($appId === '') {
            return $this->finish(false, 'Meta App ID is missing.', 'app_id', $this->mark($checks, 'app_id', 'failed', 'Required.'), $started, 'Paste the App ID from Meta App Dashboard → Settings → Basic.', failedCheck: 'app_id');
        }

        if (! ctype_digit($appId)) {
            return $this->finish(false, 'Meta App ID must be numeric.', 'app_id', $this->mark($checks, 'app_id', 'failed', 'Must be digits only.'), $started, 'Copy the App ID digits only — not the app name.', failedCheck: 'app_id');
        }

        if ($embeddedSecret === '') {
            return $this->finish(false, 'Meta App Secret for token exchange is missing.', 'embedded_secret', $this->mark($checks, 'embedded_secret', 'failed', 'Required.'), $started, 'Paste the App Secret from the same Meta app (Settings → Basic). Leave the field blank only if a secret is already saved.', failedCheck: 'embedded_secret');
        }

        try {
            $tokenResponse = Http::acceptJson()
                ->timeout(20)
                ->get('https://graph.facebook.com/oauth/access_token', [
                    'client_id' => $appId,
                    'client_secret' => $embeddedSecret,
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException|Throwable $e) {
            return $this->finish(
                false,
                'Could not reach graph.facebook.com.',
                'network',
                $this->mark($checks, 'embedded_secret', 'failed', $e->getMessage()),
                $started,
                'Check outbound HTTPS from this server (firewall, DNS, or proxy).',
                graphError: $e->getMessage(),
                failedCheck: 'embedded_secret',
            );
        }

        $graphError = $this->parseGraphError($tokenResponse);
        if ($graphError !== null || ! $tokenResponse->successful()) {
            $classified = $this->classifyOAuthError($tokenResponse->status(), $graphError);

            return $this->finish(
                false,
                $classified['message'],
                $classified['step'],
                $this->mark($checks, $classified['check'], 'failed', $graphError['message'] ?? ('HTTP '.$tokenResponse->status())),
                $started,
                $classified['hint'],
                httpStatus: $tokenResponse->status(),
                graphError: $graphError['message'] ?? null,
                graphCode: $graphError['code'] ?? null,
                failedCheck: $classified['check'],
            );
        }

        $appToken = (string) ($tokenResponse->json('access_token') ?? '');
        if ($appToken === '') {
            return $this->finish(false, 'Meta accepted the request but returned no access token.', 'embedded_secret', $this->mark($checks, 'embedded_secret', 'failed'), $started, 'Retry, or recreate the App Secret in Meta.', failedCheck: 'embedded_secret');
        }

        $checks = $this->mark($checks, 'app_id', 'passed');
        $checks = $this->mark($checks, 'embedded_secret', 'passed', 'App ID and token-exchange secret match.');

        $appName = null;
        try {
            $appResponse = Http::acceptJson()
                ->timeout(20)
                ->get(WhatsAppPlatformConfig::graphUrl().'/'.$appId, [
                    'fields' => 'id,name',
                    'access_token' => $appToken,
                ]);
            if ($appResponse->successful()) {
                $appName = $appResponse->json('name');
                $appName = is_string($appName) && $appName !== '' ? $appName : null;
            }
        } catch (Throwable) {
            // App token already proved the pair; name is optional.
        }

        if ($webhookSecret === '') {
            $checks = $this->mark($checks, 'webhook_secret', 'failed', 'Missing. Inbound webhook signatures cannot be verified.');
        } elseif (hash_equals($embeddedSecret, $webhookSecret)) {
            $checks = $this->mark($checks, 'webhook_secret', 'passed', 'Same secret as token exchange — valid for this App ID.');
        } else {
            try {
                $webhookTokenResponse = Http::acceptJson()
                    ->timeout(20)
                    ->get('https://graph.facebook.com/oauth/access_token', [
                        'client_id' => $appId,
                        'client_secret' => $webhookSecret,
                        'grant_type' => 'client_credentials',
                    ]);
            } catch (Throwable $e) {
                $checks = $this->mark($checks, 'webhook_secret', 'failed', $e->getMessage());
                $webhookTokenResponse = null;
            }

            if ($webhookTokenResponse && $webhookTokenResponse->successful() && $webhookTokenResponse->json('access_token')) {
                $checks = $this->mark($checks, 'webhook_secret', 'passed', 'Matches this App ID (different value from the token-exchange secret).');
            } else {
                $err = $webhookTokenResponse ? $this->parseGraphError($webhookTokenResponse) : null;
                $checks = $this->mark(
                    $checks,
                    'webhook_secret',
                    'failed',
                    $err['message'] ?? 'This secret does not match Meta App ID. Use the App Secret from the same app.'
                );
            }
        }

        if ($configId === '') {
            $checks = $this->mark($checks, 'config_id', 'failed', 'Missing. Companies cannot start Embedded Signup without it.');
        } elseif (! ctype_digit($configId)) {
            $checks = $this->mark($checks, 'config_id', 'failed', 'Must be numeric (from Embedded Signup configuration).');
        } else {
            try {
                $configResponse = Http::acceptJson()
                    ->timeout(20)
                    ->get(WhatsAppPlatformConfig::graphUrl().'/'.$configId, [
                        'access_token' => $appToken,
                    ]);
                $configError = $this->parseGraphError($configResponse);
                if ($configResponse->successful() && ! $configError) {
                    $checks = $this->mark($checks, 'config_id', 'passed');
                } elseif ($this->isUnverifiableConfigError($configError)) {
                    $checks = $this->mark($checks, 'config_id', 'skipped', 'Graph cannot inspect this Config ID from the server. Confirm it in Meta App → WhatsApp → Embedded Signup.');
                } else {
                    $checks = $this->mark($checks, 'config_id', 'failed', $configError['message'] ?? ('HTTP '.$configResponse->status()));
                }
            } catch (Throwable $e) {
                $checks = $this->mark($checks, 'config_id', 'skipped', 'Could not reach Graph to inspect Config ID: '.$e->getMessage());
            }
        }

        if ($verifyToken === '') {
            $checks = $this->mark($checks, 'verify_token', 'failed', 'Missing. Meta webhook verification will fail.');
        } else {
            $verifyRequest = Request::create('/api/whatsapp/webhook', 'GET', [
                'hub_mode' => 'subscribe',
                'hub_verify_token' => $verifyToken,
                'hub_challenge' => 'relayiq-meta-test',
            ]);
            $verifyResponse = app(WhatsAppWebhookController::class)->verify($verifyRequest);
            $ok = $verifyResponse->getStatusCode() === 200 && $verifyResponse->getContent() === 'relayiq-meta-test';
            if ($ok) {
                $checks = $this->mark($checks, 'verify_token', 'passed', 'This server would accept this token on GET /api/whatsapp/webhook. Meta Dashboard must use the exact same string.');
            } else {
                $saved = WhatsAppPlatformConfig::webhookVerifyToken();
                $hint = ($verifyToken !== $saved && $saved !== '')
                    ? 'This value is not the token currently saved. Click Save Integrations first, or Meta is still using the previous token.'
                    : 'This token would be rejected by RelayIQ’s webhook handshake.';
                $checks = $this->mark($checks, 'verify_token', 'failed', $hint);
            }
        }

        if ($redirectUri === '') {
            $checks = $this->mark($checks, 'redirect_uri', 'skipped', 'Empty — RelayIQ will fall back to '.rtrim((string) config('app.url'), '/').'/dashboard/settings');
        } else {
            $parsed = parse_url($redirectUri);
            $scheme = is_array($parsed) ? ($parsed['scheme'] ?? '') : '';
            $host = is_array($parsed) ? ($parsed['host'] ?? '') : '';
            if (! is_array($parsed) || $host === '' || ! in_array($scheme, ['https', 'http'], true)) {
                $checks = $this->mark($checks, 'redirect_uri', 'failed', 'Must be a full URL such as https://relayiq.app/dashboard/settings');
            } elseif ($scheme !== 'https' && ! in_array($host, ['localhost', '127.0.0.1'], true)) {
                $checks = $this->mark($checks, 'redirect_uri', 'failed', 'Meta requires HTTPS except on localhost.');
            } else {
                $checks = $this->mark($checks, 'redirect_uri', 'passed');
            }
        }

        if ($creditToken === '') {
            $checks = $this->mark($checks, 'credit_token', 'skipped', 'Not set — only required for Solution Partner billing.');
        } else {
            try {
                $me = Http::acceptJson()
                    ->timeout(20)
                    ->get(WhatsAppPlatformConfig::graphUrl().'/me', [
                        'access_token' => $creditToken,
                    ]);
                $meError = $this->parseGraphError($me);
                if ($me->successful() && ! $meError) {
                    $who = $me->json('name') ?: $me->json('id');
                    $checks = $this->mark($checks, 'credit_token', 'passed', 'Token is valid'.(is_string($who) && $who !== '' ? " ({$who})" : '').'.');
                } else {
                    $checks = $this->mark($checks, 'credit_token', 'failed', $meError['message'] ?? ('HTTP '.$me->status()));
                }
            } catch (Throwable $e) {
                $checks = $this->mark($checks, 'credit_token', 'failed', $e->getMessage());
            }
        }

        $failed = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] === 'failed'));
        $success = $failed === [];
        $firstFail = $failed[0] ?? null;
        $message = $success
            ? ('Meta App ID and token-exchange secret are valid'.($appName ? " (app: {$appName})" : '').'.')
            : ('Meta rejected or RelayIQ could not verify: '.$firstFail['label'].'.');

        $hint = $success
            ? 'Config ID and webhook verify token still have to match Meta’s dashboard exactly when companies connect.'
            : ($firstFail['detail'] ?? 'Fix the failed field below, save, and test again.');

        return $this->finish(
            $success,
            $message,
            $firstFail['id'] ?? null,
            $checks,
            $started,
            $hint,
            appName: $appName,
            failedCheck: $firstFail['id'] ?? null,
        );
    }

    /**
     * @return array{message: string, code: ?int}|null
     */
    private function parseGraphError(\Illuminate\Http\Client\Response $response): ?array
    {
        $json = $response->json();
        $error = is_array($json['error'] ?? null) ? $json['error'] : null;
        if (! is_array($error)) {
            return null;
        }

        $message = $error['message'] ?? null;
        $code = $error['code'] ?? null;

        return [
            'message' => is_string($message) && $message !== '' ? $message : 'Graph API error',
            'code' => is_numeric($code) ? (int) $code : null,
        ];
    }

    /**
     * @param  array{message: string, code: ?int}|null  $error
     * @return array{step: string, check: string, message: string, hint: string}
     */
    private function classifyOAuthError(int $status, ?array $error): array
    {
        $message = strtolower($error['message'] ?? '');
        $code = $error['code'] ?? null;

        if ($code === 101 || str_contains($message, 'invalid application')) {
            return [
                'step' => 'app_id',
                'check' => 'app_id',
                'message' => 'Meta rejected the App ID.',
                'hint' => 'Check Meta App ID (Embedded Signup). It must be the numeric ID from Settings → Basic, not a WABA or phone number ID.',
            ];
        }

        if (str_contains($message, 'client secret') || str_contains($message, 'app secret')) {
            return [
                'step' => 'embedded_secret',
                'check' => 'embedded_secret',
                'message' => 'Meta rejected the App Secret used for token exchange.',
                'hint' => 'Open Meta App → Settings → Basic → App Secret. Paste the current secret (reset it in Meta if you are unsure). Do not use a webhook verify token here.',
            ];
        }

        return [
            'step' => 'embedded_secret',
            'check' => 'embedded_secret',
            'message' => $error['message'] ?? ('Meta OAuth failed with HTTP '.$status),
            'hint' => 'App ID and App Secret must belong to the same Meta app.',
        ];
    }

    /**
     * @param  array{message: string, code: ?int}|null  $error
     */
    private function isUnverifiableConfigError(?array $error): bool
    {
        if ($error === null) {
            return false;
        }

        $message = strtolower($error['message']);

        return str_contains($message, 'unsupported get request')
            || str_contains($message, 'does not support this operation')
            || ($error['code'] === 100 && str_contains($message, 'nonexisting field'));
    }

    private function resolveString(mixed $provided, string $column, string $fallback): string
    {
        if (is_string($provided) && $provided !== '' && $provided !== '********') {
            return trim($provided);
        }

        $saved = PlatformSetting::query()->value($column);
        if (is_string($saved) && trim($saved) !== '') {
            return trim($saved);
        }

        return trim($fallback);
    }

    private function resolveSecret(mixed $provided, string $column, string $fallback): string
    {
        return $this->resolveString($provided, $column, $fallback);
    }

    /**
     * @return array{id: string, label: string, field: string, status: string, detail: ?string}
     */
    private function check(string $id, string $label, string $field): array
    {
        return ['id' => $id, 'label' => $label, 'field' => $field, 'status' => 'skipped', 'detail' => null];
    }

    /**
     * @param  array<int, array{id: string, label: string, field: string, status: string, detail: ?string}>  $checks
     * @return array<int, array{id: string, label: string, field: string, status: string, detail: ?string}>
     */
    private function mark(array $checks, string $id, string $status, ?string $detail = null): array
    {
        return array_map(
            static function (array $check) use ($id, $status, $detail): array {
                if ($check['id'] !== $id) {
                    return $check;
                }

                return [...$check, 'status' => $status, 'detail' => $detail];
            },
            $checks
        );
    }

    /**
     * @param  array<int, array{id: string, label: string, field: string, status: string, detail: ?string}>  $checks
     * @return array{success: bool, message: string, failedStep: string|null, details: array<string, mixed>}
     */
    private function finish(
        bool $success,
        string $message,
        ?string $failedStep,
        array $checks,
        float $started,
        ?string $hint = null,
        ?int $httpStatus = null,
        ?string $graphError = null,
        ?int $graphCode = null,
        ?string $appName = null,
        ?string $failedCheck = null,
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'failedStep' => $failedStep,
            'details' => [
                'httpStatus' => $httpStatus,
                'graphError' => $graphError,
                'graphCode' => $graphCode,
                'appName' => $appName,
                'latencyMs' => (int) round((microtime(true) - $started) * 1000),
                'hint' => $hint,
                'failedCheck' => $failedCheck,
                'checks' => $checks,
            ],
        ];
    }
}
