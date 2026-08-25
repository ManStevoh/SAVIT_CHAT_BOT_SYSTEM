<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyNotification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionExpiredAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company: Company, owner: User}
     */
    private function expiredCompanyOwner(): array
    {
        $company = Company::create([
            'name' => 'Expired Co',
            'email' => 'expired@test.local',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'expired',
            'start_date' => now()->subDays(40),
            'end_date' => now()->subDay(),
            'amount' => 29,
            'billing_cycle' => 'monthly',
        ]);

        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        return compact('company', 'owner');
    }

    public function test_expired_company_can_load_subscription_shell_endpoints(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->expiredCompanyOwner();

        CompanyNotification::create([
            'company_id' => $company->id,
            'title' => 'Renew soon',
            'body' => 'Your plan ended.',
            'type' => 'info',
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/company/subscription')
            ->assertOk();

        $this->getJson('/api/company/subscription/invoices')
            ->assertOk();

        $this->getJson('/api/company/subscription/usage')
            ->assertOk();

        // Navbar fetch — must not 403 or the SPA redirects into a reload loop.
        $this->getJson('/api/company/notifications')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1);
    }

    public function test_expired_company_is_blocked_from_regular_company_apis(): void
    {
        ['owner' => $owner] = $this->expiredCompanyOwner();
        Sanctum::actingAs($owner);

        $this->getJson('/api/company/products')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_expired');

        $this->getJson('/api/company/chats')
            ->assertForbidden()
            ->assertJsonPath('code', 'subscription_expired');
    }
}
