<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_show_returns_ok(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonStructure(['platformName', 'whatsappManualConnectEnabled', 'aiLearningConfig']);
    }

    public function test_admin_settings_show_survives_invalid_json_columns(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        DB::table('platform_settings')->where('id', $settings->id)->update([
            'ai_learning_config' => '{not-json',
            'landing_trusted_companies' => '{not-json',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('platformName', 'RelayIQ');
    }

    public function test_admin_settings_update_does_not_overwrite_secrets_with_mask(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'whatsapp_webhook_verify_token' => 'real-verify-token',
            'meta_app_secret' => 'real-meta-secret',
            'whatsapp_embedded_app_secret' => 'real-embedded-secret',
        ])->save();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->putJson('/api/admin/settings', [
            'whatsappWebhookVerifyToken' => '********',
            'metaAppSecret' => '********',
            'whatsappEmbeddedAppSecret' => '********',
            'whatsappEmbeddedAppId' => '846055524940193',
        ])->assertOk()->assertJsonPath('success', true);

        $settings->refresh();
        $this->assertSame('real-verify-token', $settings->getRawOriginal('whatsapp_webhook_verify_token'));
        $this->assertSame('real-meta-secret', $settings->getRawOriginal('meta_app_secret'));
        $this->assertSame('real-embedded-secret', $settings->getRawOriginal('whatsapp_embedded_app_secret'));
        $this->assertSame('846055524940193', $settings->whatsapp_embedded_app_id);
    }

    public function test_admin_can_upload_logo_and_favicon(): void
    {
        PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $logo = \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 80);
        $favicon = \Illuminate\Http\UploadedFile::fake()->image('favicon.png', 64, 64);

        $this->post('/api/admin/settings', [
            'primaryColor' => '#6D28D9',
            'logo' => $logo,
            'favicon' => $favicon,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $settings = PlatformSetting::first();
        $this->assertNotNull($settings?->app_logo);
        $this->assertNotNull($settings?->app_favicon);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($settings->app_logo));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($settings->app_favicon));

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('primaryColor', '#6D28D9')
            ->assertJsonStructure(['appLogo', 'appFavicon']);
    }

    public function test_openai_connection_reports_missing_key(): void
    {
        config(['openai.api_key' => '']);
        $this->actingAsAdmin();

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiModel' => 'gpt-4o-mini',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'missing_key')
            ->assertJsonPath('details.checks.0.id', 'api_key')
            ->assertJsonPath('details.checks.0.status', 'failed');
    }

    public function test_openai_connection_reports_invalid_key(): void
    {
        $this->actingAsAdmin();

        Http::fake([
            'api.openai.com/v1/models/*' => Http::response([
                'error' => [
                    'message' => 'Incorrect API key provided: sk-bad.',
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401),
        ]);

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => 'sk-bad',
            'openaiModel' => 'gpt-4o-mini',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'authentication')
            ->assertJsonPath('details.httpStatus', 401)
            ->assertJsonPath('details.openaiCode', 'invalid_api_key')
            ->assertJsonPath('details.checks.0.status', 'failed');
    }

    public function test_openai_connection_reports_unknown_model(): void
    {
        $this->actingAsAdmin();

        Http::fake([
            'api.openai.com/v1/models/*' => Http::response([
                'error' => [
                    'message' => 'The model `not-a-real-model` does not exist',
                    'type' => 'invalid_request_error',
                    'code' => 'model_not_found',
                ],
            ], 404),
        ]);

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => 'sk-valid',
            'openaiModel' => 'not-a-real-model',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'model')
            ->assertJsonPath('details.httpStatus', 404)
            ->assertJsonPath('details.checks.0.status', 'passed')
            ->assertJsonPath('details.checks.1.status', 'failed');
    }

    public function test_openai_connection_reports_quota_on_completion(): void
    {
        $this->actingAsAdmin();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['id' => 'gpt-4o-mini', 'object' => 'model'], 200);
            }

            return Http::response([
                'error' => [
                    'message' => 'You exceeded your current quota.',
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                ],
            ], 429);
        });

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => 'sk-valid',
            'openaiModel' => 'gpt-4o-mini',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'quota')
            ->assertJsonPath('details.openaiCode', 'insufficient_quota')
            ->assertJsonPath('details.checks.0.status', 'passed')
            ->assertJsonPath('details.checks.1.status', 'passed')
            ->assertJsonPath('details.checks.2.status', 'failed');
    }

    public function test_openai_connection_reports_network_failure(): void
    {
        $this->actingAsAdmin();

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Connection timed out');
        });

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => 'sk-valid',
            'openaiModel' => 'gpt-4o-mini',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'timeout')
            ->assertJsonPath('details.checks.0.status', 'failed');
    }

    public function test_openai_connection_succeeds_and_uses_saved_key_when_masked(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'openai_api_key' => 'sk-saved-key',
            'openai_model' => 'gpt-4o-mini',
        ])->save();

        $this->actingAsAdmin();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $this->assertSame('Bearer sk-saved-key', $request->header('Authorization')[0] ?? null);

            if ($request->method() === 'GET') {
                return Http::response(['id' => 'gpt-4o-mini', 'object' => 'model'], 200);
            }

            return Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
            ], 200);
        });

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => '********',
            'openaiModel' => 'gpt-4o-mini',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('failedStep', null)
            ->assertJsonPath('details.replyPreview', 'ok')
            ->assertJsonPath('details.checks.2.status', 'passed');
    }

    public function test_admin_test_email_requires_saved_smtp(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/settings/test-email', [
            'to' => 'stephenmusyoka207@gmail.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Save Email Settings first: host, port, username, mailbox password, and from address.']);

        $this->assertDatabaseHas('system_logs', [
            'source' => 'smtp',
            'type' => 'warning',
        ]);
    }

    public function test_admin_test_email_rejects_truncated_address(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/settings/test-email', [
            'to' => 'stephenmusyoka207@gmail.c',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_admin_test_email_sends_when_smtp_is_ready(): void
    {
        $this->actingAsAdmin();
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'smtp_host' => 'mail.relayiq.app',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'info@relayiq.app',
            'smtp_password' => 'mailbox-password',
            'mail_from_address' => 'info@relayiq.app',
            'mail_from_name' => 'RelayIQ',
        ])->save();

        \Illuminate\Support\Facades\Mail::fake();

        $this->postJson('/api/admin/settings/test-email', [
            'to' => 'stephenmusyoka207@gmail.com',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_company_user_cannot_test_openai_connection(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'company_admin',
            'email_verified_at' => now(),
        ]));

        $this->postJson('/api/admin/settings/test-openai', [
            'openaiApiKey' => 'sk-test',
        ])->assertForbidden();
    }

    public function test_meta_connection_reports_missing_app_id(): void
    {
        $this->actingAsAdmin();
        config(['whatsapp.embedded_signup_app_id' => '']);

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppSecret' => 'some-secret',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'app_id')
            ->assertJsonPath('details.failedCheck', 'app_id')
            ->assertJsonPath('details.checks.0.id', 'app_id')
            ->assertJsonPath('details.checks.0.status', 'failed');
    }

    public function test_meta_connection_reports_invalid_app_id(): void
    {
        $this->actingAsAdmin();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response([
                    'error' => [
                        'message' => 'Error validating application. Invalid application ID.',
                        'type' => 'OAuthException',
                        'code' => 101,
                    ],
                ], 400);
            }

            return Http::response(['id' => '1'], 200);
        });

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppId' => '111111111111111',
            'whatsappEmbeddedAppSecret' => 'any-secret',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'app_id')
            ->assertJsonPath('details.failedCheck', 'app_id')
            ->assertJsonPath('details.httpStatus', 400)
            ->assertJsonPath('details.checks.0.status', 'failed')
            ->assertJsonPath('details.checks.1.status', 'skipped');
    }

    public function test_meta_connection_reports_invalid_token_exchange_secret(): void
    {
        $this->actingAsAdmin();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), 'oauth/access_token')) {
                return Http::response([
                    'error' => [
                        'message' => 'Error validating client secret.',
                        'type' => 'OAuthException',
                        'code' => 1,
                    ],
                ], 400);
            }

            return Http::response(['id' => '1'], 200);
        });

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppId' => '846055524940193',
            'whatsappEmbeddedAppSecret' => 'wrong-secret',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'embedded_secret')
            ->assertJsonPath('details.failedCheck', 'embedded_secret')
            ->assertJsonPath('details.checks.1.id', 'embedded_secret')
            ->assertJsonPath('details.checks.1.status', 'failed');
    }

    public function test_meta_connection_reports_wrong_webhook_secret_separately(): void
    {
        $this->actingAsAdmin();
        $this->seedMetaVerifyToken('relayiq-test-verify');

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (str_contains($url, 'oauth/access_token')) {
                if (($query['client_secret'] ?? '') === 'wrong-webhook-secret') {
                    return Http::response([
                        'error' => [
                            'message' => 'Error validating client secret.',
                            'code' => 1,
                        ],
                    ], 400);
                }

                return Http::response(['access_token' => 'app|ok'], 200);
            }

            return Http::response(['id' => '846055524940193', 'name' => 'RelayIQ'], 200);
        });

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppId' => '846055524940193',
            'whatsappEmbeddedAppSecret' => 'good-embedded-secret',
            'metaAppSecret' => 'wrong-webhook-secret',
            'whatsappEmbeddedConfigId' => '108970426887711',
            'whatsappWebhookVerifyToken' => 'relayiq-test-verify',
            'whatsappEmbeddedRedirectUri' => 'https://relayiq.app/dashboard/settings',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('failedStep', 'webhook_secret')
            ->assertJsonPath('details.checks.0.status', 'passed')
            ->assertJsonPath('details.checks.1.status', 'passed')
            ->assertJsonPath('details.checks.2.id', 'webhook_secret')
            ->assertJsonPath('details.checks.2.status', 'failed');
    }

    public function test_meta_connection_succeeds_and_uses_saved_secret_when_masked(): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'whatsapp_embedded_app_id' => '846055524940193',
            'whatsapp_embedded_app_secret' => 'saved-embedded-secret',
            'meta_app_secret' => 'saved-embedded-secret',
            'whatsapp_embedded_config_id' => '108970426887711',
            'whatsapp_webhook_verify_token' => 'relayiq-test-verify',
            'whatsapp_embedded_redirect_uri' => 'https://relayiq.app/dashboard/settings',
        ])->save();
        \App\Services\WhatsApp\WhatsAppPlatformConfig::clearCache();

        $this->actingAsAdmin();

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (str_contains($url, 'oauth/access_token')) {
                $this->assertSame('saved-embedded-secret', $query['client_secret'] ?? null);

                return Http::response(['access_token' => 'app|ok'], 200);
            }

            return Http::response(['id' => '846055524940193', 'name' => 'RelayIQ'], 200);
        });

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppId' => '846055524940193',
            'whatsappEmbeddedAppSecret' => '********',
            'metaAppSecret' => '********',
            'whatsappEmbeddedConfigId' => '108970426887711',
            'whatsappWebhookVerifyToken' => '********',
            'whatsappEmbeddedRedirectUri' => 'https://relayiq.app/dashboard/settings',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('failedStep', null)
            ->assertJsonPath('details.appName', 'RelayIQ')
            ->assertJsonPath('details.checks.0.status', 'passed')
            ->assertJsonPath('details.checks.1.status', 'passed')
            ->assertJsonPath('details.checks.2.status', 'passed');
    }

    public function test_company_user_cannot_test_meta_connection(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'company_admin',
            'email_verified_at' => now(),
        ]));

        $this->postJson('/api/admin/settings/test-meta', [
            'whatsappEmbeddedAppId' => '846055524940193',
        ])->assertForbidden();
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));
    }

    private function seedMetaVerifyToken(string $token): void
    {
        $settings = PlatformSetting::first() ?? PlatformSetting::create(['platform_name' => 'RelayIQ']);
        $settings->forceFill([
            'whatsapp_webhook_verify_token' => $token,
        ])->save();
        \App\Services\WhatsApp\WhatsAppPlatformConfig::clearCache();
    }
}
