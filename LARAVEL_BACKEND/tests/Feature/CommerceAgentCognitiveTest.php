<?php



namespace Tests\Feature;



use App\Models\AiProvider;

use App\Models\CognitiveEpisode;

use App\Models\Company;

use App\Models\CompanySetting;

use App\Models\KnowledgeArtifact;

use App\Models\Message;

use App\Models\PlatformIntelligencePattern;

use App\Models\Subscription;

use App\Models\User;

use App\Models\WhatsAppAccount;

use App\Services\Agent\Cognitive\BusinessDnaService;

use App\Services\Agent\Cognitive\CognitivePipelineService;

use App\Services\Agent\Cognitive\ExecutivePlanningService;

use App\Services\Agent\Cognitive\KnowledgeCompressionService;

use App\Services\Agent\Cognitive\PerceptionService;

use App\Services\Agent\Cognitive\SelfCritiqueService;

use App\Services\Agent\Cognitive\SimulationService;

use App\Services\Agent\Cognitive\StrategicMemoryService;

use App\Services\Agent\Cognitive\ToolProposalService;

use App\Services\AI\AiModelResolver;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Laravel\Sanctum\Sanctum;

use Tests\TestCase;



class CommerceAgentCognitiveTest extends TestCase

{

    use RefreshDatabase;



    protected function setUp(): void

    {

        parent::setUp();

        config(['agent.company.reasoning_enabled' => false]);

        $provider = AiProvider::where('slug', 'openai')->firstOrFail();

        $provider->update(['api_key' => 'sk-cognitive', 'is_enabled' => true]);

        AiModelResolver::clearCache();

    }



    private function cognitiveCompany(): Company

    {

        $company = Company::create([

            'name' => 'Cognitive Co',

            'email' => 'cognitive@test.local',

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

            'auto_reply_enabled' => true,

            'business_dna' => [

                'tone' => 'luxury and calm',

                'values' => ['quality', 'discretion'],

                'risk_tolerance' => 'low',

            ],

        ]);

        WhatsAppAccount::create([

            'company_id' => $company->id,

            'phone_number_id' => 'ph-cog',

            'whatsapp_business_account_id' => 'waba-cog',

            'access_token' => 'tok',

            'status' => 'active',

            'onboarding_status' => 'active',

        ]);



        return $company->fresh(['settings']);

    }



    public function test_perception_extracts_disappointed_wrong_product(): void

    {

        $company = $this->cognitiveCompany();

        $perception = app(PerceptionService::class)->perceive(

            $company,

            'I wanted the black one 😔',

            '254711122233',

        );



        $this->assertSame('disappointed', $perception['emotion']);

        $this->assertSame('wrong product', $perception['topic']);

        $this->assertSame('possible return', $perception['risk']);

    }



    public function test_cognitive_pipeline_persists_episode(): void

    {

        $company = $this->cognitiveCompany();

        $chat = \App\Models\Chat::create([

            'company_id' => $company->id,

            'customer_phone' => '254711122233',

            'customer_name' => 'Jane',

        ]);



        $result = app(CognitivePipelineService::class)->processTurn(

            $company, $chat, '254711122233', 'Jane', 'Do you have laptops?',

        );



        $this->assertArrayHasKey('episode_id', $result);

        $this->assertGreaterThan(0, $result['confidence']);

        $this->assertDatabaseHas('cognitive_episodes', [

            'company_id' => $company->id,

            'chat_id' => $chat->id,

        ]);

    }



    public function test_self_critique_adds_empathy_for_disappointed_customer(): void

    {

        $company = $this->cognitiveCompany();

        $context = [

            'perception' => [

                'emotion' => 'disappointed',

                'topic' => 'wrong product',

                'risk' => 'possible return',

            ],

        ];



        $result = app(SelfCritiqueService::class)->review(

            $company,

            'We can send the correct item tomorrow.',

            $context,

        );



        $this->assertFalse($result['passed']);

        $this->assertStringContainsString('sorry', mb_strtolower($result['rewritten'] ?? ''));

    }



    public function test_strategic_memory_stored_and_retrieved(): void

    {

        $company = $this->cognitiveCompany();

        app(StrategicMemoryService::class)->store(

            $company->id,

            'negotiation',

            'Volume discount retained customer',

            'Customer asked for competitor price',

            '8% discount secured 2-year retention',

            85,

        );



        $prompt = app(StrategicMemoryService::class)->getForPrompt($company->id);

        $this->assertStringContainsString('negotiation', $prompt);

        $this->assertDatabaseHas('strategic_memories', ['company_id' => $company->id]);

    }



    public function test_business_dna_uses_company_settings(): void

    {

        $company = $this->cognitiveCompany();

        $dna = app(BusinessDnaService::class)->resolve($company);

        $this->assertSame('luxury and calm', $dna['tone']);

    }



