<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_branding_exposes_cookie_and_recaptcha_public_fields(): void
    {
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'cookie_banner_enabled' => true,
            'cookie_banner_text' => 'We use cookies.',
            'cookie_policy_url' => '/privacy',
            'recaptcha_enabled' => true,
            'recaptcha_site_key' => 'site-public',
            'recaptcha_secret_key' => 'secret-private',
        ]);

        $this->getJson('/api/app-branding')
            ->assertOk()
            ->assertJsonPath('cookieBannerEnabled', true)
            ->assertJsonPath('cookieBannerText', 'We use cookies.')
            ->assertJsonPath('cookiePolicyUrl', '/privacy')
            ->assertJsonPath('recaptchaEnabled', true)
            ->assertJsonPath('recaptchaSiteKey', 'site-public')
            ->assertJsonMissingPath('recaptchaSecretKey');
    }

    public function test_admin_can_update_compliance_settings_without_overwriting_masked_secret(): void
    {
        $settings = PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'recaptcha_secret_key' => 'real-secret',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->putJson('/api/admin/settings', [
            'cookieBannerEnabled' => true,
            'cookieBannerText' => 'Cookies help us improve.',
            'cookiePolicyUrl' => '/privacy',
            'recaptchaEnabled' => true,
            'recaptchaSiteKey' => 'site-key-1',
            'recaptchaSecretKey' => '********',
        ])->assertOk()->assertJsonPath('success', true);

        $settings->refresh();
        $this->assertTrue((bool) $settings->cookie_banner_enabled);
        $this->assertSame('Cookies help us improve.', $settings->cookie_banner_text);
        $this->assertSame('site-key-1', $settings->recaptcha_site_key);
        $this->assertSame('real-secret', $settings->getRawOriginal('recaptcha_secret_key'));
    }

    public function test_contact_requires_recaptcha_when_enabled(): void
    {
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'recaptcha_enabled' => true,
            'recaptcha_site_key' => 'site',
            'recaptcha_secret_key' => 'secret',
        ]);

        $this->postJson('/api/contact', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello there',
        ])->assertStatus(422)->assertJsonValidationErrors(['recaptchaToken']);
    }

    public function test_contact_accepts_valid_recaptcha_token(): void
    {
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'recaptcha_enabled' => true,
            'recaptcha_site_key' => 'site',
            'recaptcha_secret_key' => 'secret',
        ]);

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->postJson('/api/contact', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello there',
            'recaptchaToken' => 'valid-token',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_register_requires_recaptcha_when_enabled(): void
    {
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'allow_new_registrations' => true,
            'recaptcha_enabled' => true,
            'recaptcha_site_key' => 'site',
            'recaptcha_secret_key' => 'secret',
        ]);

        $this->postJson('/api/auth/register', [
            'companyName' => 'Acme',
            'name' => 'Owner',
            'email' => 'owner@acme.test',
            'phone' => '+254700000000',
            'password' => 'password123',
            'confirmPassword' => 'password123',
            'acceptTerms' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['recaptchaToken']);
    }

    public function test_recaptcha_service_disabled_without_keys(): void
    {
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'recaptcha_enabled' => true,
            'recaptcha_site_key' => null,
            'recaptcha_secret_key' => null,
        ]);

        $this->assertFalse(app(RecaptchaService::class)->isEnabled());
    }
}
