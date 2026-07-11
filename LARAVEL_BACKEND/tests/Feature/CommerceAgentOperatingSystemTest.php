<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBrainSnapshot;
use App\Models\CompanyIntegration;
use App\Models\CompanySetting;
use App\Models\MessageVisionAnalysis;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\Integrations\ConnectorRegistry;
use App\Services\Agent\Vision\VisionOutboundImageService;
use App\Services\AI\KnowledgeChunkService;
use App\Services\AI\PgVectorSearchService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentOperatingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function osCompany(): array
    {
        $company = Company::create([
            'name' => 'OS Co',
            'email' => 'os@test.local',
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

    public function test_owner_analytics_investigations_list_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->osCompany();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/owner-analytics/investigate', [
            'question' => 'Why are sales down this week?',
            'period' => '7d',
        ])->assertCreated();

        $response = $this->getJson('/api/company/owner-analytics/investigations');
        $response->assertOk();
        $response->assertJsonCount(1, 'investigations');
    }

    public function test_company_brain_refresh_api(): void
    {
        ['owner' => $owner] = $this->osCompany();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/company/company-brain/refresh');
        $response->assertCreated();
        $this->assertGreaterThan(0, CompanyBrainSnapshot::count());
    }

    public function test_knowledge_vector_status_api(): void
    {
        ['owner' => $owner] = $this->osCompany();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/company/knowledge/vector-status');
        $response->assertOk();
        $response->assertJsonStructure(['vectorSearch' => ['driver', 'pgvector', 'message'], 'chunkCount']);
        $this->assertFalse($response->json('vectorSearch.pgvector'));
    }

    public function test_pgvector_unavailable_on_sqlite(): void
    {
        $status = app(PgVectorSearchService::class)->status();
        $this->assertSame('sqlite', $status['driver']);
        $this->assertFalse($status['pgvector']);
    }

    public function test_connector_catalog_includes_dhl_and_sendy(): void
    {
        $types = collect(app(ConnectorRegistry::class)->catalog())->pluck('type')->all();
        $this->assertContains('dhl_shipping', $types);
        $this->assertContains('sendy_logistics', $types);
        $this->assertGreaterThanOrEqual(7, count($types));
    }

    public function test_integrations_sync_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->osCompany();
        Sanctum::actingAs($owner);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        CompanyIntegration::create([
            'company_id' => $company->id,
            'connector_type' => 'crm_webhook',
            'status' => 'active',
            'config' => ['webhook_url' => 'https://crm.example.com/hook'],
        ]);

        $response = $this->postJson('/api/company/integrations/sync', [
            'connectorType' => 'crm_webhook',
        ]);
        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_vision_outbound_resolves_product_image(): void
    {
        ['company' => $company] = $this->osCompany();
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700011122',
            'customer_name' => 'Buyer',
            'status' => 'active',
        ]);
        $message = \App\Models\Message::create([
            'chat_id' => $chat->id,
            'content' => '[image]',
            'message_type' => 'image',
            'sender' => 'customer',
            'status' => 'received',
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Red Sneakers',
            'price' => 4500,
            'status' => 'active',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/test.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $analysis = MessageVisionAnalysis::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'message_id' => $message->id,
            'analysis_type' => 'product',
            'labels' => ['sneakers'],
            'product_matches' => [
                ['product_id' => $product->id, 'name' => 'Red Sneakers', 'label' => 'sneakers'],
            ],
            'warranty_detected' => false,
            'confidence' => 0.9,
        ]);

        $preview = app(VisionOutboundImageService::class)->resolveProductPreview($company, $analysis);
        $this->assertNotNull($preview);
        $this->assertStringContainsString('Red Sneakers', $preview['caption']);
        $this->assertNotEmpty($preview['url']);
    }

    public function test_knowledge_chunk_service_vector_status(): void
    {
        $status = app(KnowledgeChunkService::class)->vectorSearchStatus();
        $this->assertArrayHasKey('message', $status);
    }
}
