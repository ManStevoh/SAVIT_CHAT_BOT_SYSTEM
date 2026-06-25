<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Company;
use App\Models\CompanyAiProvider;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\AiModelResolver;
use App\Services\AI\CompanyAiCredentialService;
use App\Services\Conversation\MessageLanguageDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiP3FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(Company $company, string $plan = 'starter'): User
    {
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    public function test_message_language_detector_finds_swahili(): void
    {
        $lang = app(MessageLanguageDetector::class)->detect('Habari, bei ya mzigo ni ngapi?');

        $this->assertSame('sw', $lang);
    }

    public function test_learning_review_queue_stores_pending_when_required(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => ['requireLearningReview' => true],
        ]);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id]);

        app(\App\Services\ConversationLearningService::class)->storeSample(
            $company->id,
            'What are your hours?',
            'We are open Monday to Friday from 9am to 6pm every week.',
            ConversationLearningSample::SOURCE_OPENAI,
        );

        $sample = ConversationLearningSample::first();
        $this->assertSame(ConversationLearningSample::STATUS_PENDING, $sample->status);
    }

    public function test_company_can_configure_byok_openai_key(): void
    {
        $provider = AiProvider::firstOrCreate(
            ['slug' => 'openai'],
            ['name' => 'OpenAI', 'is_enabled' => true, 'sort_order' => 0],
        );

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_credential_mode' => 'company_preferred',
        ]);

        Sanctum::actingAs($this->companyUser($company, 'professional'));

        $this->putJson('/api/company/ai-providers/openai', [
            'apiKey' => 'sk-test-company-key',
            'isEnabled' => true,
            'credentialMode' => 'company_preferred',
        ])->assertOk()->assertJsonPath('success', true);

        CompanyAiCredentialService::clearCacheForCompany($company->id);
        $cred = app(CompanyAiCredentialService::class)->resolve($company->fresh(['settings']), $provider);

        $this->assertSame('company', $cred['source']);
        $this->assertSame('sk-test-company-key', $cred['key']);
    }

    public function test_starter_cannot_configure_byok(): void
    {
        AiProvider::firstOrCreate(
            ['slug' => 'openai'],
            ['name' => 'OpenAI', 'is_enabled' => true, 'sort_order' => 0],
        );

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);

        Sanctum::actingAs($this->companyUser($company, 'starter'));

        $this->putJson('/api/company/ai-providers/openai', [
            'apiKey' => 'sk-test-company-key',
        ])->assertStatus(422)->assertJsonPath('code', 'plan_byok_restricted');
    }

    public function test_admin_can_approve_pending_learning_sample(): void
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        $sample = ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'Q',
            'assistant_reply' => 'Answer long enough for review queue testing here',
            'question_fingerprint' => hash('xxh128', 'q'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_PENDING,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/ai-learning/samples/{$sample->id}/review", [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(
            ConversationLearningSample::STATUS_APPROVED,
            $sample->fresh()->status,
        );
    }

    public function test_company_ai_usage_endpoint_returns_summary(): void
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        Sanctum::actingAs($this->companyUser($company));

        $this->getJson('/api/company/ai-usage')
            ->assertOk()
            ->assertJsonStructure(['summary' => ['totalRequests', 'platformBilledCostUsd'], 'byUseCase']);
    }
}
