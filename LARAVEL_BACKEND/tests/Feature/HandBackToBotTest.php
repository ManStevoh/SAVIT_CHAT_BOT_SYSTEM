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
use Illuminate\Support\Facades\Queue;
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
            'agent_commerce_enabled' => true,
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

    public function test_hand_back_reprocesses_latest_unanswered_customer_message(): void
    {
        Queue::fake();

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

        $customer = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hi',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.customer-hi',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('reprocessed', true);

        $this->assertNull($chat->fresh()->agent_handling_at);

        Queue::assertPushed(ProcessIncomingWhatsAppMessage::class, function ($job) use ($chat, $customer) {
            return $job->chatId === (int) $chat->id
                && $job->forceReply === true
                && $job->incomingMessageId === (int) $customer->id
                && $job->messageText === 'Hi';
        });
    }

    public function test_hand_back_skips_reprocess_when_agent_already_replied_after_customer(): void
    {
        Queue::fake();

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Essem',
            'customer_phone' => '254728210962',
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
            'content' => 'Thanks, agent here',
            'sender' => 'agent',
            'status' => 'sent',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/hand-back")
            ->assertOk()
            ->assertJsonPath('reprocessed', false);

        Queue::assertNotPushed(ProcessIncomingWhatsAppMessage::class);
    }
}
