<?php

namespace Tests\Feature;

use App\Jobs\Growth\PrunePortfolioRecommendationsJob;
use App\Models\Company;
use App\Models\PortfolioRecommendation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Integrations Co',
            'email' => 'int@test.local',
            'status' => 'active',
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

    public function test_integrations_status_endpoint(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/integrations')
            ->assertOk()
            ->assertJsonStructure(['integrations' => [['provider', 'status', 'configured']]]);
    }

    public function test_connect_website_integration(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/growth/integrations/connect', [
            'provider' => 'website',
            'siteUrl' => 'https://example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('growth_integrations', [
            'provider' => 'website',
            'status' => 'connected',
        ]);
    }

    public function test_subscription_usage_includes_growth_limits(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/subscription/usage')
            ->assertOk()
            ->assertJsonPath('growth.aiPostsLimit', 100)
            ->assertJsonPath('growth.platformLimit', 3);
    }

    public function test_prune_old_portfolio_recommendations(): void
    {
        $old = PortfolioRecommendation::create([
            'company_id' => null,
            'recommendation_type' => 'portfolio_platform',
            'title' => 'Old',
            'body' => 'Stale recommendation',
            'confidence_score' => 50,
            'is_read' => true,
        ]);
        $old->forceFill(['created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120)])->saveQuietly();

        (new PrunePortfolioRecommendationsJob)->handle();

        $this->assertDatabaseMissing('portfolio_recommendations', ['title' => 'Old']);
    }
}
