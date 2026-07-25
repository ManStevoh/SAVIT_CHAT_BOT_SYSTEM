<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Faq;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HandBackToBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::create([
            'platform_name' => 'Test',
            'meta_app_secret' => 'meta-secret',
        ]);
        WhatsAppPlatformConfig::clearCache();
    }

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Handback Co',
            'email' => 'handback@test.local',
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
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-hb',
            'access_token' => 'token-hb',
            'whatsapp_business_account_id' => 'waba-hb',
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

    public function test_hand_back_sends_ai_reply_synchronously(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot-hb']],
            ], 200),
        ]);

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Essem',
            'customer_phone' => '254728210962',
            'status' => 'active',
            'agent_handling_at' => now(),
            'last_message' => 'Hi',
            'last_message_at' => now(),
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'hello',
            'sender' => 'agent',
            'status' => 'sent',
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hi',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.customer-hi',
        ]);

        Faq::create([
            'company_id' => $user->company_id,
            'question' => 'Hi',
            'answer' => 'Hello! How can we help you today?',
            'keywords' => ['hi', 'hello'],
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reprocessed', true)
            ->assertJsonPath('replied', true);

        $this->assertNull($chat->fresh()->agent_handling_at);
        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
        ]);
    }

    public function test_hand_back_skips_reprocess_when_agent_already_replied_after_customer(): void
    {
        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Essem',
            'customer_phone' => '254728210963',
            'status' => 'active',
            'agent_handling_at' => now(),
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hi',
            'sender' => 'customer',
            'status' => 'received',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Thanks, we are on it',
            'sender' => 'agent',
            'status' => 'sent',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('reprocessed', false)
            ->assertJsonPath('replied', false);

        $this->assertNull($chat->fresh()->agent_handling_at);
    }
}
