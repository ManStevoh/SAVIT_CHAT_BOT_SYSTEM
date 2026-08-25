<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
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

class ChatReplyAndStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformSetting::create([
            'platform_name' => 'Test',
            'whatsapp_webhook_verify_token' => 'verify-token',
            'meta_app_secret' => 'meta-secret',
        ]);
        WhatsAppPlatformConfig::clearCache();
    }

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Reply Co',
            'email' => 'reply@test.local',
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

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-reply',
            'access_token' => 'token-reply',
            'whatsapp_business_account_id' => 'waba-reply',
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

    public function test_send_message_with_reply_embeds_quote_and_sends_context(): void
    {
        Http::fake([
            'graph.facebook.com/*/messages' => Http::response([
                'messages' => [['id' => 'wamid.agent-reply']],
            ], 200),
        ]);

        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Jane',
            'customer_phone' => '254711111111',
            'last_message' => 'Hi',
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        $quoted = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Original customer question',
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => 'wamid.original',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/chats/{$chat->id}/messages", [
            'content' => 'Here is my answer',
            'replyToMessageId' => $quoted->id,
        ])->assertOk()->assertJsonPath('success', true);

        $stored = Message::where('whatsapp_message_id', 'wamid.agent-reply')->first();
        $this->assertNotNull($stored);
        $this->assertSame($quoted->id, $stored->reply_to_message_id);

        $this->getJson("/api/company/chats/{$chat->id}/messages")
            ->assertOk()
            ->assertJsonFragment([
                'content' => 'Here is my answer',
                'replyToMessageId' => (string) $quoted->id,
            ]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['context']['message_id'] ?? null) === 'wamid.original'
                && ($body['text']['body'] ?? null) === 'Here is my answer';
        });
    }

    public function test_status_webhook_advances_monotonically(): void
    {
        $user = $this->companyUser();
        $chat = Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Jane',
            'customer_phone' => '254711111111',
            'status' => 'active',
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello',
            'sender' => 'agent',
            'status' => 'sent',
            'whatsapp_message_id' => 'wamid.status-1',
        ]);

        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.status-1',
                            'status' => 'read',
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

        $this->assertSame('read', $message->fresh()->status);

        $downgrade = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.status-1',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $hash2 = hash_hmac('sha256', $downgrade, 'meta-secret');
        $this->call(
            'POST',
            '/api/whatsapp/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'X-Hub-Signature-256' => 'sha256='.$hash2,
                'Content-Type' => 'application/json',
            ]),
            $downgrade
        )->assertOk();

        $this->assertSame('read', $message->fresh()->status);
    }

    public function test_chat_search_matches_local_phone_formats(): void
    {
        $user = $this->companyUser();
        Chat::create([
            'company_id' => $user->company_id,
            'customer_name' => 'Jane',
            'customer_phone' => '254712345678',
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/company/chats?search=0712345678')
            ->assertOk()
            ->assertJsonFragment(['customerPhone' => '254712345678']);
    }
}
