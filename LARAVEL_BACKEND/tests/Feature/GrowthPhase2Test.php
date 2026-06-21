<?php

namespace Tests\Feature;

use App\Models\AttributionEvent;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Jobs\Growth\ProcessCrmFollowUpsJob;
use App\Jobs\Growth\RunGrowthAgentJob;
use App\Models\GrowthAgentRun;
use App\Models\PortfolioRecommendation;
use App\Services\Growth\CrmFollowUpService;
use App\Services\Growth\CrossBrandLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthPhase2Test extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Phase2 Co',
            'email' => 'p2@test.local',
            'status' => 'active',
            'growth_pilot_at' => now(),
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

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => '123456',
            'access_token' => 'test-token',
            'status' => 'active',
            'display_phone_number' => '254700000099',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    public function test_pilot_status_endpoint(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->getJson('/api/company/growth/pilot')
            ->assertOk()
            ->assertJsonPath('isPilot', true);
    }

    public function test_cross_brand_learning_generates_recommendations(): void
    {
        $company = Company::create(['name' => 'Brand A', 'email' => 'a@test.local', 'status' => 'active']);
        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'facebook',
            'content' => 'Winner post',
            'status' => 'published',
        ]);

        AttributionEvent::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'event_type' => 'revenue',
            'platform' => 'facebook',
            'revenue' => 50000,
        ]);

        $items = app(CrossBrandLearningService::class)->generate(30);

        $this->assertNotEmpty($items);
    }

    public function test_cross_brand_learning_replaces_unread_recommendations(): void
    {
        $company = Company::create(['name' => 'Brand B', 'email' => 'b@test.local', 'status' => 'active']);
        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'facebook',
            'content' => 'Winner post',
            'status' => 'published',
        ]);

        AttributionEvent::create([
            'company_id' => $company->id,
            'social_post_id' => $post->id,
            'event_type' => 'revenue',
            'platform' => 'facebook',
            'revenue' => 25000,
        ]);

        $learning = app(CrossBrandLearningService::class);
        $learning->generate(30);
        $firstCount = PortfolioRecommendation::count();

        $learning->generate(30);
        $secondCount = PortfolioRecommendation::count();

        $this->assertGreaterThan(0, $firstCount);
        $this->assertSame($firstCount, $secondCount);
    }

    public function test_crm_skips_chats_with_orders(): void
    {
        $user = $this->companyUser();
        $companyId = $user->company_id;

        $post = SocialPost::create([
            'company_id' => $companyId,
            'platform' => 'facebook',
            'content' => 'Promo',
            'status' => 'published',
        ]);

        $chat = Chat::create([
            'company_id' => $companyId,
            'social_post_id' => $post->id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254711111111',
            'last_message' => 'hi',
            'last_message_at' => now()->subDays(2),
            'status' => 'active',
        ]);

        Order::create([
            'company_id' => $companyId,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-CRM01',
            'customer_name' => 'Buyer',
            'customer_phone' => '254711111111',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $result = app(CrmFollowUpService::class)->processCompany($companyId);

        $this->assertSame(0, $result['sent']);
    }

    public function test_meta_sync_endpoints(): void
    {
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/growth/meta/sync-metrics')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/company/growth/meta/sync-ads')->assertOk()->assertJsonPath('success', true);

        Queue::fake();
        $this->postJson('/api/company/growth/crm/run')->assertOk()->assertJsonPath('success', true);
        Queue::assertPushed(ProcessCrmFollowUpsJob::class, fn (ProcessCrmFollowUpsJob $job) => $job->companyId !== null);
    }

    public function test_crm_follows_up_when_chat_has_no_message_rows(): void
    {
        $user = $this->companyUser();
        $companyId = $user->company_id;

        $post = SocialPost::create([
            'company_id' => $companyId,
            'platform' => 'facebook',
            'content' => 'Promo',
            'status' => 'published',
        ]);

        Chat::create([
            'company_id' => $companyId,
            'social_post_id' => $post->id,
            'customer_name' => 'Lead',
            'customer_phone' => '254722222222',
            'last_message' => 'I am interested',
            'last_message_at' => now()->subDays(2),
            'status' => 'active',
        ]);

        $this->mock(\App\Services\WhatsAppMessageSenderService::class, function ($mock) {
            $mock->shouldReceive('sendText')->once()->andReturn(['success' => true, 'message_id' => 'wamid.test']);
        });

        $result = app(CrmFollowUpService::class)->processCompany($companyId);

        $this->assertSame(1, $result['sent']);
    }

    public function test_content_agent_pipeline_does_not_auto_generate_posts(): void
    {
        $user = $this->companyUser();
        $run = GrowthAgentRun::create([
            'company_id' => $user->company_id,
            'agent_type' => 'content',
            'status' => 'pending',
            'input' => ['topic' => 'test'],
        ]);

        $job = new RunGrowthAgentJob($run->id);
        $job->handle(
            app(\App\Services\Growth\GrowthContentService::class),
            app(\App\Services\Growth\GrowthAnalyticsService::class),
            app(\App\Services\Growth\GrowthInsightService::class),
        );

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertArrayHasKey('message', $run->output);
        $this->assertSame(0, SocialPost::where('company_id', $user->company_id)->where('ai_generated', true)->count());
    }
}
