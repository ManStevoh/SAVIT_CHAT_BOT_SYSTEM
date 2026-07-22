<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformSetting::create([
            'platform_name' => 'Test',
            'whatsapp_webhook_verify_token' => 'verify-token',
            'meta_app_secret' => 'meta-secret',
            'whatsapp_embedded_app_id' => 'app-123',
            'whatsapp_embedded_config_id' => 'config-456',
            'whatsapp_embedded_app_secret' => 'app-secret',
            'whatsapp_embedded_redirect_uri' => 'https://app.test/dashboard/settings',
        ]);

        WhatsAppPlatformConfig::clearCache();
    }

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Test Biz',
            'email' => 'biz@test.local',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_embedded_config_enabled_when_platform_credentials_set(): void
    {
        Sanctum::actingAs($this->companyUser());

        $response = $this->getJson('/api/company/whatsapp/embedded/config');

        $response->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('graphVersion', 'v22.0');
    }

    public function test_complete_embedded_signup_subscribes_webhooks_and_registers_phone(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token-abc'], 200),
            'graph.facebook.com/*/waba-1/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-1/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-1',
            'whatsappBusinessAccountId' => 'waba-1',
            'displayPhoneNumber' => '+254712345678',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertNotNull($account);
        $this->assertSame('active', $account->status);
        $this->assertSame('active', $account->onboarding_status);
        $this->assertNotNull($account->webhook_subscribed_at);
        $this->assertNotNull($account->phone_registered_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'waba-1/subscribed_apps'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'phone-1/register'));
    }

    public function test_embedded_config_disabled_when_admin_toggles_off(): void
    {
        PlatformSetting::query()->update(['whatsapp_embedded_signup_enabled' => false]);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/whatsapp/embedded/config')
            ->assertOk()
            ->assertJsonPath('enabled', false);

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-1',
            'whatsappBusinessAccountId' => 'waba-1',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_manual_connect_subscribes_webhooks_and_registers_phone(): void
    {
        Http::fake([
            'graph.facebook.com/*/phone-manual*' => Http::response([
                'id' => 'phone-manual',
                'display_phone_number' => '+254712345678',
                'quality_rating' => 'GREEN',
            ], 200),
            'graph.facebook.com/*/waba-manual/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-manual/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'phone-manual',
            'accessToken' => 'manual-token',
            'whatsappBusinessAccountId' => 'waba-manual',
            'displayPhoneNumber' => '+254712345678',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertNotNull($account);
        $this->assertSame('active', $account->status);
        $this->assertSame('phone-manual', $account->phone_number_id);
    }

    public function test_manual_connect_uses_provided_registration_pin(): void
    {
        Http::fake([
            'graph.facebook.com/*/phone-manual*' => Http::response([
                'id' => 'phone-manual',
                'display_phone_number' => '+254712345678',
                'quality_rating' => 'GREEN',
            ], 200),
            'graph.facebook.com/*/waba-manual/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-manual/register' => Http::response(['success' => true], 200),
        ]);

        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'phone-manual',
            'accessToken' => 'manual-token',
            'whatsappBusinessAccountId' => 'waba-manual',
            'registrationPin' => '654321',
        ])->assertOk()->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'phone-manual/register')) {
                return true;
            }

            return ($request['pin'] ?? null) === '654321'
                && ($request['messaging_product'] ?? null) === 'whatsapp';
        });
    }

    public function test_manual_connect_disabled_when_admin_toggles_off(): void
    {
        PlatformSetting::query()->update(['whatsapp_manual_connect_enabled' => false]);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'phone-manual',
            'accessToken' => 'manual-token',
        ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_status_includes_manual_connect_flag(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('manualConnectEnabled', true);
    }

    public function test_admin_can_list_whatsapp_connections(): void
    {
        $company = Company::create(['name' => 'Acme Ltd', 'email' => 'acme@test.local', 'status' => 'active']);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-99',
            'access_token' => 'token',
            'status' => 'active',
            'onboarding_status' => 'active',
            'display_phone_number' => '+1234567890',
            'connected_at' => now(),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/whatsapp/connections');

        $response->assertOk()
            ->assertJsonPath('platform.embeddedSignupEnabled', true)
            ->assertJsonCount(1, 'connections')
            ->assertJsonPath('connections.0.companyName', 'Acme Ltd');
    }

    public function test_solution_partner_mode_shares_credit_line_on_embedded_signup(): void
    {
        PlatformSetting::query()->update([
            'whatsapp_billing_model' => 'solution_partner',
            'whatsapp_extended_credit_line_id' => 'credit-line-1',
            'whatsapp_credit_sharing_system_token' => 'system-token-xyz',
            'whatsapp_waba_currency' => 'USD',
        ]);
        WhatsAppPlatformConfig::clearCache();

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token-abc'], 200),
            'graph.facebook.com/*/waba-sp/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/credit-line-1/whatsapp_credit_sharing_and_attach*' => Http::response([
                'allocation_config_id' => 'alloc-999',
                'waba_id' => 'waba-sp',
            ], 200),
            'graph.facebook.com/*/phone-sp/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-sp',
            'whatsappBusinessAccountId' => 'waba-sp',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertNotNull($account);
        $this->assertSame('solution_partner', $account->meta_billing_model);
        $this->assertSame('alloc-999', $account->credit_allocation_config_id);
        $this->assertNotNull($account->credit_line_shared_at);
        $this->assertSame('active', $account->status);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'whatsapp_credit_sharing_and_attach')
            && str_contains($request->url(), 'waba_id=waba-sp')
            && str_contains($request->url(), 'waba_currency=USD'));
    }

    public function test_solution_partner_mode_fails_when_credit_credentials_missing(): void
    {
        PlatformSetting::query()->update([
            'whatsapp_billing_model' => 'solution_partner',
            'whatsapp_extended_credit_line_id' => null,
            'whatsapp_credit_sharing_system_token' => null,
        ]);
        WhatsAppPlatformConfig::clearCache();

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token-abc'], 200),
            'graph.facebook.com/*/waba-sp/subscribed_apps' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-sp',
            'whatsappBusinessAccountId' => 'waba-sp',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'platform_billing_not_ready');

        $this->assertNull(WhatsAppAccount::where('company_id', $user->company_id)->first());
    }

    public function test_status_reflects_solution_partner_billing_model(): void
    {
        PlatformSetting::query()->update(['whatsapp_billing_model' => 'solution_partner']);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('metaBillingModel', 'solution_partner')
            ->assertJsonPath('requiresMetaPaymentMethod', false);
    }

    public function test_tech_provider_mode_sets_meta_billing_model_on_account(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token-abc'], 200),
            'graph.facebook.com/*/waba-1/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-1/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-1',
            'whatsappBusinessAccountId' => 'waba-1',
        ])->assertOk();

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertSame('tech_provider', $account->meta_billing_model);
        $this->assertNull($account->credit_line_shared_at);
    }

    public function test_solution_partner_manual_connect_shares_credit_line(): void
    {
        PlatformSetting::query()->update([
            'whatsapp_billing_model' => 'solution_partner',
            'whatsapp_extended_credit_line_id' => 'credit-line-1',
            'whatsapp_credit_sharing_system_token' => 'system-token-xyz',
            'whatsapp_waba_currency' => 'USD',
        ]);
        WhatsAppPlatformConfig::clearCache();

        Http::fake([
            'graph.facebook.com/*/phone-manual*' => Http::response([
                'id' => 'phone-manual',
                'display_phone_number' => '+254712345678',
            ], 200),
            'graph.facebook.com/*/waba-manual/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/credit-line-1/whatsapp_credit_sharing_and_attach*' => Http::response([
                'allocation_config_id' => 'alloc-manual',
                'waba_id' => 'waba-manual',
            ], 200),
            'graph.facebook.com/*/phone-manual/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'phone-manual',
            'accessToken' => 'manual-token',
            'whatsappBusinessAccountId' => 'waba-manual',
        ])->assertOk();

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertSame('solution_partner', $account->meta_billing_model);
        $this->assertSame('alloc-manual', $account->credit_allocation_config_id);
    }

    public function test_solution_partner_disconnect_revokes_credit_line(): void
    {
        PlatformSetting::query()->update([
            'whatsapp_billing_model' => 'solution_partner',
            'whatsapp_extended_credit_line_id' => 'credit-line-1',
            'whatsapp_credit_sharing_system_token' => 'system-token-xyz',
        ]);
        WhatsAppPlatformConfig::clearCache();

        Http::fake([
            'graph.facebook.com/*/waba-sp/subscribed_apps' => Http::sequence()
                ->push(['success' => true], 200)
                ->push(['success' => true], 200),
            'graph.facebook.com/*/alloc-999*' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        WhatsAppAccount::create([
            'company_id' => $user->company_id,
            'phone_number_id' => 'phone-sp',
            'whatsapp_business_account_id' => 'waba-sp',
            'access_token' => 'biz-token',
            'meta_billing_model' => 'solution_partner',
            'credit_allocation_config_id' => 'alloc-999',
            'credit_line_shared_at' => now(),
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/company/whatsapp/disconnect')->assertOk();

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertSame('disconnected', $account->onboarding_status);
        $this->assertNull($account->credit_allocation_config_id);
        $this->assertNull($account->credit_line_shared_at);

        Http::assertSent(fn ($req) => $req->method() === 'DELETE' && str_contains($req->url(), 'alloc-999'));
    }

    public function test_embedded_signup_rejects_duplicate_phone(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'email' => 'other@test.local', 'status' => 'active']);
        WhatsAppAccount::create([
            'company_id' => $otherCompany->id,
            'phone_number_id' => 'phone-dup',
            'access_token' => 'token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token-abc'], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-dup',
            'whatsappBusinessAccountId' => 'waba-new',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_connect_blocked_when_solution_partner_not_configured(): void
    {
        PlatformSetting::query()->update([
            'whatsapp_billing_model' => 'solution_partner',
            'whatsapp_extended_credit_line_id' => null,
            'whatsapp_credit_sharing_system_token' => null,
        ]);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'oauth-code-xyz',
            'phoneNumberId' => 'phone-1',
            'whatsappBusinessAccountId' => 'waba-1',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'platform_billing_not_ready');

        $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'phone-1',
            'accessToken' => 'token',
        ])
            ->assertStatus(503)
            ->assertJsonPath('code', 'platform_billing_not_ready');
    }

    public function test_admin_can_save_billing_model_settings(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'whatsappBillingModel' => 'solution_partner',
            'whatsappExtendedCreditLineId' => 'credit-123',
            'whatsappCreditSharingSystemToken' => 'sys-token-abc',
            'whatsappWabaCurrency' => 'USD',
        ])->assertOk();

        WhatsAppPlatformConfig::clearCache();

        $this->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('whatsappBillingModel', 'solution_partner')
            ->assertJsonPath('whatsappExtendedCreditLineId', 'credit-123')
            ->assertJsonPath('whatsappSolutionPartnerReady', true)
            ->assertJsonPath('whatsappCreditSharingSystemToken', '********');
    }

    public function test_embedded_config_includes_billing_flags(): void
    {
        PlatformSetting::query()->update(['whatsapp_billing_model' => 'solution_partner']);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/whatsapp/embedded/config')
            ->assertOk()
            ->assertJsonPath('metaBillingModel', 'solution_partner')
            ->assertJsonPath('requiresMetaPaymentMethod', false)
            ->assertJsonPath('platformBillingReady', false);
    }
}
