<?php

namespace Tests\Unit;

use App\Models\AiRequestLog;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\AiUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevModePromptLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_mode_enabled_toggle_stores_prompt_payload(): void
    {
        $company = Company::create(['name' => 'Test Dev Co', 'email' => 'dev@test.local']);
        $settings = CompanySetting::create([
            'company_id' => $company->id,
            'dev_mode_enabled' => true,
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '123456789',
            'customer_name' => 'John Dev',
            'last_message_at' => now(),
        ]);

        $gateway = app(AiGateway::class);
        $reflection = new \ReflectionMethod(AiGateway::class, 'persistLog');
        $reflection->setAccessible(true);

        $messages = [
            ['role' => 'system', 'content' => 'System prompt debug test'],
            ['role' => 'user', 'content' => 'Customer message test'],
        ];

        $chatResult = new \App\Services\AI\OpenAiChatResult(
            content: 'Bot response test',
            success: true,
            model: 'gpt-4o-mini',
            promptTokens: 50,
            completionTokens: 20,
            totalTokens: 70,
        );

        $logId = $reflection->invoke($gateway, $chatResult, AiUseCase::WHATSAPP, $company->id, $chat->id, null, $messages);

        $this->assertNotNull($logId);
        $log = AiRequestLog::find($logId);
        $this->assertNotNull($log);
        $this->assertNotNull($log->prompt_payload);
        $this->assertStringContainsString('System prompt debug test', $log->prompt_payload);
        $this->assertStringContainsString('Customer message test', $log->prompt_payload);
    }

    public function test_dev_mode_disabled_does_not_store_prompt_payload(): void
    {
        $company = Company::create(['name' => 'Test Normal Co', 'email' => 'normal@test.local']);
        CompanySetting::create([
            'company_id' => $company->id,
            'dev_mode_enabled' => false,
        ]);

        $gateway = app(AiGateway::class);
        $reflection = new \ReflectionMethod(AiGateway::class, 'persistLog');
        $reflection->setAccessible(true);

        $messages = [
            ['role' => 'system', 'content' => 'Secret system prompt'],
            ['role' => 'user', 'content' => 'Secret user message'],
        ];

        $chatResult = new \App\Services\AI\OpenAiChatResult(
            content: 'Response',
            success: true,
            model: 'gpt-4o-mini',
        );

        $logId = $reflection->invoke($gateway, $chatResult, AiUseCase::WHATSAPP, $company->id, null, null, $messages);

        $log = AiRequestLog::find($logId);
        $this->assertNotNull($log);
        $this->assertNull($log->prompt_payload);
    }

    public function test_download_prompt_endpoint_returns_file_download(): void
    {
        $company = Company::create(['name' => 'Dev Test Co', 'email' => 'dev2@test.local']);
        CompanySetting::create(['company_id' => $company->id, 'dev_mode_enabled' => true]);
        \App\Models\Subscription::create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'status' => 'active',
            'amount' => 0,
            'billing_cycle' => 'monthly',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Dev Admin',
            'email' => 'devadmin@test.local',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '987654321',
            'customer_name' => 'Jane',
            'last_message_at' => now(),
        ]);

        $logPayload = json_encode([
            ['role' => 'system', 'content' => 'System debug instructions'],
            ['role' => 'user', 'content' => 'Hello AI'],
        ]);

        $log = AiRequestLog::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'use_case' => 'whatsapp',
            'model' => 'gpt-4o',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'success' => true,
            'prompt_payload' => $logPayload,
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hello there!',
            'sender' => 'bot',
            'status' => 'sent',
            'ai_request_log_id' => $log->id,
        ]);

        $response = $this->actingAs($user)->get("/api/company/chats/{$chat->id}/messages/{$message->id}/download-prompt");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('RELAYIQ AI PROMPT DEBUGGER REPORT', $response->streamedContent());
        $this->assertStringContainsString('System debug instructions', $response->streamedContent());
    }
}
