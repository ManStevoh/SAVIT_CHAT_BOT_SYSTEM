<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySetupStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    /**
     * @return array{company: Company, owner: User}
     */
    private function freshCompany(array $companyOverrides = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Setup Co',
            'email' => 'setup@test.local',
            'phone' => null,
            'status' => 'active',
            'storefront_enabled' => false,
            'store_slug' => null,
        ], $companyOverrides));

        CompanySetting::create([
            'company_id' => $company->id,
            'display_currency' => 'KES',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(13),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return compact('company', 'owner');
    }

    public function test_new_company_setup_status_is_mostly_incomplete(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->freshCompany();
        Sanctum::actingAs($owner);

        $this->getJson('/api/company/setup-status')
            ->assertOk()
            ->assertJsonPath('dismissed', false)
            ->assertJsonPath('isComplete', false)
            ->assertJsonPath('completedCount', 0)
            ->assertJsonPath('totalCount', 5)
            ->assertJsonPath('steps.0.id', 'whatsapp')
            ->assertJsonPath('steps.0.done', false)
            ->assertJsonPath('steps.1.id', 'product')
            ->assertJsonPath('steps.1.done', false)
            ->assertJsonPath('steps.2.id', 'payments')
            ->assertJsonPath('steps.2.done', false)
            ->assertJsonPath('steps.3.id', 'business')
            ->assertJsonPath('steps.3.done', false)
            ->assertJsonPath('steps.4.id', 'storefront')
            ->assertJsonPath('steps.4.done', false);
    }

    public function test_setup_steps_flip_when_configured(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->freshCompany([
            'phone' => '254700000111',
            'storefront_enabled' => true,
            'store_slug' => 'setup-co',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pnid_test',
            'access_token' => 'test_token',
            'verify_token' => 'verify_token',
            'status' => 'active',
            'onboarding_status' => 'active',
            'display_phone_number' => '+254700000111',
        ]);

        Product::create([
            'company_id' => $company->id,
            'name' => 'Starter Tee',
            'slug' => 'starter-tee',
            'price' => 1000,
        ]);

        $company->settings?->update([
            'orders_accept_mpesa' => true,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/company/setup-status')
            ->assertOk()
            ->assertJsonPath('isComplete', true)
            ->assertJsonPath('completedCount', 5)
            ->assertJsonPath('percent', 100)
            ->assertJsonPath('steps.0.done', true)
            ->assertJsonPath('steps.1.done', true)
            ->assertJsonPath('steps.2.done', true)
            ->assertJsonPath('steps.3.done', true)
            ->assertJsonPath('steps.4.done', true);
    }

    public function test_dismiss_setup_checklist(): void
    {
        ['owner' => $owner] = $this->freshCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/setup-status/dismiss')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('dismissed', true);

        $this->getJson('/api/company/setup-status')
            ->assertOk()
            ->assertJsonPath('dismissed', true);
    }
}
