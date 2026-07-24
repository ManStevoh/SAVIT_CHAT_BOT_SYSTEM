<?php

namespace Tests\Feature;

use App\Models\CompanyNotification;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationTrialAndConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        Mail::fake();
        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'platform_name' => 'RelayIQ',
            'allow_new_registrations' => true,
            'require_email_verification' => false,
        ]);
    }

    public function test_register_requires_terms_acceptance(): void
    {
        $this->postJson('/api/auth/register', [
            'companyName' => 'No Terms Co',
            'name' => 'Owner',
            'email' => 'noterms@test.local',
            'phone' => '254700000001',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => false,
        ])->assertStatus(422);
    }

    public function test_register_with_selected_trial_plan_starts_that_plan_trial(): void
    {
        $growth = Plan::where('slug', 'professional')->firstOrFail();
        $growth->update(['has_trial' => true, 'trial_days' => 14]);

        $res = $this->postJson('/api/auth/register', [
            'companyName' => 'Growth Trial Co',
            'name' => 'Owner',
            'email' => 'growth-trial@test.local',
            'phone' => '254700000002',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
            'marketingConsent' => true,
            'planId' => (string) $growth->id,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('trialStarted', true)
            ->assertJsonPath('trialPlan', 'professional')
            ->assertJsonPath('requiresPayment', false);

        $user = User::where('email', 'growth-trial@test.local')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertTrue($user->marketing_consent);
        $this->assertSame($growth->id, $user->selected_plan_id);

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $user->company_id,
            'plan' => 'professional',
            'status' => 'trial',
        ]);

        $this->assertDatabaseHas('company_notifications', [
            'company_id' => $user->company_id,
            'title' => 'Free trial started',
        ]);
    }

    public function test_admin_can_disable_trial_on_plan_and_signup_falls_back(): void
    {
        $growth = Plan::where('slug', 'professional')->firstOrFail();
        $growth->update(['has_trial' => false, 'trial_days' => null]);
        $starter = Plan::where('slug', 'starter')->firstOrFail();
        $starter->update(['has_trial' => true, 'trial_days' => 14]);

        $this->postJson('/api/auth/register', [
            'companyName' => 'Pay Growth Co',
            'name' => 'Owner',
            'email' => 'pay-growth@test.local',
            'phone' => '254700000003',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
            'marketingConsent' => false,
            'planId' => (string) $growth->id,
        ])->assertOk()
            ->assertJsonPath('requiresPayment', true)
            ->assertJsonPath('trialPlan', 'starter');

        $user = User::where('email', 'pay-growth@test.local')->firstOrFail();
        $this->assertFalse($user->marketing_consent);
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $user->company_id,
            'plan' => 'starter',
            'status' => 'trial',
        ]);
    }

    public function test_admin_users_api_exposes_consent_fields(): void
    {
        $this->postJson('/api/auth/register', [
            'companyName' => 'Consent Co',
            'name' => 'Owner',
            'email' => 'consent@test.local',
            'phone' => '254700000004',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'acceptTerms' => true,
            'marketingConsent' => true,
        ])->assertOk();

        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'consent@test.local',
                'marketingConsent' => true,
            ]);
    }

    public function test_public_plans_expose_has_trial(): void
    {
        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonStructure([['id', 'hasTrial', 'trialDays']]);
    }
}
