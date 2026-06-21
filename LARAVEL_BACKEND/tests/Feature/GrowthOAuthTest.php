<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GrowthOauthState;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthOAuthTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'OAuth Co',
            'email' => 'oauth@test.local',
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

    public function test_oauth_config_endpoint(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/oauth/config')
            ->assertOk()
            ->assertJsonStructure(['callbackUrl', 'platforms']);
    }

    public function test_oauth_authorize_returns_url_when_configured(): void
    {
        Config::set('growth.oauth.meta.client_id', 'test-app-id');
        Config::set('growth.oauth.meta.client_secret', 'test-secret');

        Sanctum::actingAs($this->companyUser());

        $response = $this->getJson('/api/company/growth/oauth/facebook/authorize');
        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('facebook.com', $response->json('authorizeUrl'));
        $this->assertSame(1, GrowthOauthState::count());
    }

    public function test_oauth_authorize_fails_without_credentials(): void
    {
        Config::set('growth.oauth.meta.client_id', '');
        Config::set('growth.oauth.meta.client_secret', '');
        Config::set('whatsapp.embedded_signup_app_id', '');

        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/oauth/facebook/authorize')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
