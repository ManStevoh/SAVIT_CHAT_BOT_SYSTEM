<?php

namespace Tests\Feature;

use App\Models\AiRequestLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AIUsageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_ai_usage_endpoint_requires_admin(): void
    {
        $this->getJson('/api/admin/ai-usage')->assertUnauthorized();
    }

    public function test_ai_usage_returns_real_log_metrics(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::create([
            'name' => 'Acme',
            'email' => 'acme@example.test',
        ]);

        AiRequestLog::create([
            'company_id' => $company->id,
            'use_case' => 'whatsapp',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'latency_ms' => 800,
            'success' => true,
            'http_status' => 200,
            'created_at' => now(),
        ]);

        AiRequestLog::create([
            'company_id' => $company->id,
            'use_case' => 'growth',
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => 200,
            'completion_tokens' => 100,
            'total_tokens' => 300,
            'latency_ms' => 1200,
            'success' => false,
            'http_status' => 500,
            'error_message' => 'Server error',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/ai-usage?period=7d');
        $response->assertOk()
            ->assertJsonPath('totalRequests', 2)
            ->assertJsonPath('totalTokens', 450)
            ->assertJsonPath('successRate', 50)
            ->assertJsonPath('modelUsage.0.model', 'gpt-4o-mini')
            ->assertJsonPath('usageByCompany.0.companyName', 'Acme');
    }
}
