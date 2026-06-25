<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use App\Services\AI\AiLearningConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): array
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    public function test_whatsapp_campaign_audience_requires_auth(): void
    {
        $this->getJson('/api/company/whatsapp/campaign/audience')->assertUnauthorized();
    }

    public function test_whatsapp_campaign_audience_counts_unique_phones(): void
    {
        [$company, $user] = $this->companyUser();
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111111',
            'customer_name' => 'A',
            'status' => 'open',
        ]);
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111111',
            'customer_name' => 'A2',
            'status' => 'open',
        ]);
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700222222',
            'customer_name' => 'B',
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/company/whatsapp/campaign/audience')
            ->assertOk()
            ->assertJsonPath('uniqueCustomers', 2);
    }

    public function test_whatsapp_campaign_audience_ordered_segment(): void
    {
        [$company, $user] = $this->companyUser();
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700111111',
            'customer_name' => 'Buyer',
            'status' => 'open',
        ]);
        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700333333',
            'customer_name' => 'Browser',
            'status' => 'open',
        ]);
        \App\Models\Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1001',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111111',
            'status' => 'completed',
            'total' => 100,
        ]);

        Sanctum::actingAs($user);
        $this->getJson('/api/company/whatsapp/campaign/audience?segment=ordered')
            ->assertOk()
            ->assertJsonPath('uniqueCustomers', 1)
            ->assertJsonPath('segment', 'ordered');
    }

    public function test_growth_analytics_includes_ai_image_limits(): void
    {
        [$company, $user] = $this->companyUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/company/growth/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'limits' => ['aiPostsUsed', 'aiPostsLimit', 'aiImagesUsed', 'aiImagesLimit', 'platformLimit'],
            ]);
    }

    public function test_customer_thumbs_down_via_whatsapp_rejects_sample(): void
    {
        PlatformSetting::create(['platform_name' => 'Test']);
        AiLearningConfig::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id, 'auto_reply_enabled' => true]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pn-1',
            'display_phone_number' => '254700000000',
            'access_token' => 'token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700999999',
            'customer_name' => 'Cust',
            'status' => 'open',
        ]);

        $sample = ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'hours?',
            'assistant_reply' => 'We are open nine to five on weekdays for all customers.',
            'question_fingerprint' => hash('xxh128', 'hours'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'chat_id' => $chat->id,
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'We are open nine to five on weekdays for all customers.',
            'sender' => 'bot',
            'reply_source' => 'openai',
            'learning_sample_id' => $sample->id,
            'status' => 'sent',
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700999999',
            'pn-1',
            '👎',
            'Cust',
            'wamid.thumb',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $sample->refresh();
        $this->assertSame(ConversationLearningSample::STATUS_REJECTED, $sample->status);
    }

    public function test_learning_export_returns_csv(): void
    {
        [$company, $user] = $this->companyUser();
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'test q',
            'assistant_reply' => 'test answer long enough for storage here',
            'question_fingerprint' => hash('xxh128', 'test'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
        ]);

        Sanctum::actingAs($user);
        $response = $this->get('/api/company/learning/export');
        $response->assertOk();
        $content = method_exists($response, 'streamedContent')
            ? $response->streamedContent()
            : $response->getContent();
        $this->assertStringContainsString('customer_message', $content);
    }
}
