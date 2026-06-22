<?php

namespace Tests\Feature;

use App\Models\AttributionEvent;
use App\Models\Company;
use App\Models\GrowthLearningPattern;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\ContentPredictionService;
use App\Services\Growth\GrowthPatternService;
use App\Services\Growth\PostPerformanceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Intel Co',
            'email' => 'intel@test.local',
            'status' => 'active',
            'growth_pilot_at' => now(),
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

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    public function test_post_performance_scorer_updates_score(): void
    {
        $user = $this->companyUser();
        $post = SocialPost::create([
            'company_id' => $user->company_id,
            'platform' => 'facebook',
            'content' => 'Limited time sale! Message us on WhatsApp to order.',
            'content_type' => 'text',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        AttributionEvent::create([
            'company_id' => $user->company_id,
            'social_post_id' => $post->id,
            'event_type' => 'revenue',
            'platform' => 'facebook',
            'revenue' => 15000,
        ]);

        $score = app(PostPerformanceScorer::class)->scorePost($post->fresh());
        $post->refresh();

        $this->assertGreaterThan(0, $score);
        $this->assertNotNull($post->performance_score);
        $this->assertNotEmpty($post->content_tags);
    }

    public function test_content_prediction_scores_draft(): void
    {
        $user = $this->companyUser();
        $post = SocialPost::create([
            'company_id' => $user->company_id,
            'platform' => 'facebook',
            'content' => 'Customer testimonial: we loved the service! WhatsApp us today.',
            'content_type' => 'text',
            'status' => 'draft',
        ]);

        $prediction = app(ContentPredictionService::class)->predictAndStore($post);
        $post->refresh();

        $this->assertGreaterThan(0, $prediction['score']);
        $this->assertNotNull($post->predicted_revenue_score);
        $this->assertContains('testimonial', $post->content_tags);
    }

    public function test_pattern_extraction_creates_learning_patterns(): void
    {
        $user = $this->companyUser();
        $company = Company::find($user->company_id);

        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'facebook',
            'content' => 'Flash sale 50% off! Order via WhatsApp now.',
            'content_type' => 'text',
            'content_tags' => ['promo', 'whatsapp_cta'],
            'status' => 'published',
            'published_at' => now()->subDays(2),
        ]);

        AttributionEvent::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'event_type' => 'revenue',
            'platform' => 'facebook',
            'revenue' => 25000,
        ]);

        $patterns = app(GrowthPatternService::class)->extractForCompany($company, 30);

        $this->assertNotEmpty($patterns);
        $this->assertGreaterThan(0, GrowthLearningPattern::where('company_id', $company->id)->count());
    }

    public function test_intelligence_api_endpoints(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/intelligence/content-mix')
            ->assertOk()
            ->assertJsonStructure(['plan' => ['weekOf', 'totalPosts', 'mix']]);

        $this->getJson('/api/company/growth/intelligence/score-drafts')->assertOk();

        $this->postJson('/api/company/growth/intelligence/patterns/extract', ['periodDays' => 30])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_smart_generate_endpoint(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/growth/content/generate-smart', [
            'count' => 2,
            'platform' => 'facebook',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_execute_mix_plan_endpoint(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/growth/intelligence/execute-mix')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['plan', 'posts']);
    }

    public function test_analytics_includes_intelligence_summary(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/analytics')
            ->assertOk()
            ->assertJsonStructure(['intelligence' => ['contentMix', 'pendingPatterns']]);
    }

    public function test_instagram_publish_requires_image(): void
    {
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $post = SocialPost::create([
            'company_id' => $user->company_id,
            'platform' => 'instagram',
            'content' => 'No image post',
            'status' => 'draft',
            'approved_at' => now(),
        ]);

        $this->postJson("/api/company/growth/posts/{$post->id}/publish")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
