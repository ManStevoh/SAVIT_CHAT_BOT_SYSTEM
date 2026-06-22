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
}
