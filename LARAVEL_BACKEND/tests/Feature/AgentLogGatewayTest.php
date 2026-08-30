<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Models\Company;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AgentLogGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['deploy.secret' => 'test-secret-12345']);
        config(['deploy.agent_key' => 'test-agent-key-xyz']);
    }

    public function test_unauthorized_request_rejected_with_401(): void
    {
        $response = $this->postJson('/logs/agent', [
            'channel' => 'laravel',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['success' => false]);
    }

    public function test_valid_agent_key_fetches_recent_laravel_logs(): void
    {
        $logPath = storage_path('logs/laravel.log');
        $dir = dirname($logPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        File::put($logPath, "[2026-08-30 12:00:00] local.INFO: Application booted successfully\n[2026-08-30 12:01:00] local.ERROR: SQL timeout error connection failed\n");

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'laravel',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'channel',
            'count',
            'filters',
            'logs',
        ]);

        $this->assertEquals('laravel', $response->json('channel'));
        $this->assertGreaterThanOrEqual(1, $response->json('count'));
    }

    public function test_level_filtering_returns_only_matching_levels(): void
    {
        $logPath = storage_path('logs/laravel.log');
        File::put($logPath, "[2026-08-30 12:00:00] local.INFO: Booted\n[2026-08-30 12:01:00] local.ERROR: Database error occurred\n[2026-08-30 12:02:00] local.WARNING: Memory near limit\n");

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'laravel',
            'level'   => 'error',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $logs = $response->json('logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('ERROR', $logs[0]['level']);
        $this->assertStringContainsString('Database error', $logs[0]['message']);
    }

    public function test_grep_filtering_matches_substring(): void
    {
        $logPath = storage_path('logs/whatsapp_debug.log');
        $dir = dirname($logPath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        File::put($logPath, "[2026-08-30 12:00:00] [INFO] [WEBHOOK_RECEIVED] {\"chat_id\": 101}\n[2026-08-30 12:01:00] [ERROR] [AI_GATEWAY_RESOLVE_FAILED] {\"reason\": \"No key\"}\n");

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'whatsapp',
            'grep'    => 'AI_GATEWAY',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $logs = $response->json('logs');
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('AI_GATEWAY_RESOLVE_FAILED', $logs[0]['message']);
    }

    public function test_sensitive_tokens_are_scrubbed(): void
    {
        $logPath = storage_path('logs/laravel.log');
        File::put($logPath, "[2026-08-30 12:00:00] local.ERROR: Failed API call with sk-proj-1234567890abcdef12345678 and Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9\n");

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'laravel',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $logs = $response->json('logs');
        $this->assertNotEmpty($logs);
        $this->assertStringNotContainsString('sk-proj-1234567890abcdef12345678', $logs[0]['message']);
        $this->assertStringContainsString('[REDACTED_API_KEY]', $logs[0]['message']);
        $this->assertStringContainsString('[REDACTED_BEARER_TOKEN]', $logs[0]['message']);
    }

    public function test_system_db_channel_reads_system_logs(): void
    {
        SystemLog::create([
            'type'    => 'error',
            'message' => 'Failed to connect to SMTP server',
            'source'  => 'PlatformSmtpConfig',
            'details' => 'Connection refused on port 587',
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'system_db',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $this->assertEquals('system_db', $response->json('channel'));
        $logs = $response->json('logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('ERROR', $logs[0]['level']);
        $this->assertStringContainsString('SMTP server', $logs[0]['message']);
    }

    public function test_ai_requests_channel_reads_ai_logs(): void
    {
        $company = Company::factory()->create();

        AiRequestLog::create([
            'company_id'        => $company->id,
            'model'             => 'gpt-4o-mini',
            'use_case'          => 'chat_reply',
            'prompt_tokens'     => 100,
            'completion_tokens' => 50,
            'total_tokens'      => 150,
            'estimated_cost_usd'=> 0.0002,
            'latency_ms'        => 350,
            'success'           => true,
            'http_status'       => 200,
            'created_at'        => now(),
        ]);

        $response = $this->withHeaders([
            'X-Deploy-Agent-Key' => 'test-agent-key-xyz',
        ])->postJson('/logs/agent', [
            'channel' => 'ai_requests',
            'lines'   => 10,
        ]);

        $response->assertOk();
        $this->assertEquals('ai_requests', $response->json('channel'));
        $logs = $response->json('logs');
        $this->assertCount(1, $logs);
        $this->assertEquals('INFO', $logs[0]['level']);
        $this->assertStringContainsString('gpt-4o-mini', $logs[0]['message']);
    }
}
