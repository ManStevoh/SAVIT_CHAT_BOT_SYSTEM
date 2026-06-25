<?php

namespace Tests\Unit;

use App\Models\AiRequestLog;
use App\Models\Company;
use App\Models\PlatformSetting;
use App\Services\AI\OpenAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_completion_logs_successful_usage(): void
    {
        PlatformSetting::create([
            'openai_api_key' => 'sk-test',
            'openai_model' => 'gpt-4o-mini',
            'openai_max_tokens' => 256,
        ]);

        $company = Company::create([
            'name' => 'Test Shop',
            'email' => 'shop@example.test',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [
                    ['message' => ['content' => 'Hello from AI']],
                ],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 18,
                    'total_tokens' => 138,
                ],
            ], 200),
        ]);

        $client = app(OpenAiClient::class);
        $result = $client->chatCompletion(
            messages: [
                ['role' => 'system', 'content' => 'You are helpful.'],
                ['role' => 'user', 'content' => 'Hi'],
            ],
            useCase: OpenAiClient::USE_CASE_WHATSAPP,
            companyId: $company->id,
            chatId: 42,
        );

        $this->assertTrue($result->success);
        $this->assertSame('Hello from AI', $result->content);
        $this->assertSame(138, $result->totalTokens);

        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'chat_id' => 42,
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 120,
            'completion_tokens' => 18,
            'total_tokens' => 138,
            'success' => true,
        ]);
    }

    public function test_chat_completion_retries_on_rate_limit_then_succeeds(): void
    {
        PlatformSetting::create([
            'openai_api_key' => 'sk-test',
            'openai_model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limited']], 429)
                ->push([
                    'model' => 'gpt-4o-mini',
                    'choices' => [['message' => ['content' => 'Recovered']]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
                ], 200),
        ]);

        $result = app(OpenAiClient::class)->chatCompletion(
            messages: [['role' => 'user', 'content' => 'Hi']],
            useCase: OpenAiClient::USE_CASE_GROWTH,
        );

        $this->assertTrue($result->success);
        $this->assertSame('Recovered', $result->content);
        Http::assertSentCount(2);
        $this->assertSame(1, AiRequestLog::where('success', true)->count());
    }

    public function test_chat_completion_logs_failure_when_key_missing(): void
    {
        $result = app(OpenAiClient::class)->chatCompletion(
            messages: [['role' => 'user', 'content' => 'Hi']],
            useCase: OpenAiClient::USE_CASE_WHATSAPP,
        );

        $this->assertFalse($result->success);
        $this->assertDatabaseHas('ai_request_logs', [
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
            'success' => false,
        ]);
        Http::assertNothingSent();
    }
}
