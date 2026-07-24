<?php

namespace Tests\Feature;

use App\Jobs\Agent\RunCommerceSpecialistJob;
use App\Models\AiProvider;
use App\Models\CommerceAgentEvent;
use App\Models\CommerceAgentRun;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\CustomerMemory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRelationship;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\AgentToolRegistry;
use App\Services\Agent\Company\ProductGraphService;
use App\Services\Agent\Events\CommerceEventDetector;
use App\Services\Agent\Specialists\CommerceSpecialistOrchestrator;
use App\Services\Agent\Specialists\InventorySpecialistService;
use App\Services\Agent\Specialists\SalesSpecialistService;
use App\Services\Agent\Specialists\SupportSpecialistService;
use App\Services\Agent\Tools\CheckDeliveryStatusTool;
use App\Services\Agent\Tools\GetProductRelationshipsTool;
use App\Services\Agent\Tools\GetWeatherTool;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'agent.company.reasoning_enabled' => false,
            'agent.specialists.use_llm' => false,
            'agent.specialists.consult_on_turn' => true,
        ]);
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-phase3', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function phase3Company(): Company
    {
        $company = Company::create([
            'name' => 'Phase3 Co',
            'email' => 'phase3@test.local',
            'status' => 'active',
            'industry' => 'retail',
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
        CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'agent_council_enabled' => true,
            'agent_proactive_enabled' => true,
            'auto_reply_enabled' => true,
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'ph-p3',
            'whatsapp_business_account_id' => 'waba-p3',
            'access_token' => 'tok',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        return $company->fresh(['settings']);
    }

    public function test_tool_registry_has_eighteen_tools(): void
    {
        $this->assertCount(20, app(AgentToolRegistry::class)->all());
        $names = array_map(fn ($t) => $t->name(), app(AgentToolRegistry::class)->all());
        $this->assertContains('get_product_relationships', $names);
        $this->assertContains('check_delivery_status', $names);
        $this->assertContains('get_weather', $names);
    }

    public function test_specialists_consult_on_turn_without_llm(): void
    {
        $company = $this->phase3Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711122233',
            'customer_name' => 'Buyer',
        ]);

        $views = app(CommerceSpecialistOrchestrator::class)->consultForTurn(
            $company, $chat, 'Do you have laptops in stock?', ['topic' => 'product inquiry', 'risk' => 'low'],
        );

        $this->assertArrayHasKey('sales', $views);
        $this->assertArrayHasKey('support', $views);
        $this->assertArrayHasKey('inventory', $views);
    }

    public function test_specialist_background_pipeline_creates_runs(): void
    {
        $company = $this->phase3Company();
        $runs = app(CommerceSpecialistOrchestrator::class)->dispatchBackgroundPipeline($company);

        $this->assertCount(3, $runs);
        $this->assertDatabaseCount('commerce_agent_runs', 3);
    }

    public function test_run_commerce_specialist_job_completes_inventory(): void
    {
        $company = $this->phase3Company();
        Product::create([
            'company_id' => $company->id,
            'name' => 'Low Item',
            'price' => 100,
            'stock' => 1,
            'status' => 'active',
        ]);

        $run = CommerceAgentRun::create([
            'company_id' => $company->id,
            'agent_type' => 'inventory',
            'status' => 'pending',
        ]);

        (new RunCommerceSpecialistJob($run->id))->handle(app(CommerceSpecialistOrchestrator::class));

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertArrayHasKey('low_stock_count', $run->output ?? []);
    }

    public function test_product_graph_relationships(): void
    {
        $company = $this->phase3Company();
        $laptop = Product::create([
            'company_id' => $company->id,
            'name' => 'Laptop Pro',
            'price' => 50000,
            'stock' => 10,
            'status' => 'active',
        ]);
        $mouse = Product::create([
            'company_id' => $company->id,
            'name' => 'Wireless Mouse',
            'price' => 2000,
            'stock' => 50,
            'status' => 'active',
        ]);
        ProductRelationship::create([
            'company_id' => $company->id,
            'product_id' => $laptop->id,
            'related_product_id' => $mouse->id,
            'relationship_type' => 'accessory',
            'label' => 'Recommended mouse',
        ]);

        $graph = app(ProductGraphService::class)->graphForProduct($company->id, $laptop->id);
        $this->assertTrue($graph['found']);
        $this->assertCount(1, $graph['relationships']);
        $this->assertSame('accessory', $graph['relationships'][0]['relationship_type']);
    }

    public function test_get_product_relationships_tool(): void
    {
        $company = $this->phase3Company();
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Phone X',
            'price' => 30000,
            'stock' => 5,
            'status' => 'active',
        ]);
        $case = Product::create([
            'company_id' => $company->id,
            'name' => 'Phone Case',
            'price' => 1500,
            'stock' => 20,
            'status' => 'active',
        ]);
        ProductRelationship::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'related_product_id' => $case->id,
            'relationship_type' => 'accessory',
        ]);

        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000001',
            'customer_name' => 'A',
        ]);
        $context = new \App\Services\Agent\AgentToolContext($company, $chat, '254700000001', 'A', 'cases?');

        $result = app(GetProductRelationshipsTool::class)->execute($context, ['product_name' => 'Phone X']);
        $this->assertTrue($result['found']);
        $this->assertNotEmpty($result['relationships']);
    }

    public function test_check_delivery_status_tool(): void
    {
        $company = $this->phase3Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000002',
            'customer_name' => 'B',
        ]);
        Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-P3-1',
            'customer_phone' => '254700000002',
            'customer_name' => 'B',
            'total' => 5000,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $context = new \App\Services\Agent\AgentToolContext($company, $chat, '254700000002', 'B', 'where is my order');
        $result = app(CheckDeliveryStatusTool::class)->execute($context, []);

        $this->assertNotEmpty($result['orders']);
        $this->assertSame('ORD-P3-1', $result['orders'][0]['order_number']);
    }

    public function test_get_weather_tool_with_mocked_api(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response([
                'results' => [['name' => 'Nairobi', 'country' => 'Kenya', 'latitude' => -1.28, 'longitude' => 36.82]],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'temperature_2m' => 22.5,
                    'relative_humidity_2m' => 60,
                    'weather_code' => 1,
                    'wind_speed_10m' => 10,
                ],
            ]),
        ]);

        $company = $this->phase3Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000003',
            'customer_name' => 'C',
        ]);
        $context = new \App\Services\Agent\AgentToolContext($company, $chat, '254700000003', 'C', 'weather?');

        $result = app(GetWeatherTool::class)->execute($context, ['city' => 'Nairobi']);
        $this->assertSame('Nairobi', $result['city']);
        $this->assertSame(22.5, $result['temperature_c']);
    }

    public function test_event_detector_finds_low_stock_and_birthday(): void
    {
        $company = $this->phase3Company();
        Product::create([
            'company_id' => $company->id,
            'name' => 'Critical SKU',
            'price' => 100,
            'stock' => 1,
            'status' => 'active',
        ]);
        CustomerMemory::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000099',
            'memory_key' => 'birthday',
            'memory_value' => now()->format('m-d'),
            'category' => 'personal',
            'confidence' => 0.9,
            'source' => 'agent',
        ]);

        $events = app(CommerceEventDetector::class)->detectForCompany($company);

        $this->assertNotEmpty($events);
        $this->assertDatabaseHas('commerce_agent_events', [
            'company_id' => $company->id,
            'event_type' => 'low_stock',
        ]);
        $this->assertDatabaseHas('commerce_agent_events', [
            'company_id' => $company->id,
            'event_type' => 'customer_birthday',
        ]);
    }

    public function test_specialist_api_lists_runs(): void
    {
        $company = $this->phase3Company();
        CommerceAgentRun::create([
            'company_id' => $company->id,
            'agent_type' => 'sales',
            'status' => 'completed',
            'output' => ['focus' => 'test'],
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/company/commerce-specialists/runs')
            ->assertOk()
            ->assertJsonStructure(['runs']);
    }

    public function test_specialist_api_triggers_pipeline(): void
    {
        $company = $this->phase3Company();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/company/commerce-specialists/run', [])
            ->assertStatus(202)
            ->assertJsonStructure(['runs']);

        $this->assertDatabaseCount('commerce_agent_runs', 3);
    }

    public function test_individual_specialist_background_analysis(): void
    {
        $company = $this->phase3Company();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711100000',
            'customer_name' => 'T',
        ]);

        $sales = app(SalesSpecialistService::class)->analyzeBackground($company);
        $support = app(SupportSpecialistService::class)->analyzeBackground($company);
        $inventory = app(InventorySpecialistService::class)->analyzeBackground($company);

        $this->assertArrayHasKey('recommendation', $sales);
        $this->assertArrayHasKey('recommendation', $support);
        $this->assertArrayHasKey('low_stock_count', $inventory);
    }
}
