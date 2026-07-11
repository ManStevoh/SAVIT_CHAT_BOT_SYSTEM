<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\Channels\ChatChannel;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceAgentPhase8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config(['agent.owner_analytics.use_llm' => false]);
    }

    /** @return array{company: Company, owner: User, widgetToken: string, ingestSecret: string} */
    private function phase8Company(): array
    {
        $company = Company::create([
            'name' => 'Phase8 Co',
            'email' => 'phase8@test.local',
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
            'web_widget_token' => 'widget-phase8-token',
            'channel_ingest_secret' => 'ingest-secret-phase8',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [
            'company' => $company,
            'owner' => $owner,
            'widgetToken' => (string) $settings->web_widget_token,
            'ingestSecret' => (string) $settings->channel_ingest_secret,
        ];
    }

    public function test_web_widget_config_api(): void
    {
        ['company' => $company, 'widgetToken' => $token] = $this->phase8Company();

        $this->getJson('/api/public/web-widget/config?companyId='.$company->id.'&widgetToken='.$token)
            ->assertOk()
            ->assertJsonPath('companyName', 'Phase8 Co');
    }

    public function test_email_webhook_ingests_message(): void
    {
        ['company' => $company, 'ingestSecret' => $secret] = $this->phase8Company();

        $response = $this->postJson('/api/webhooks/channels/'.$company->id.'/email', [
            'from' => 'buyer@example.com',
            'fromName' => 'Buyer',
            'subject' => 'Order status',
            'body' => 'Where is my package?',
        ], ['X-Channel-Ingest-Secret' => $secret]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('chats', [
            'company_id' => $company->id,
            'channel' => ChatChannel::EMAIL,
            'channel_user_id' => 'buyer@example.com',
        ]);
    }

    public function test_email_webhook_rejects_bad_secret(): void
    {
        ['company' => $company] = $this->phase8Company();

        $this->postJson('/api/webhooks/channels/'.$company->id.'/email', [
            'from' => 'a@b.com',
            'body' => 'Hi',
        ], ['X-Channel-Ingest-Secret' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_instagram_dm_generic_webhook(): void
    {
        ['company' => $company, 'ingestSecret' => $secret] = $this->phase8Company();

        $this->postJson('/api/webhooks/channels/'.$company->id.'/instagram-dm', [
            'senderId' => 'ig_user_99',
            'senderUsername' => 'shopper',
            'text' => 'Do you ship internationally?',
        ], ['X-Channel-Ingest-Secret' => $secret])
            ->assertStatus(202);

        $this->assertDatabaseHas('chats', [
            'channel' => ChatChannel::INSTAGRAM_DM,
            'channel_user_id' => 'ig_user_99',
        ]);
    }

    public function test_channels_api_includes_webhook_urls(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->phase8Company();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/company/channels');
        $response->assertOk();
        $response->assertJsonStructure([
            'webhooks' => ['email', 'instagramDm'],
            'widget' => ['scriptUrl', 'configUrl', 'messageUrl'],
            'channelIngestSecret',
        ]);
        $this->assertStringContainsString('/email', $response->json('webhooks.email'));
    }

    public function test_regenerate_channel_tokens(): void
    {
        ['owner' => $owner] = $this->phase8Company();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/company/channels/regenerate-tokens');
        $response->assertOk();
        $response->assertJsonStructure(['webWidgetToken', 'channelIngestSecret']);
    }

    public function test_widget_script_file_exists(): void
    {
        $path = public_path('widget/savit-chat.js');
        $this->assertFileExists($path);
        $this->assertStringContainsString('data-company-id', file_get_contents($path));
    }
}
