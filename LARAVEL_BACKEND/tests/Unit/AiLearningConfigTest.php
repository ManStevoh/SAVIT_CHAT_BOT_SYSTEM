<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\PlatformSetting;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\LearningPiiRedactor;
use App\Services\ConversationLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiLearningConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_can_disable_learning_globally(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => ['learningEnabled' => false],
        ]);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local']);
        CompanySetting::create(['company_id' => $company->id, 'learn_from_conversations' => true]);

        $config = app(AiLearningConfig::class);
        $this->assertFalse($config->companyCanLearn($company->fresh(['settings'])));
    }

    public function test_pii_redactor_masks_email_and_phone(): void
    {
        $redactor = app(LearningPiiRedactor::class);
        $out = $redactor->redact('Contact me at john@example.com or +254712345678');

        $this->assertStringContainsString('[email]', $out);
        $this->assertStringContainsString('[phone]', $out);
        $this->assertStringNotContainsString('john@example.com', $out);
    }

    public function test_learning_service_stores_redacted_sample(): void
    {
        PlatformSetting::create(['platform_name' => 'Test']);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local']);
        $service = app(ConversationLearningService::class);
        $service->storeSample(
            $company->id,
            'My email is a@b.com',
            'We will email you back at the address you provided soon.',
        );

        $sample = ConversationLearningSample::first();
        $this->assertNotNull($sample);
        $this->assertStringContainsString('[email]', $sample->customer_message);
    }

    public function test_retention_prunes_old_samples(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => ['retentionDays' => 30],
        ]);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local']);
        $sample = ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'old question',
            'assistant_reply' => 'old answer that is long enough to store',
            'question_fingerprint' => hash('xxh128', 'old'),
            'source' => 'openai',
        ]);
        $sample->forceFill(['created_at' => now()->subDays(60)])->save();

        $deleted = app(ConversationLearningService::class)->pruneExpiredForCompany($company->id);
        $this->assertSame(1, $deleted);
    }

    public function test_prompt_and_embedding_settings_from_platform_config(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => [
                'maxPromptTokens' => 8000,
                'embeddingModelKey' => 'text-embedding-3-large',
                'faqSemanticMinScore' => 0.88,
                'maxSamplesPerCompany' => 150,
            ],
        ]);
        AiLearningConfig::clearCache();

        $config = app(AiLearningConfig::class);
        $this->assertSame(8000, $config->maxPromptTokens());
        $this->assertSame('text-embedding-3-large', $config->embeddingModelKey());
        $this->assertSame(0.88, $config->faqSemanticMinScore());
        $this->assertSame(150, $config->maxSamplesPerCompany());
        $this->assertTrue($config->learningEmbeddingsEnabled());
        $this->assertSame(0.78, $config->learningSemanticMinScore());
    }
}
