<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\AI\AiModelResolver;
use App\Services\AI\OpenAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiFullFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedAiProvider(string $apiKey = 'sk-test'): AiProvider
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => $apiKey, 'is_enabled' => true]);
        AiModelResolver::clearCache();

        return $provider->fresh();
    }

    private function companyWithBotEnabled(string $plan = 'professional'): array
    {
        $company = Company::create([
            'name' => 'AI Flow Co',
            'email' => 'ai@test.local',
            'status' => 'active',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'auto_reply_enabled' => true,
            'ai_model_mode' => 'auto',
            'learn_from_conversations' => false,
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-ai',
            'whatsapp_business_account_id' => 'waba-ai',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000099',
            'customer_name' => 'Test Customer',
        ]);

        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Hi',
            'sender' => 'customer',
            'whatsapp_message_id' => 'wamid.in.0',
        ]);
        Message::create([
            'chat_id' => $chat->id,
            'content' => 'Earlier question',
            'sender' => 'customer',
            'whatsapp_message_id' => 'wamid.in.1',
        ]);

        return [$company, $chat];
    }

    private function adminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_whatsapp_job_replies_via_faq_in_balanced_mode(): void
    {
        $this->seedAiProvider();
        [$company, $chat] = $this->companyWithBotEnabled();
        $company->settings->update(['ai_reply_mode' => 'balanced']);

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Refund policy',
            'answer' => 'We refund within 14 days.',
            'keywords' => ['refund'],
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot.1']],
            ], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700000099',
            'phone-ai',
            'What is your refund policy?',
            'Test Customer',
            'wamid.in.2',
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
            'content' => 'We refund within 14 days.',
        ]);

        // FAQ reply stores a learning sample; embedding sync may log one API call (no chat completion).
        $this->assertDatabaseMissing('ai_request_logs', [
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
        ]);
    }

    public function test_ai_first_mode_uses_openai_even_when_faq_matches(): void
    {
        $this->seedAiProvider();
        [$company, $chat] = $this->companyWithBotEnabled();

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Refund policy',
            'answer' => 'We refund within 14 days.',
            'keywords' => ['refund'],
            'is_active' => true,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'Our refund policy allows returns within 14 days of purchase.']]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 20, 'total_tokens' => 120],
            ], 200),
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot.ai1']],
            ], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700000099',
            'phone-ai',
            'What is your refund policy?',
            'Test Customer',
            'wamid.in.faq-ai',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'reply_source' => 'openai',
        ]);
        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
            'success' => true,
        ]);
    }

    public function test_ai_first_falls_back_to_faq_when_openai_unavailable(): void
    {
        $this->seedAiProvider('');
        [$company, $chat] = $this->companyWithBotEnabled();

        Faq::create([
            'company_id' => $company->id,
            'question' => 'Refund policy',
            'answer' => 'We refund within 14 days.',
            'keywords' => ['refund'],
            'is_active' => true,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot.faqfb']],
            ], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700000099',
            'phone-ai',
            'What is your refund policy?',
            'Test Customer',
            'wamid.in.faqfb',
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
            'content' => 'We refund within 14 days.',
        ]);
    }

    public function test_whatsapp_job_replies_via_openai_with_cost_log(): void
    {
        $this->seedAiProvider();
        [$company, $chat] = $this->companyWithBotEnabled();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'We deliver across Nairobi.']]],
                'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 30, 'total_tokens' => 230],
            ], 200),
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.bot.2']],
            ], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700000099',
            'phone-ai',
            'Do you deliver to Nairobi?',
            'Test Customer',
            'wamid.in.3',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('messages', [
            'chat_id' => $chat->id,
            'sender' => 'bot',
            'reply_source' => 'openai',
        ]);

        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'use_case' => OpenAiClient::USE_CASE_WHATSAPP,
            'success' => true,
            'total_tokens' => 230,
        ]);

        $log = \App\Models\AiRequestLog::where('company_id', $company->id)->first();
        $this->assertGreaterThan(0, (float) $log->estimated_cost_usd);
    }

    public function test_company_specific_model_is_used_for_openai_call(): void
    {
        $provider = $this->seedAiProvider();
        [$company, $chat] = $this->companyWithBotEnabled('enterprise');

        $gpt4o = AiModel::where('ai_provider_id', $provider->id)
            ->where('model_key', 'gpt-4o')
            ->where('capability', 'chat')
            ->firstOrFail();
        $gpt4o->update(['is_enabled' => true]);
        AiModelResolver::clearCache();

        $company->settings->update([
            'ai_model_mode' => 'specific',
            'ai_model_id' => $gpt4o->id,
        ]);
        $company->load('settings');

        Http::fake([
            'api.openai.com/*' => function ($request) {
                $body = $request->data();
                $this->assertSame('gpt-4o', $body['model'] ?? null);

                return Http::response([
                    'model' => 'gpt-4o',
                    'choices' => [['message' => ['content' => 'Custom model reply']]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
                ], 200);
            },
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.bot.3']]], 200),
        ]);

        $job = new ProcessIncomingWhatsAppMessage(
            $company->id,
            $chat->id,
            '254700000099',
            'phone-ai',
            'What is your warranty policy for refurbished items?',
            'Test Customer',
            'wamid.in.4',
        );
        $job->handle(
            app(\App\Services\AIReplyService::class),
            app(\App\Services\WhatsAppMessageSenderService::class),
            app(\App\Services\MailService::class),
        );

        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'model' => 'gpt-4o',
        ]);
    }

    public function test_admin_ai_config_lists_providers(): void
    {
        $this->seedAiProvider();
        Sanctum::actingAs($this->adminUser());

        $response = $this->getJson('/api/admin/ai-config');
        $response->assertOk()
            ->assertJsonPath('providers.0.slug', 'openai')
            ->assertJsonStructure(['providers' => [['id', 'slug', 'name', 'models']]]);
    }

    public function test_admin_ai_config_survives_undecryptable_provider_keys(): void
    {
        $provider = $this->seedAiProvider();
        DB::table('ai_providers')->where('id', $provider->id)->update([
            'api_key' => 'plaintext-not-encrypted',
        ]);

        Sanctum::actingAs($this->adminUser());

        $this->getJson('/api/admin/ai-config')
            ->assertOk()
            ->assertJsonPath('providers.0.slug', 'openai')
            ->assertJsonPath('providers.0.apiKeyConfigured', false);
    }

    public function test_admin_can_update_provider_key(): void
    {
        $provider = $this->seedAiProvider('');
        Sanctum::actingAs($this->adminUser());

        $this->putJson("/api/admin/ai-config/providers/{$provider->id}", [
            'apiKey' => 'sk-new-key',
            'isEnabled' => true,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue($provider->fresh()->hasConfiguredApiKey());
    }

    public function test_company_lists_available_ai_models(): void
    {
        $this->seedAiProvider();
        [$company] = $this->companyWithBotEnabled();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/company/ai-models')
            ->assertOk()
            ->assertJsonStructure(['models' => [['id', 'displayName', 'provider']]]);
    }

    public function test_company_can_save_ai_model_mode(): void
    {
        $this->seedAiProvider();
        [$company] = $this->companyWithBotEnabled('enterprise');
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $model = AiModel::where('capability', 'chat')->where('is_enabled', true)->first();

        $this->putJson('/api/company/settings', [
            'aiModelMode' => 'specific',
            'aiModelId' => $model->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'ai_model_mode' => 'specific',
            'ai_model_id' => $model->id,
        ]);
    }

    public function test_company_can_save_ai_reply_mode(): void
    {
        $this->seedAiProvider();
        [$company] = $this->companyWithBotEnabled();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/company/settings', [
            'aiReplyMode' => 'balanced',
        ])->assertOk();

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'ai_reply_mode' => 'balanced',
        ]);
    }

    public function test_growth_generate_returns_ai_generated_flag(): void
    {
        $this->seedAiProvider();
        [$company] = $this->companyWithBotEnabled();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        Subscription::where('company_id', $company->id)->update(['plan' => 'professional']);

        $aiContent = json_encode([
            'posts' => [[
                'title' => 'P1',
                'content' => 'Hello',
                'hashtags' => ['a'],
                'contentType' => 'text',
            ]],
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => $aiContent]]],
                'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 40, 'total_tokens' => 90],
            ], 200),
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/company/growth/content/generate', ['count' => 1])
            ->assertOk()
            ->assertJsonPath('aiGenerated', true)
            ->assertJsonPath('success', true);
    }

    public function test_embedding_logs_tokens_and_cost(): void
    {
        $this->seedAiProvider();
        $company = Company::create(['name' => 'Emb Co', 'email' => 'e@test.local']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => [0.1, 0.2, 0.3]]],
                'usage' => ['prompt_tokens' => 12, 'total_tokens' => 12],
            ], 200),
        ]);

        $vector = app(OpenAiClient::class)->embedText('delivery areas', $company->id);
        $this->assertIsArray($vector);
        $this->assertDatabaseHas('ai_request_logs', [
            'company_id' => $company->id,
            'use_case' => OpenAiClient::USE_CASE_EMBEDDING,
            'success' => true,
            'total_tokens' => 12,
        ]);
    }
}
