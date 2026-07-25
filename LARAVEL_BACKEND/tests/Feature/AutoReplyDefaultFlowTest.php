<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutoReplyDefaultFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::create([
            'platform_name' => 'Test',
            'meta_app_secret' => 'meta-secret',
            'whatsapp_webhook_verify_token' => 'verify-token',
        ]);
        WhatsAppPlatformConfig::clearCache();
    }

    private function companyUser(bool $withSettings = true, bool $autoReply = true): User
    {
        $company = Company::create([
            'name' => 'AutoReply Co',
            'email' => 'auto@test.local',
            'status' => 'active',
        ]);

        if ($withSettings) {
            CompanySetting::create([
                'company_id' => $company->id,
                'auto_reply_enabled' => $autoReply,
                'ai_reply_mode' => 'balanced',
            ]);
        }

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-auto',
            'access_token' => 'token-auto',
            'whatsapp_business_account_id' => 'waba-auto',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_start_chat_does_not_lock_agent_handling_by_default(): void
    {
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/chats/start', [
            'phone' => '254700000001',
            'name' => 'Customer One',
        ])->assertCreated()
            ->assertJsonPath('chat.isAgentHandling', false)
            ->assertJsonPath('chat.agentHandlingAt', null);

        $this->assertDatabaseHas('chats', [
            'company_id' => $user->company_id,
            'customer_phone' => '254700000001',
            'agent_handling_at' => null,
        ]);
    }

    public function test_reopening_chat_does_not_take_over_from_ai(): void
    {
        $user = $this->companyUser();
        Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Existing',
            'customer_phone' => '254700000002',
            'status' => 'active',
            'agent_handling_at' => null,
            'ai_handled' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/company/chats/start', [
            'phone' => '254700000002',
            'name' => 'Existing',
        ])->assertOk()
            ->assertJsonPath('chat.isAgentHandling', false);

        $this->assertNull(Chat::where('customer_phone', '254700000002')->value('agent_handling_at'));
    }

    public function test_settings_api_defaults_auto_reply_enabled_when_settings_missing(): void
    {
        $user = $this->companyUser(withSettings: false);
        Sanctum::actingAs($user);

        $this->getJson('/api/company/settings')
            ->assertOk()
            ->assertJsonPath('autoReplyEnabled', true);
    }

    public function test_incoming_webhook_dispatches_auto_reply_when_not_agent_handling(): void
    {
        Queue::fake();
        $user = $this->companyUser();

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone-auto'],
                        'contacts' => [['profile' => ['name' => 'Buyer']]],
                        'messages' => [[
                            'id' => 'wamid.auto-1',
                            'from' => '254711111111',
                            'type' => 'text',
                            'text' => ['body' => 'I want to buy'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $hash = hash_hmac('sha256', $payload, 'meta-secret');
        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hub-Signature-256' => 'sha256='.$hash,
                'Content-Type' => 'application/json',
            ]),
            $payload
        )->assertOk();

        $chat = Chat::where('customer_phone', '254711111111')->first();
        $this->assertNotNull($chat);
        $this->assertNull($chat->agent_handling_at);

        Queue::assertPushed(ProcessIncomingWhatsAppMessage::class);
    }

    public function test_agent_send_takes_over_then_handback_returns_to_ai(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.agent-or-bot']],
            ], 200),
        ]);

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254722222222',
            'status' => 'active',
            'agent_handling_at' => null,
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Need help',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.need-help',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/messages", [
            'content' => 'Hello from agent',
        ])->assertOk();

        $this->assertNotNull($chat->fresh()->agent_handling_at);

        // Customer messages again while agent owns the chat — bot stays silent until handback.
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Still need help',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.still-need-help',
        ]);

        \App\Models\Faq::create([
            'company_id' => $user->company_id,
            'question' => 'Still need help',
            'answer' => 'We are here to help.',
            'keywords' => ['help'],
            'is_active' => true,
        ]);

        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('reprocessed', true)
            ->assertJsonPath('replied', true);

        $this->assertNull($chat->fresh()->agent_handling_at);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
        ]);
    }

    public function test_process_job_still_replies_when_company_settings_row_missing(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-nosettings']],
            ], 200),
        ]);

        // Missing settings should default auto-reply ON for handback reprocess.
        $user = $this->companyUser(withSettings: false);
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254733333333',
            'status' => 'active',
            'agent_handling_at' => now(),
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello?',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.hello',
        ]);

        \App\Models\Faq::create([
            'company_id' => $user->company_id,
            'question' => 'Hello?',
            'answer' => 'Hi there!',
            'keywords' => ['hello'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('reprocessed', true)
            ->assertJsonPath('replied', true);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
        ]);
    }

    public function test_trial_subscription_gets_ai_reply_not_unavailable_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-trial']],
            ], 200),
        ]);

        $company = Company::create([
            'name' => 'Trial Co',
            'email' => 'trial@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'ai_reply_mode' => 'balanced',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(13),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-trial',
            'access_token' => 'token-trial',
            'whatsapp_business_account_id' => 'waba-trial',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Trial Buyer',
            'customer_phone' => '254744444444',
            'status' => 'active',
        ]);

        $incoming = Message::create([
            'chat_id' => $chat->id,
            'content' => 'What are your hours?',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.trial-q',
        ]);

        \App\Models\Faq::create([
            'company_id' => $company->id,
            'question' => 'What are your hours?',
            'answer' => 'We are open 9am to 5pm.',
            'keywords' => ['hours'],
            'is_active' => true,
        ]);

        (new ProcessIncomingWhatsAppMessage(
            (int) $company->id,
            (int) $chat->id,
            '254744444444',
            'phone-trial',
            'What are your hours?',
            'Trial Buyer',
            'wamid.trial-q',
            (int) $incoming->id,
        ))->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $bot = Message::where('chat_id', $chat->id)->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($bot);
        $this->assertStringNotContainsString('temporarily unavailable', (string) $bot->content);
    }

    public function test_ask_ai_replies_to_unanswered_customer_without_agent_lock(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-ask']],
            ], 200),
        ]);

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Stuck',
            'customer_phone' => '254766666666',
            'status' => 'active',
            'agent_handling_at' => null,
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.stuck-hello',
        ]);

        \App\Models\Faq::create([
            'company_id' => $user->company_id,
            'question' => 'Hello',
            'answer' => 'Hi from AI.',
            'keywords' => ['hello'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('reprocessed', true)
            ->assertJsonPath('replied', true);

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
        ]);
    }

    public function test_expired_agent_lock_allows_auto_reply_on_new_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-after-expiry']],
            ], 200),
        ]);

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Expired Lock',
            'customer_phone' => '254777777777',
            'status' => 'active',
            'agent_handling_at' => now()->subMinutes(45),
        ]);

        $incoming = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Are you there?',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.after-expiry',
        ]);

        \App\Models\Faq::create([
            'company_id' => $user->company_id,
            'question' => 'Are you there?',
            'answer' => 'Yes, we are here.',
            'keywords' => ['there'],
            'is_active' => true,
        ]);

        (new ProcessIncomingWhatsAppMessage(
            (int) $user->company_id,
            (int) $chat->id,
            '254777777777',
            'phone-auto',
            'Are you there?',
            'Expired Lock',
            'wamid.after-expiry',
            (int) $incoming->id,
        ))->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertNull($chat->fresh()->agent_handling_at);
        $bot = Message::where('chat_id', $chat->id)->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($bot);
        $this->assertStringNotContainsString('temporarily unavailable', (string) $bot->content);
    }

    public function test_expired_subscription_sends_unavailable_message(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-expired']],
            ], 200),
        ]);

        $company = Company::create([
            'name' => 'Expired Co',
            'email' => 'expired@test.local',
            'status' => 'active',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subDay(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-expired',
            'access_token' => 'token-expired',
            'whatsapp_business_account_id' => 'waba-expired',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Expired Buyer',
            'customer_phone' => '254755555555',
            'status' => 'active',
        ]);

        $incoming = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.expired-q',
        ]);

        (new ProcessIncomingWhatsAppMessage(
            (int) $company->id,
            (int) $chat->id,
            '254755555555',
            'phone-expired',
            'Hello',
            'Expired Buyer',
            'wamid.expired-q',
            (int) $incoming->id,
        ))->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $bot = Message::where('chat_id', $chat->id)->where('sender', 'bot')->latest('id')->first();
        $this->assertNotNull($bot);
        $this->assertStringContainsString('temporarily unavailable', (string) $bot->content);
    }
}
