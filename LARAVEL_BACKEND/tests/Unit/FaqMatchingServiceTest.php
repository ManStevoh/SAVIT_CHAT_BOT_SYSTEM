<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Faq;
use App\Services\Conversation\FaqMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_best_returns_faq_when_keyword_is_strong(): void
    {
        config(['conversation.faq_direct_answer_min_score' => 72]);

        $company = Company::create([
            'name' => 'Test Shop',
            'email' => 'shop@example.test',
        ]);

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Refund policy',
            'answer' => 'We refund within 14 days.',
            'keywords' => ['refund', 'returns'],
            'is_active' => true,
        ]);

        $svc = app(FaqMatchingService::class);
        $result = $svc->matchBest($company, 'What is your refund policy?', 'what is your refund policy?');

        $this->assertNotNull($result);
        $this->assertSame('We refund within 14 days.', $result['answer']);
        $this->assertSame(95.0, $result['score']);
    }

    public function test_match_best_returns_null_when_overlap_is_weak(): void
    {
        config(['conversation.faq_direct_answer_min_score' => 72]);

        $company = Company::create([
            'name' => 'Other Shop',
            'email' => 'other@example.test',
        ]);

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Do you sell blue widgets?',
            'answer' => 'Yes, sometimes.',
            'keywords' => [],
            'is_active' => true,
        ]);

        $svc = app(FaqMatchingService::class);
        $result = $svc->matchBest($company, 'hello there', 'hello there');

        $this->assertNull($result);
    }
}
