<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\Channels\ChatChannel;
use App\Services\Agent\Owner\OwnerAnalyticsAgentService;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiUseCase;
use App\Models\AiModel;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config([
            'agent.owner_analytics.use_llm' => false,
            'agent.company.reasoning_enabled' => false,
        ]);
    }

    /** @return array{company: Company, owner: User, token: string} */
    private function phase7Company(): array
    {
        $company = Company::create([
            'name' => 'Phase7 Co',
            'email' => 'phase7@test.local',
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
        $settings = CompanySetting::create([
            'company_id' => $company->id,
            'agent_commerce_enabled' => true,
            'auto_reply_enabled' => true,
            'web_widget_token' => 'widget-test-token-abc123',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner, 'token' => (string) $settings->web_widget_token];
    }

    public function test_channel_ingest_api_creates_web_widget_chat(): void
    {
        ['owner' => $owner] = $this->phase7Company();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/company/channels/ingest', [
            'channel' => ChatChannel::WEB_WIDGET,
            'channelUserId' => 'visitor-42',
            'message' => 'Hello from the website',
            'customerName' => 'Site User',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('chats', [
            'channel' => ChatChannel::WEB_WIDGET,
            'channel_user_id' => 'visitor-42',
        ]);
    }

    public function test_web_widget_public_api(): void
    {
        ['company' => $company, 'token' => $token] = $this->phase7Company();

        $response = $this->postJson('/api/public/web-widget/message', [
            'companyId' => $company->id,
            'widgetToken' => $token,
            'visitorId' => 'v-public-1',
            'message' => 'Pricing question',
            'name' => 'Visitor',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['chatId', 'reply']);
    }

    public function test_owner_analytics_evidence_includes_timeline_and_graph(): void
    {
        ['company' => $company] = $this->phase7Company();

        $evidence = app(OwnerAnalyticsAgentService::class)->gatherEvidence($company, '7d');

        $this->assertArrayHasKey('business_timeline', $evidence);
        $this->assertArrayHasKey('business_graph', $evidence);
        $this->assertArrayHasKey('stats', $evidence['business_graph']);
    }

    public function test_memory_search_includes_chat_messages(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase7Company();
        Sanctum::actingAs($owner);

        $chat = Chat::create([
            'company_id' => $company->id,
            'channel' => ChatChannel::WHATSAPP,
            'customer_name' => 'Buyer',
            'customer_phone' => '254700000001',
            'status' => 'active',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'sender' => 'customer',
            'content' => 'Do you have blue sneakers in stock?',
            'message_type' => 'text',
        ]);

        $response = $this->postJson('/api/company/memory-search', [
            'query' => 'blue sneakers',
        ]);

        $response->assertOk();
        $sources = collect($response->json('results'))->pluck('source')->all();
        $this->assertContains('chat', $sources);
    }

    public function test_tts_use_case_resolves_tts_capability(): void
    {
        $provider = \App\Models\AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-tts', 'is_enabled' => true]);
        AiModelResolver::clearCache();

        $company = Company::create(['name' => 'TTS Co', 'email' => 'tts@test.local']);
        $resolved = app(AiModelResolver::class)->resolve(
            $company,
            AiModel::CAPABILITY_TTS,
            AiUseCase::TEXT_TO_SPEECH,
        );

        $this->assertNotNull($resolved);
        $this->assertSame(AiModel::CAPABILITY_TTS, $resolved->model->capability);
        $this->assertSame('tts-1', $resolved->model->model_key);
    }
}
