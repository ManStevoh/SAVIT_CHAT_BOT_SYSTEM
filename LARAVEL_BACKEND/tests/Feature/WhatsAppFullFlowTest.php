<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppFullFlowTest extends TestCase
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
            'name' => 'Flow Co',
            'email' => 'flow@test.local',
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

    private function signedWebhookHeaders(string $payload): array
    {
        $hash = hash_hmac('sha256', $payload, 'meta-secret');

        return [
            'X-Hub-Signature-256' => 'sha256=' . $hash,
            'Content-Type' => 'application/json',
        ];
    }

    private function connectCompany(User $user): WhatsAppAccount
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/waba-flow/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-flow/register' => Http::response(['success' => true], 200),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'code' => 'code-abc',
            'phoneNumberId' => 'phone-flow',
            'whatsappBusinessAccountId' => 'waba-flow',
            'displayPhoneNumber' => '+254700000001',
        ])->assertOk();

        return WhatsAppAccount::where('company_id', $user->company_id)->firstOrFail();
    }

    public function test_webhook_verify_returns_challenge(): void
    {
        $response = $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=verify-token&hub_challenge=challenge123');

        $response->assertOk();
        $response->assertSee('challenge123');
    }

    public function test_webhook_verify_rejects_bad_token(): void
    {
        $this->get('/api/whatsapp/webhook?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=x')
            ->assertForbidden();
    }

    public function test_incoming_webhook_creates_chat_and_message(): void
    {
        Queue::fake();

        $user = $this->companyUser();
        $this->connectCompany($user);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone-flow'],
                        'contacts' => [['profile' => ['name' => 'Jane Customer']]],
                        'messages' => [[
                            'id' => 'wamid.test123',
                            'from' => '254711111111',
                            'type' => 'text',
                            'text' => ['body' => 'Hello shop'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedWebhookHeaders($payload)),
            $payload
        )->assertOk();

        $this->assertDatabaseHas('chats', [
            'company_id' => $user->company_id,
            'customer_phone' => '254711111111',
        ]);

        $chat = Chat::where('company_id', $user->company_id)->first();
        $this->assertNotNull($chat);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'content' => 'Hello shop',
            'whatsapp_message_id' => 'wamid.test123',
        ]);

        Queue::assertPushed(\App\Jobs\ProcessIncomingWhatsAppMessage::class);
    }

    public function test_disconnect_unsubscribes_and_deactivates(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/waba-flow/subscribed_apps' => Http::sequence()
                ->push(['success' => true], 200)
                ->push(['success' => true], 200),
            'graph.facebook.com/*/phone-flow/register' => Http::response(['success' => true], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);
        $this->connectCompany($user);

        $this->postJson('/api/company/whatsapp/disconnect')->assertOk();

        $account = WhatsAppAccount::where('company_id', $user->company_id)->first();
        $this->assertSame('inactive', $account->status);
        $this->assertSame('disconnected', $account->onboarding_status);

        Http::assertSent(fn ($req) => $req->method() === 'DELETE' && str_contains($req->url(), 'waba-flow/subscribed_apps'));
    }

    public function test_template_sync_and_create(): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'biz-token'], 200),
            'graph.facebook.com/*/waba-flow/subscribed_apps' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/phone-flow/register' => Http::response(['success' => true], 200),
            'graph.facebook.com/*/waba-flow/message_templates*' => Http::response([
                'data' => [[
                    'id' => 'tpl-meta-1',
                    'name' => 'order_ready',
                    'language' => 'en',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'components' => [['type' => 'BODY', 'text' => 'Your order is ready']],
                ]],
            ], 200),
            'graph.facebook.com/*/waba-flow/message_templates' => Http::response(['id' => 'tpl-new-1'], 200),
        ]);

        $user = $this->companyUser();
        Sanctum::actingAs($user);
        $this->connectCompany($user);

        $this->postJson('/api/company/whatsapp/templates/sync')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'company_id' => $user->company_id,
            'name' => 'order_ready',
            'status' => 'approved',
        ]);

        $this->postJson('/api/company/whatsapp/templates', [
            'name' => 'welcome_msg',
            'body' => 'Welcome to our store!',
            'category' => 'utility',
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'company_id' => $user->company_id,
            'name' => 'welcome_msg',
        ]);
    }

    public function test_template_status_webhook_updates_local_template(): void
    {
        $user = $this->companyUser();
        $account = WhatsAppAccount::create([
            'company_id' => $user->company_id,
            'phone_number_id' => 'phone-tpl',
            'whatsapp_business_account_id' => 'waba-tpl',
            'access_token' => 'token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        WhatsAppMessageTemplate::create([
            'company_id' => $user->company_id,
            'name' => 'promo_offer',
            'language' => 'en',
            'status' => 'pending',
            'category' => 'marketing',
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => [
                        'whatsapp_business_account_id' => 'waba-tpl',
                        'message_template_name' => 'promo_offer',
                        'message_template_language' => 'en',
                        'event' => 'APPROVED',
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($this->signedWebhookHeaders($payload)),
            $payload
        )->assertOk();

        $this->assertDatabaseHas('whatsapp_message_templates', [
            'company_id' => $user->company_id,
            'name' => 'promo_offer',
            'status' => 'approved',
        ]);
    }

    public function test_embedded_complete_requires_code(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/whatsapp/embedded/complete', [
            'phoneNumberId' => 'phone-1',
        ])->assertUnprocessable();
    }

    public function test_manual_connect_endpoint_disabled_when_toggle_off(): void
    {
        PlatformSetting::query()->update(['whatsapp_manual_connect_enabled' => false]);
        WhatsAppPlatformConfig::clearCache();

        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/whatsapp/connect', [
            'phoneNumberId' => 'x',
            'accessToken' => 'y',
        ])->assertForbidden();
    }
}
