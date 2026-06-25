<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Jobs\SendWhatsAppCampaignRecipientJob;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\Faq;
use App\Models\Message;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use App\Services\AI\AiModelResolver;
use App\Services\AI\OpenAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end API coverage for WhatsApp + AI flows (campaign wizard, growth posters, chat AI, learning).
 */
class WhatsAppAiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function seedAiStack(): void
    {
        config(['gemini.api_key' => 'gemini-test-key']);

        $openai = AiProvider::where('slug', 'openai')->firstOrFail();
        $openai->update(['api_key' => 'sk-test-openai', 'is_enabled' => true]);

        $google = AiProvider::updateOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google Gemini',
                'api_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key' => 'gemini-test-key',
                'is_enabled' => true,
                'sort_order' => 2,
            ]
        );

        AiModel::updateOrCreate(
            [
                'ai_provider_id' => $google->id,
                'model_key' => 'gemini-2.5-flash-image',
                'capability' => AiModel::CAPABILITY_IMAGE,
            ],
            [
                'display_name' => 'Nano Banana',
                'input_cost_per_million' => 30,
                'output_cost_per_million' => 0,
                'max_output_tokens' => 1290,
                'is_enabled' => true,
                'is_platform_default' => true,
                'sort_order' => 0,
            ]
        );

        // Chat/caption tests must hit OpenAI — disable Google chat models when auto-selecting.
        AiModel::query()
            ->where('ai_provider_id', $google->id)
            ->where('capability', '!=', AiModel::CAPABILITY_IMAGE)
            ->update(['is_enabled' => false]);

        AiModel::query()
            ->where('ai_provider_id', $openai->id)
            ->where('capability', AiModel::CAPABILITY_CHAT)
            ->update(['is_platform_default' => true, 'is_enabled' => true]);

        AiModelResolver::clearCache();
    }

    /** @return array{Company, User, Chat} */
    private function companyWithWhatsApp(): array
    {
        $company = Company::create(['name' => 'Integrate Co', 'email' => 'int@test.local', 'status' => 'active']);
        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'ai_reply_mode' => 'balanced',
            'learn_from_conversations' => true,
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pn-int',
            'whatsapp_business_account_id' => 'waba-int',
            'display_phone_number' => '254700000099',
            'access_token' => 'wa-token-int',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254711122233',
            'customer_name' => 'Buyer',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return [$company, $user, $chat];
    }

    public function test_whatsapp_status_and_campaign_limits_endpoints(): void
    {
        $this->seedAiStack();
        [, $user] = $this->companyWithWhatsApp();
        Sanctum::actingAs($user);

        $this->getJson('/api/company/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('connected', true);

        $this->getJson('/api/company/whatsapp/campaign/limits')
            ->assertOk()
            ->assertJsonStructure(['campaignsUsed', 'campaignsLimit', 'recipientsLimit']);
    }

    public function test_audience_segments_and_growth_posts_listing(): void
    {
        $this->seedAiStack();
        [$company, $user] = $this->companyWithWhatsApp();
        Sanctum::actingAs($user);

        Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254799988877',
            'customer_name' => 'Inactive',
            'status' => 'open',
            'last_message_at' => now()->subDays(45),
        ]);

        SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'whatsapp',
            'title' => 'Weekend promo',
            'content' => 'Big sale this weekend!',
            'content_type' => 'image',
            'media_urls' => ['https://example.com/poster.png'],
            'status' => 'draft',
        ]);

        $this->getJson('/api/company/whatsapp/campaign/audience?segment=recent')
            ->assertOk()
            ->assertJsonPath('uniqueCustomers', 1);

        $this->getJson('/api/company/whatsapp/campaign/audience?segment=inactive')
            ->assertOk()
            ->assertJsonPath('uniqueCustomers', 1);

        $posts = $this->getJson('/api/company/whatsapp/campaign/growth-posts')->assertOk()->json();
        $this->assertCount(1, $posts);
        $this->assertNotEmpty($posts[0]['mediaUrls']);
    }

    public function test_growth_poster_generation_and_campaign_wizard_flow(): void
    {
        Storage::fake('public');
        Queue::fake();

        $this->seedAiStack();
        [$company, $user] = $this->companyWithWhatsApp();

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => $png]]]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 1290],
            ], 200),
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => '{"caption":"Weekend sale — reply to order!"}']]],
                'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 15, 'total_tokens' => 95],
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        WhatsAppMessageTemplate::create([
            'company_id' => $company->id,
            'name' => 'promo_poster_v1',
            'language' => 'en',
            'category' => 'marketing',
            'status' => 'approved',
            'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE'],
                ['type' => 'BODY', 'text' => 'Hello {{1}}'],
            ],
            'body_preview' => 'Hello {{1}}',
        ]);

        Sanctum::actingAs($user);

        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'whatsapp',
            'content' => 'Weekend sale on all items',
            'content_type' => 'text',
            'status' => 'draft',
        ]);

        $imageRes = $this->postJson("/api/company/growth/posts/{$post->id}/generate-image");
        $imageRes->assertOk()->assertJsonPath('success', true);
        $post->refresh();
        $this->assertNotEmpty($post->media_urls);

        $captionRes = $this->postJson('/api/company/whatsapp/campaign/generate-caption', [
            'topic' => 'weekend sale',
        ]);
        $captionRes->assertOk()->assertJsonPath('success', true);
        $this->assertStringContainsString('sale', strtolower($captionRes->json('caption')));

        $create = $this->postJson('/api/company/whatsapp/campaigns', [
            'name' => 'E2E Campaign',
            'segment' => 'all',
            'socialPostId' => (string) $post->id,
            'templateName' => 'promo_poster_v1',
            'caption' => $captionRes->json('caption'),
        ]);
        $create->assertCreated();
        $campaignId = $create->json('campaign.id');

        $this->patchJson("/api/company/whatsapp/campaigns/{$campaignId}", [
            'segment' => 'recent',
        ])->assertOk();

        $this->getJson("/api/company/whatsapp/campaigns/{$campaignId}")
            ->assertOk()
            ->assertJsonPath('segment', 'recent');

        $this->postJson("/api/company/whatsapp/campaigns/{$campaignId}/test", [
            'phone' => '254700000099',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson("/api/company/whatsapp/campaigns/{$campaignId}/send")
            ->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertPushed(SendWhatsAppCampaignRecipientJob::class);

        $this->getJson('/api/company/whatsapp/campaigns')
            ->assertOk()
            ->assertJsonFragment(['name' => 'E2E Campaign']);
    }

    public function test_campaign_poster_upload_endpoint(): void
    {
        Storage::fake('public');
        $this->seedAiStack();
        [$company, $user] = $this->companyWithWhatsApp();
        Sanctum::actingAs($user);

        $campaign = \App\Models\WhatsAppCampaign::create([
            'company_id' => $company->id,
            'name' => 'Upload test',
            'status' => 'draft',
            'segment' => 'all',
        ]);

        $file = UploadedFile::fake()->image('poster.jpg', 400, 400);

        $this->postJson("/api/company/whatsapp/campaigns/{$campaign->id}/poster", [
            'image' => $file,
        ])->assertOk()->assertJsonPath('success', true);

        $campaign->refresh();
        $this->assertNotNull($campaign->poster_url);
    }

    public function test_whatsapp_incoming_message_triggers_ai_faq_reply(): void
    {
        $this->seedAiStack();
        [$company, , $chat] = $this->companyWithWhatsApp();

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Opening hours',
            'answer' => 'We are open 9am to 6pm Monday to Saturday.',
            'keywords' => ['hours', 'open'],
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.bot.faq']]], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254711122233',
            'pn-int',
            'What are your opening hours?',
            'Buyer',
            'wamid.cust.1',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'reply_source' => 'faq',
        ]);
    }

    public function test_learning_feedback_and_export_api(): void
    {
        $this->seedAiStack();
        [$company, $user, $chat] = $this->companyWithWhatsApp();
        Sanctum::actingAs($user);

        $sample = ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'hours?',
            'assistant_reply' => 'We are open nine to six on weekdays for all customers here.',
            'question_fingerprint' => hash('xxh128', 'hours'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'chat_id' => $chat->id,
        ]);

        $botMessage = Message::create([
            'chat_id' => $chat->id,
            'content' => 'We are open nine to six on weekdays for all customers here.',
            'sender' => 'bot',
            'reply_source' => 'openai',
            'learning_sample_id' => $sample->id,
            'status' => 'sent',
        ]);

        $this->postJson("/api/company/chats/{$chat->id}/messages/{$botMessage->id}/learning-feedback", [
            'feedback' => 1,
        ])->assertOk();

        $sample->refresh();
        $this->assertGreaterThanOrEqual(1, $sample->positive_feedback_count);

        $export = $this->get('/api/company/learning/export');
        $export->assertOk();
        $content = method_exists($export, 'streamedContent')
            ? $export->streamedContent()
            : $export->getContent();
        $this->assertStringContainsString('customer_message', $content);
    }

    public function test_legacy_campaign_template_send_api(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.leg']]], 200),
        ]);

        $this->seedAiStack();
        [$company, $user] = $this->companyWithWhatsApp();
        Sanctum::actingAs($user);

        WhatsAppMessageTemplate::create([
            'company_id' => $company->id,
            'name' => 'order_update',
            'language' => 'en',
            'category' => 'utility',
            'status' => 'approved',
            'body_preview' => 'Your order is ready',
        ]);

        $this->postJson('/api/company/whatsapp/campaign/send', [
            'mode' => 'template',
            'templateName' => 'order_update',
            'segment' => 'all',
            'bodyParameters' => ['Your order is ready'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('sent', 1);
    }

    public function test_ai_openai_whatsapp_reply_logs_usage(): void
    {
        $this->seedAiStack();
        [$company, , $chat] = $this->companyWithWhatsApp();
        $company->settings->update(['ai_reply_mode' => 'ai_first']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Thanks for reaching out! How can I help you today?']]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 12, 'total_tokens' => 62],
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ai']]], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254711122233',
            'pn-int',
            'Hello I need help',
            'Buyer',
            'wamid.cust.ai',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
            'success' => true,
        ]);
    }
}
