<?php

namespace Tests\Feature;

use App\Jobs\Agent\GenerateDailyCommerceBriefJob;
use App\Jobs\Agent\RunConsciousnessSenseCycleJob;
use App\Models\BusinessTimelineEvent;
use App\Models\CommerceBrief;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\IntelligenceOutcome;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Agent\Company\CommerceMorningBriefService;
use App\Services\Agent\Consciousness\ConsciousnessSenseCycleService;
use App\Services\Agent\Consciousness\OwnerMorningBriefPushService;
use App\Services\Agent\Platform\OpportunityDetectionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase9Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config(['agent.owner_analytics.use_llm' => false]);
    }

    /** @return array{company: Company, owner: User} */
    private function phase9Company(array $settingsOverrides = []): array
    {
        $company = Company::create([
            'name' => 'Phase9 Co',
            'email' => 'phase9@test.local',
            'phone' => '254700000001',
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
        CompanySetting::create(array_merge([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'auto_reply_enabled' => true,
            'agent_morning_brief_whatsapp_enabled' => false,
        ], $settingsOverrides));
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'phone' => '254711111111',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    public function test_consciousness_sense_cycle_records_timeline(): void
    {
        ['company' => $company] = $this->phase9Company();

        $result = app(ConsciousnessSenseCycleService::class)->sense($company->fresh());

        $this->assertTrue($result['sensed'] ?? false);
        $this->assertGreaterThan(
            0,
            BusinessTimelineEvent::where('company_id', $company->id)->where('event_type', 'consciousness_sense')->count()
        );
        $this->assertNotNull($company->fresh()->settings?->consciousness_last_sensed_at);
    }

    public function test_consciousness_sense_cycle_job_runs(): void
    {
        ['company' => $company] = $this->phase9Company();

        (new RunConsciousnessSenseCycleJob($company->id))->handle(app(ConsciousnessSenseCycleService::class));

        $this->assertGreaterThan(
            0,
            BusinessTimelineEvent::where('company_id', $company->id)->where('event_type', 'consciousness_sense')->count()
        );
    }

    public function test_morning_brief_seeds_outcomes(): void
    {
        ['company' => $company] = $this->phase9Company();

        Product::create([
            'company_id' => $company->id,
            'name' => 'Low Stock Item',
            'price' => 100,
            'stock' => 1,
            'status' => 'active',
        ]);

        config(['agent.owner_analytics.use_llm' => false]);
        $brief = app(CommerceMorningBriefService::class)->generateForCompany($company);

        $this->assertNotNull($brief);
        $this->assertGreaterThan(
            0,
            IntelligenceOutcome::where('company_id', $company->id)->where('source_type', 'brief')->count()
        );
    }

    public function test_morning_brief_whatsapp_push_when_enabled(): void
    {
        ['company' => $company] = $this->phase9Company([
            'agent_morning_brief_whatsapp_enabled' => true,
            'owner_whatsapp_phone' => '254722222222',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pnid-test',
            'whatsapp_business_account_id' => 'waba-test',
            'access_token' => 'test-token',
            'status' => 'active',
        ]);

        $brief = CommerceBrief::create([
            'company_id' => $company->id,
            'brief_date' => now()->toDateString(),
            'summary' => 'Good morning. Sales look steady.',
            'recommendations' => ['Restock low items.'],
        ]);

        $this->mock(\App\Services\WhatsAppMessageSenderService::class, function ($mock) {
            $mock->shouldReceive('sendText')->once()->andReturn(['success' => true, 'message_id' => 'wamid.test']);
        });

        $pushed = app(OwnerMorningBriefPushService::class)->pushForCompany($company, $brief);

        $this->assertTrue($pushed);
        $this->assertNotNull($brief->fresh()->pushed_to_owner_at);
    }

    public function test_opportunity_seeds_outcome(): void
    {
        ['company' => $company] = $this->phase9Company();

        Product::create([
            'company_id' => $company->id,
            'name' => 'Slow Mover',
            'price' => 50,
            'stock' => 20,
            'status' => 'active',
        ]);

        app(OpportunityDetectionService::class)->detectForCompany($company);

        $this->assertGreaterThan(
            0,
            IntelligenceOutcome::where('company_id', $company->id)->where('source_type', 'opportunity')->count()
        );
    }

    public function test_intelligence_outcomes_list_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase9Company();
        Sanctum::actingAs($owner);

        IntelligenceOutcome::create([
            'company_id' => $company->id,
            'source_type' => 'brief',
            'source_id' => 1,
            'recommendation_key' => 'abc123',
            'recommended_action' => 'Restock low inventory items.',
            'outcome' => 'pending',
        ]);

        $this->getJson('/api/company/intelligence/outcomes?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'outcomes');
    }

    public function test_daily_brief_job_pushes_whatsapp_when_enabled(): void
    {
        ['company' => $company] = $this->phase9Company([
            'agent_morning_brief_whatsapp_enabled' => true,
            'owner_whatsapp_phone' => '254733333333',
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pnid-test2',
            'whatsapp_business_account_id' => 'waba-test2',
            'access_token' => 'test-token',
            'status' => 'active',
        ]);

        $this->mock(\App\Services\WhatsAppMessageSenderService::class, function ($mock) {
            $mock->shouldReceive('sendText')->once()->andReturn(['success' => true, 'message_id' => 'wamid.test2']);
        });

        (new GenerateDailyCommerceBriefJob($company->id))->handle(
            app(CommerceMorningBriefService::class),
            app(OwnerMorningBriefPushService::class),
        );

        $brief = CommerceBrief::where('company_id', $company->id)->first();
        $this->assertNotNull($brief);
        $this->assertNotNull($brief->pushed_to_owner_at);
    }
}
