<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_index_requires_admin(): void
    {
        $this->getJson('/api/admin/companies')->assertUnauthorized();

        $owner = User::factory()->create([
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/admin/companies')->assertForbidden();
    }

    public function test_index_returns_frontend_company_list_shape(): void
    {
        $active = Company::create([
            'name' => 'Acme Foods',
            'email' => 'acme@test.local',
            'phone' => '+254700000001',
            'plan' => 'growth',
            'status' => 'active',
        ]);
        Company::create([
            'name' => 'Pending Shop',
            'email' => 'pending@test.local',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/companies')
            ->assertOk()
            ->assertJsonIsArray()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'id' => (string) $active->id,
                'name' => 'Acme Foods',
                'email' => 'acme@test.local',
                'phone' => '+254700000001',
                'plan' => 'growth',
                'status' => 'active',
                'totalChats' => 0,
                'totalOrders' => 0,
                'whatsappConnected' => false,
            ]);

        $first = $response->json('0');
        $this->assertIsString($first['id']);
        $this->assertIsString($first['name']);
        $this->assertIsInt($first['totalChats']);
        $this->assertIsInt($first['totalOrders']);
        $this->assertArrayHasKey('createdAt', $first);
        $this->assertArrayHasKey('isGrowthPilot', $first);
        $this->assertArrayHasKey('growthDemoMode', $first);
    }

    public function test_index_filters_by_search_and_status(): void
    {
        Company::create(['name' => 'Nairobi Bites', 'email' => 'nairobi@test.local', 'status' => 'active']);
        Company::create(['name' => 'Mombasa Grill', 'email' => 'mombasa@test.local', 'status' => 'suspended']);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/companies?search=Nairobi')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Nairobi Bites');

        $this->getJson('/api/admin/companies?status=suspended')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Mombasa Grill');
    }

    public function test_show_returns_the_same_company_shape(): void
    {
        $company = Company::create([
            'name' => 'Show Co',
            'email' => 'show@test.local',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->admin());

        $this->getJson("/api/admin/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('id', (string) $company->id)
            ->assertJsonPath('name', 'Show Co')
            ->assertJsonPath('totalChats', 0)
            ->assertJsonPath('totalOrders', 0);
    }

    public function test_companies_page_declares_hooks_before_early_returns(): void
    {
        $source = file_get_contents(resource_path('js/Pages/admin/companies/page.tsx'));
        $this->assertNotFalse($source);

        $fnStart = strpos($source, 'export default function AdminCompaniesPage');
        $this->assertNotFalse($fnStart, 'Expected AdminCompaniesPage export.');

        $loadingReturn = strpos($source, 'if (isLoading && !companies)', $fnStart);
        $this->assertNotFalse($loadingReturn, 'Expected loading early return in companies page.');

        $callbackCount = 0;
        $offset = $fnStart;
        while (($pos = strpos($source, 'useCallback', $offset)) !== false) {
            $callbackCount++;
            $this->assertLessThan(
                $loadingReturn,
                $pos,
                'useCallback after the loading early return crashes React when companies finish loading (Rendered more hooks than during the previous render).'
            );
            $offset = $pos + 1;
        }

        $this->assertGreaterThanOrEqual(4, $callbackCount, 'Expected useCallback usages on the companies page.');
    }
}
