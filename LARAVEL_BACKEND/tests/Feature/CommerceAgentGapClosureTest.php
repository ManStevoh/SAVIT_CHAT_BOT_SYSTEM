<?php

namespace Tests\Feature;

use App\Models\CommerceAgentEvent;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\Cognitive\CognitivePipelineService;
use App\Services\Agent\Events\CommerceEventHandler;
use App\Services\Agent\Integrations\ConnectorRegistry;
use App\Services\Agent\Specialists\CommerceSpecialistOrchestrator;
use Database\Seeders\EnterprisePlatformSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentGapClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->seed(EnterprisePlatformSeeder::class);
    }

    private function gapCompany(bool $council = false): array
    {
        $company = Company::create([
            'name' => 'Gap Co',
            'email' => 'gap@test.local',
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
            'agent_council_enabled' => $council,
            'digital_twin' => ['mission' => 'Serve customers fast'],
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company->fresh('settings'), 'owner' => $owner];
    }

    public function test_settings_api_exposes_digital_twin_and_council(): void
    {
        ['owner' => $owner] = $this->gapCompany(true);
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/company/settings');
        $response->assertOk();
        $response->assertJsonPath('digitalTwin.mission', 'Serve customers fast');
        $response->assertJsonPath('agentCouncilEnabled', true);
    }

    public function test_settings_api_updates_digital_twin_and_council(): void
    {
        ['owner' => $owner] = $this->gapCompany(false);
        Sanctum::actingAs($owner);

        $response = $this->putJson('/api/company/settings', [
            'digitalTwin' => ['mission' => 'Premium retail', 'brand_voice' => 'Calm luxury'],
            'agentCouncilEnabled' => true,
        ]);
        $response->assertOk();

        $settings = CompanySetting::where('company_id', $owner->company_id)->first();
        $this->assertSame('Premium retail', $settings->digital_twin['mission'] ?? null);
        $this->assertTrue($settings->agent_council_enabled);
    }

    public function test_commerce_events_owner_alerts_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->gapCompany();
        Sanctum::actingAs($owner);

        CommerceAgentEvent::create([
            'company_id' => $company->id,
            'event_type' => 'low_stock',
            'event_key' => 'low_stock:test',
            'payload' => ['summary' => 'Widget low'],
            'status' => 'open',
        ]);

        $list = $this->getJson('/api/company/commerce-events/owner-alerts');
        $list->assertOk();
        $list->assertJsonCount(1, 'alerts');

        $process = $this->postJson('/api/company/commerce-events/process-alerts');
        $process->assertOk();
        $this->assertSame('alerted', CommerceAgentEvent::first()->status);
    }

    public function test_cognitive_pipeline_skips_debate_when_council_disabled(): void
    {
        ['company' => $company] = $this->gapCompany(false);
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000099',
            'customer_name' => 'Buyer',
            'last_message' => 'hi',
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        config(['agent.company.reasoning_enabled' => false]);
        $result = app(CognitivePipelineService::class)->processTurn(
            $company,
            $chat,
            '254700000099',
            'Buyer',
            'Hello',
        );

        $this->assertArrayHasKey('chief', $result['debate']);
        $this->assertStringContainsString('Council disabled', $result['debate']['chief']);
    }

    public function test_specialist_consult_skipped_when_council_disabled(): void
    {
        ['company' => $company] = $this->gapCompany(false);
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000088',
            'customer_name' => 'Buyer',
            'status' => 'active',
            'last_message_at' => now(),
        ]);

        $views = app(CommerceSpecialistOrchestrator::class)->consultForTurn(
            $company,
            $chat,
            'Do you have red shoes?',
            ['topic' => 'product inquiry'],
        );

        $this->assertSame([], $views);
    }

    public function test_connector_registry_catalog(): void
    {
        $catalog = app(ConnectorRegistry::class)->catalog();
        $types = collect($catalog)->pluck('type')->all();
        $this->assertContains('weather', $types);
        $this->assertContains('crm_webhook', $types);
        $this->assertContains('erp_inventory', $types);
    }

    public function test_integrations_api_lists_connectors(): void
    {
        ['owner' => $owner] = $this->gapCompany();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/company/integrations');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(7, count($response->json('connectors')));
        $types = collect($response->json('connectors'))->pluck('type')->all();
        $this->assertContains('dhl_shipping', $types);
        $this->assertContains('sendy_logistics', $types);
    }
}
