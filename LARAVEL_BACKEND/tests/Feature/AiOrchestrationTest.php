<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\AiUseCase;
use App\Services\AI\Classification\EntityExtractionService;
use App\Services\AI\Classification\IntentClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiModelResolver::clearCache();
    }

    private function enableOpenAi(): AiProvider
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-orchestration', 'is_enabled' => true]);

        return $provider->fresh();
    }

    public function test_reasoning_use_case_resolves_reasoning_capability(): void
    {
        $this->enableOpenAi();
        $company = Company::create(['name' => 'Orch Co', 'email' => 'orch@test.local']);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_model_mode' => 'auto',
        ]);

        $resolved = app(AiModelResolver::class)->resolve(
            $company->fresh('settings'),
            AiModel::CAPABILITY_CHAT,
            AiUseCase::AGENT_REASONING,
        );

        $this->assertNotNull($resolved);
        $this->assertSame(AiModel::CAPABILITY_REASONING, $resolved->model->capability);
        $this->assertSame('gpt-4o', $resolved->model->model_key);
    }

    public function test_whatsapp_fast_use_case_resolves_fast_chat(): void
    {
        $this->enableOpenAi();
        $company = Company::create(['name' => 'Fast Co', 'email' => 'fast@test.local']);

        $resolved = app(AiModelResolver::class)->resolve(
            $company,
            AiModel::CAPABILITY_CHAT,
            AiUseCase::WHATSAPP_FAST,
        );

        $this->assertNotNull($resolved);
        $this->assertSame(AiModel::CAPABILITY_FAST_CHAT, $resolved->model->capability);
    }

    public function test_stt_use_case_resolves_whisper(): void
    {
        $this->enableOpenAi();
        $company = Company::create(['name' => 'Voice Co', 'email' => 'voice@test.local']);

        $resolved = app(AiModelResolver::class)->resolve(
            $company,
            AiModel::CAPABILITY_STT,
            AiUseCase::SPEECH_TO_TEXT,
        );

        $this->assertNotNull($resolved);
        $this->assertSame('whisper-1', $resolved->model->model_key);
    }

    public function test_intent_classifier_detects_buy_intent(): void
    {
        $result = app(IntentClassificationService::class)->classify('I want to buy 2 chairs please');

        $this->assertSame('buy', $result['intent']);
        $this->assertSame('rules', $result['method']);
    }

    public function test_entity_extractor_parses_quantity_and_product(): void
    {
        $entities = app(EntityExtractionService::class)->extract('I need 20 chairs by Friday');

        $this->assertSame(20, $entities['quantity']);
        $this->assertSame('chairs', $entities['product']);
        $this->assertSame('friday', $entities['delivery_date']);
    }

    public function test_orchestrator_marks_trivial_messages(): void
    {
        $intents = app(IntentClassificationService::class);

        $this->assertTrue($intents->isTrivialMessage('Thanks!'));
        $this->assertFalse($intents->isTrivialMessage('Do you have red shoes in size 42?'));
    }

    public function test_orchestrator_routing_map_includes_capabilities(): void
    {
        $map = app(AiOrchestrator::class)->routingMap();

        $this->assertContains(AiModel::CAPABILITY_REASONING, $map['capabilities']);
        $this->assertArrayHasKey(AiUseCase::AGENT_REASONING, $map['useCases']);
        $this->assertArrayHasKey('tax_calculation', $map['deterministicHandlers']);
    }

    public function test_admin_orchestration_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/ai-config/orchestration');
        $response->assertOk();
        $response->assertJsonStructure(['useCases', 'recommendedDefaults', 'capabilities']);
    }
}