    public function test_executive_plan_breaks_down_revenue_goal(): void

    {

        $company = $this->cognitiveCompany();

        $result = app(ExecutivePlanningService::class)->createPlan(

            $company,

            'I want to double revenue this year',

        );



        $this->assertDatabaseHas('executive_plans', ['company_id' => $company->id]);

        $this->assertArrayHasKey('streams', $result['breakdown']);

        $this->assertGreaterThanOrEqual(3, count($result['breakdown']['streams']));

    }



    public function test_simulation_compares_discount_scenarios(): void

    {

        $company = $this->cognitiveCompany();

        $result = app(SimulationService::class)->simulate($company, 'discount', ['discount_pct' => 10]);



        $this->assertGreaterThanOrEqual(2, count($result['scenarios']));

        $this->assertDatabaseHas('cognitive_simulations', ['company_id' => $company->id]);

    }



    public function test_platform_patterns_seeded_without_tenant_data(): void

    {

        $this->assertGreaterThanOrEqual(1, PlatformIntelligencePattern::count());

        $pattern = PlatformIntelligencePattern::first();

        $this->assertNotEmpty($pattern->description);

        $this->assertNull($pattern->getAttribute('company_id'));

    }



    public function test_knowledge_compression_creates_artifact_from_confusion(): void

    {

        $company = $this->cognitiveCompany();

        $chat = \App\Models\Chat::create([

            'company_id' => $company->id,

            'customer_phone' => '254700000001',

            'customer_name' => 'A',

        ]);



        for ($i = 0; $i < 4; $i++) {

            Message::create([

                'chat_id' => $chat->id,

                'sender' => 'customer',

                'content' => 'What is the difference between model A and model B?',

            ]);

        }



        $artifacts = app(KnowledgeCompressionService::class)->compressForCompany($company);

        $this->assertNotEmpty($artifacts);

        $this->assertDatabaseHas('knowledge_artifacts', [

            'company_id' => $company->id,

            'artifact_type' => 'comparison_guide',

        ]);

    }



    public function test_cognitive_dashboard_api(): void

    {

        $company = $this->cognitiveCompany();

        $user = User::factory()->create([

            'company_id' => $company->id,

            'role' => 'company_owner',

            'email_verified_at' => now(),

        ]);

        Sanctum::actingAs($user);



        $this->getJson('/api/company/cognitive-ai/dashboard')

            ->assertOk()

            ->assertJsonStructure([

                'architecture', 'workforce', 'forecast', 'causalAnalysis', 'counts',

            ]);

    }



    public function test_orchestrator_uses_cognitive_route_and_trust_confidence(): void

    {

        $company = $this->cognitiveCompany();

        $chat = \App\Models\Chat::create([

            'company_id' => $company->id,

            'customer_phone' => '254711122233',

            'customer_name' => 'Test',

        ]);



        $mock = \Mockery::mock(\App\Services\Agent\AgentChatService::class);

        $mock->shouldReceive('completeWithTools')->once()->andReturn(new \App\Services\AI\OpenAiChatResult(

            content: 'Yes, we have laptops in stock!',

            success: true,

            model: 'gpt-4o-mini',

        ));

        $this->app->instance(\App\Services\Agent\AgentChatService::class, $mock);



        $result = app(\App\Services\Agent\CommerceAgentOrchestrator::class)->run(

            $company, $chat, '254711122233', 'Test', 'Do you have laptops?',

        );



        $this->assertSame('agent_os', $result['route']);

        $episode = CognitiveEpisode::where('company_id', $company->id)->first();

        $this->assertNotNull($episode);

        $this->assertNotNull($episode->confidence);

    }



    public function test_tool_proposal_detects_repeated_chain(): void
    {
        $company = $this->cognitiveCompany();

        foreach ([1, 2] as $round) {
            $chat = \App\Models\Chat::create([
                'company_id' => $company->id,
                'customer_phone' => '25470000000'.$round,
                'customer_name' => 'Customer '.$round,
            ]);
            \App\Models\AgentToolInvocation::create([
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'tool_name' => 'search_products',
                'arguments' => ['query' => 'laptop'],
                'result' => ['ok' => true],
                'duration_ms' => 10,
                'success' => true,
                'created_at' => now(),
            ]);
            \App\Models\AgentToolInvocation::create([
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'tool_name' => 'process_order_message',
                'arguments' => [],
                'result' => ['ok' => true],
                'duration_ms' => 10,
                'success' => true,
                'created_at' => now(),
            ]);
        }

        $proposals = app(ToolProposalService::class)->detectForCompany($company->id);
        $this->assertNotEmpty($proposals);
        $this->assertDatabaseHas('tool_proposals', ['company_id' => $company->id]);
    }
}

