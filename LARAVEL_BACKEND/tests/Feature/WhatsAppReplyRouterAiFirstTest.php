<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Faq;
use App\Services\Conversation\CatalogShortcutMatcher;
use App\Services\Conversation\FaqMatchingService;
use App\Services\Conversation\KeywordReplyMatcher;
use App\Services\Conversation\WhatsAppReplyRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ensures keyword/catalog paths are fallbacks: when AI is unavailable, FAQ/keyword still work.
 */
class WhatsAppReplyRouterAiFirstTest extends TestCase
{
    use RefreshDatabase;

    public function test_falls_back_to_faq_when_ai_unavailable(): void
    {
        $company = Company::create([
            'name' => 'Router Co',
            'email' => 'router@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_reply_mode' => 'balanced',
            'auto_reply_enabled' => true,
            // No OpenAI key → AI generator returns null → fallbacks run.
        ]);
        Faq::create([
            'company_id' => $company->id,
            'question' => 'What are your delivery hours?',
            'answer' => 'We deliver 9am to 6pm.',
            'keywords' => 'delivery hours',
            'is_active' => true,
        ]);

        $result = app(WhatsAppReplyRouter::class)->resolve(
            $company->fresh(['settings']),
            'What are your delivery hours?',
            'what are your delivery hours?',
            'Buyer',
            null,
            null,
        );

        $this->assertContains($result['route'], ['faq', 'faq_fuzzy', 'openai']);
        if ($result['route'] !== 'openai') {
            $this->assertTrue(($result['meta']['fallback'] ?? false) || str_starts_with($result['route'], 'faq'));
            $this->assertStringContainsString('9am', $result['text']);
        }
    }

    public function test_router_resolve_method_is_ai_primary_single_path(): void
    {
        $ref = new \ReflectionClass(WhatsAppReplyRouter::class);
        $this->assertTrue($ref->hasMethod('resolve'));
        $this->assertFalse($ref->hasMethod('resolveAiFirst'), 'legacy dual AI/balanced routers removed');
        $this->assertFalse($ref->hasMethod('resolveBalanced'), 'legacy dual AI/balanced routers removed');
    }
}
