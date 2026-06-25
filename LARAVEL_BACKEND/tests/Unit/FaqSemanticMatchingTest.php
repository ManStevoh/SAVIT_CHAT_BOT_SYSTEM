<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Faq;
use App\Models\PlatformSetting;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\FaqEmbeddingService;
use App\Services\Conversation\FaqMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FaqSemanticMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_semantic_match_returns_faq_when_lexical_fails(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => ['faqSemanticMinScore' => 0.8],
        ]);
        AiLearningConfig::clearCache();

        config([
            'conversation.faq_direct_answer_min_score' => 72,
        ]);

        $company = Company::create([
            'name' => 'Test Shop',
            'email' => 'shop@example.test',
        ]);

        $faq = Faq::create([
            'company_id' => $company->id,
            'question' => 'What are your delivery areas?',
            'answer' => 'We deliver across Dubai and Sharjah.',
            'is_active' => true,
            'question_embedding' => [1.0, 0.0, 0.0],
        ]);

        $embeddingMock = Mockery::mock(FaqEmbeddingService::class);
        $embeddingMock->shouldReceive('embedMessage')
            ->once()
            ->andReturn([0.99, 0.01, 0.0]);
        $embeddingMock->shouldReceive('matchSemantic')
            ->once()
            ->with(Mockery::on(fn ($f) => $f->id === $faq->id), Mockery::type('array'), 0.8)
            ->andReturn([
                'answer' => $faq->answer,
                'faq_id' => $faq->id,
                'score' => 92.0,
            ]);

        $this->app->instance(FaqEmbeddingService::class, $embeddingMock);

        $svc = app(FaqMatchingService::class);
        $result = $svc->matchBest($company, 'Do you ship to Sharjah?', 'do you ship to sharjah?');

        $this->assertNotNull($result);
        $this->assertSame('faq_semantic', $result['route']);
        $this->assertSame('We deliver across Dubai and Sharjah.', $result['answer']);
    }
}
