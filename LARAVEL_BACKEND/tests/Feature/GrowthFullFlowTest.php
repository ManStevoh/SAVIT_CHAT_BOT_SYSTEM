<?php

namespace Tests\Feature;

use App\Models\AttributionLink;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Growth\AttributionService;
use App\Services\Growth\GrowthOptimizerService;
use App\Services\Growth\GrowthPatternService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthFullFlowTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Full Flow Co',
            'email' => 'flow@test.local',
            'status' => 'active',
            'phone' => '254700000001',
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

    public function test_end_to_end_attribution_generate_analytics_flow(): void
    {
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $generate = $this->postJson('/api/company/growth/content/generate', [
            'count' => 2,
            'platform' => 'facebook',
            'topic' => 'Summer sale',
        ]);
        $generate->assertOk()->assertJsonPath('success', true);
        $postId = $generate->json('posts.0.id');
        $this->assertNotEmpty($postId);

        $this->getJson('/api/company/growth/intelligence/score-drafts')->assertOk();

        $link = AttributionLink::where('social_post_id', $postId)->first();
        $this->assertNotNull($link);

        $redirect = $this->get('/g/'.$link->slug);
        $redirect->assertRedirect();

        $this->getJson('/api/company/growth/analytics?period=30d')
            ->assertOk()
            ->assertJsonStructure([
                'summary',
                'funnel',
                'topPosts',
                'intelligence' => ['contentMix', 'pendingPatterns'],
            ]);
    }

    public function test_learning_optimizer_pipeline(): void
    {
        $user = $this->companyUser();
        $company = Company::find($user->company_id);
        Sanctum::actingAs($user);

        $this->postJson('/api/company/growth/content/generate', [
            'count' => 1,
            'platform' => 'facebook',
        ])->assertOk();

        app(GrowthPatternService::class)->extractForCompany($company, 30);

        $mix = app(GrowthOptimizerService::class)->executeMixPlan($company);
        $this->assertNotEmpty($mix['plan']['mix']);

        $this->postJson('/api/company/growth/agents/run', ['platform' => 'facebook'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
