<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyModuleInstallation;
use App\Models\CompanySetting;
use App\Models\MarketplaceModule;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\Platform\ExternalModuleToolBridge;
use App\Services\Agent\Platform\MarketplaceModuleService;
use App\Services\Agent\Platform\SkillModuleRegistry;
use Database\Seeders\MarketplaceModuleSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->seed(MarketplaceModuleSeeder::class);
    }

    /** @return array{company: Company, owner: User} */
    private function phase10Company(string $plan = 'professional'): array
    {
        $company = Company::create([
            'name' => 'Phase10 Co',
            'email' => 'phase10@test.local',
            'industry' => 'retail',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    public function test_marketplace_catalog_api(): void
    {
        ['owner' => $owner] = $this->phase10Company();
        Sanctum::actingAs($owner);

        $this->getJson('/api/company/marketplace/modules')
            ->assertOk()
            ->assertJsonStructure(['modules', 'installed'])
            ->assertJsonFragment(['moduleKey' => 'retail']);
    }

    public function test_install_and_uninstall_module(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase10Company();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/marketplace/modules/pharmacy/install')
            ->assertCreated();

        $this->assertDatabaseHas('company_module_installations', [
            'company_id' => $company->id,
            'module_key' => 'pharmacy',
            'status' => 'installed',
        ]);

        $this->deleteJson('/api/company/marketplace/modules/pharmacy/install')
            ->assertOk();

        $this->assertDatabaseMissing('company_module_installations', [
            'company_id' => $company->id,
            'module_key' => 'pharmacy',
        ]);
    }

    public function test_starter_plan_cannot_install_professional_module(): void
    {
        ['owner' => $owner] = $this->phase10Company('starter');
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/marketplace/modules/pharmacy/install')
            ->assertStatus(422);
    }

    public function test_installed_modules_change_prompt_addons(): void
    {
        ['company' => $company] = $this->phase10Company();
        app(MarketplaceModuleService::class)->install($company, 'pharmacy');

        $addon = app(SkillModuleRegistry::class)->promptAddonsForCompany($company);

        $this->assertStringContainsString('Pharmacy Assistant', $addon);
        $this->assertStringContainsString('prescription', strtolower($addon));
    }

    public function test_installed_modules_filter_tools(): void
    {
        ['company' => $company] = $this->phase10Company();
        app(MarketplaceModuleService::class)->install($company, 'retail');

        $definitions = app(AgentToolRegistry::class)->openAiDefinitionsForCompany($company);
        $names = array_map(fn ($d) => $d['function']['name'], $definitions);

        $this->assertContains('search_products', $names);
        $this->assertNotContains('issue_order_refund', $names);
    }

    public function test_agent_sdk_manifest_is_public(): void
    {
        $this->getJson('/api/agent-sdk/v1/manifest')
            ->assertOk()
            ->assertJsonPath('sdk_version', '1')
            ->assertJsonPath('name', 'SAVIT Agent SDK');
    }

    public function test_external_module_tool_webhook_execution(): void
    {
        ['company' => $company] = $this->phase10Company('enterprise');

        Http::fake([
            'https://agent.example.com/*' => Http::response(['result' => ['quote' => 1200, 'currency' => 'KES']], 200),
        ]);

        app(MarketplaceModuleService::class)->install($company, 'demo_procurement_agent', [
            'webhook_base_url' => 'https://agent.example.com/api/savit',
        ]);

        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000099',
            'customer_name' => 'Test Buyer',
            'status' => 'active',
        ]);

        $context = new \App\Services\Agent\AgentToolContext(
            company: $company,
            chat: $chat,
            customerPhone: '254700000099',
            customerName: null,
            incomingMessage: 'Need a supplier quote',
        );

        $result = app(ExternalModuleToolBridge::class)->execute(
            $company,
            'check_supplier_quote',
            $context,
            ['sku' => 'ABC', 'quantity' => 10],
        );

        $this->assertSame(1200, $result['quote'] ?? null);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/tools/check_supplier_quote'));
    }

    public function test_third_party_install_requires_webhook_url(): void
    {
        ['owner' => $owner] = $this->phase10Company('enterprise');
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/marketplace/modules/demo_procurement_agent/install')
            ->assertStatus(422);
    }

    public function test_marketplace_seeder_populates_modules(): void
    {
        $this->assertGreaterThan(5, MarketplaceModule::count());
        $this->assertNotNull(MarketplaceModule::where('module_key', 'healthcare')->first());
    }
}
