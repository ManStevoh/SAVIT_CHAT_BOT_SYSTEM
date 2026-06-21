<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthEngineTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Test Biz',
            'email' => 'biz@test.local',
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

    public function test_growth_analytics_endpoint_requires_auth(): void
    {
        $this->getJson('/api/company/growth/analytics')->assertUnauthorized();
    }

    public function test_growth_analytics_returns_structure(): void
    {
        Sanctum::actingAs($this->companyUser());

        $response = $this->getJson('/api/company/growth/analytics?period=30d');
        $response->assertOk()
            ->assertJsonStructure([
                'summary' => ['leads', 'revenue', 'clicks', 'orders'],
                'platformBreakdown',
                'topPosts',
                'contentIntelligence',
                'funnel',
                'limits',
            ]);
    }

    public function test_generate_content_creates_draft_posts(): void
    {
        Sanctum::actingAs($this->companyUser());

        $response = $this->postJson('/api/company/growth/content/generate', [
            'count' => 2,
            'platform' => 'facebook',
            'topic' => 'Scratch programming',
            'audience' => 'Parents',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('posts'));
    }

    public function test_attribution_redirect_tracks_click(): void
    {
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/growth/posts', [
            'platform' => 'facebook',
            'content' => 'Click me',
        ]);
        $create->assertOk();

        $this->get('/g/nonexistent-slug')->assertNotFound();

        $posts = $this->getJson('/api/company/growth/posts')->json();
        $trackingUrl = $posts[0]['trackingUrl'] ?? null;
        $this->assertNotNull($trackingUrl);

        preg_match('#/g/([a-z0-9]+)#', $trackingUrl, $m);
        $this->get('/g/'.$m[1])->assertRedirect();
    }
}
