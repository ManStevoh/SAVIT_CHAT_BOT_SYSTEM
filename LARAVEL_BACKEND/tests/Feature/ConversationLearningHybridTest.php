<?php

namespace Tests\Feature;

use App\Jobs\EmbedLearningSampleJob;
use App\Models\AiProvider;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConversationLearningSample;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AI\AiLearningConfig;
use App\Services\AI\AiModelResolver;
use App\Services\ConversationLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationLearningHybridTest extends TestCase
{
    use RefreshDatabase;

    private function seedPlatform(): void
    {
        PlatformSetting::create([
            'platform_name' => 'Test',
            'ai_learning_config' => [
                'learningEmbeddingsEnabled' => true,
                'learningSemanticMinScore' => 0.5,
                'promptSampleLimit' => 3,
            ],
        ]);
        AiLearningConfig::clearCache();
    }

    private function companyWithLearning(): Company
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local', 'status' => 'active']);
        CompanySetting::create(['company_id' => $company->id, 'learn_from_conversations' => true]);

        return $company;
    }

    private function companyUser(Company $company): User
    {
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    private function seedAiProvider(): void
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => 'sk-test', 'is_enabled' => true]);
        AiModelResolver::clearCache();
    }

    private function createChat(Company $company): Chat
    {
        return Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '+15551234567',
            'customer_name' => 'Test Customer',
            'status' => 'open',
        ]);
    }

    public function test_lexical_retrieval_ranks_matching_sample(): void
    {
        $this->seedPlatform();
        PlatformSetting::first()->update([
            'ai_learning_config' => array_merge(
                PlatformSetting::first()->ai_learning_config ?? [],
                ['learningEmbeddingsEnabled' => false],
            ),
        ]);
        AiLearningConfig::clearCache();

        $company = $this->companyWithLearning();
        $service = app(ConversationLearningService::class);

        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'What is your refund policy?',
            'assistant_reply' => 'We offer full refunds within 14 days of purchase for unused items.',
            'question_fingerprint' => hash('xxh128', 'refund'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
        ]);
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'How much is international shipping?',
            'assistant_reply' => 'International shipping starts at fifteen dollars depending on destination.',
            'question_fingerprint' => hash('xxh128', 'shipping'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
        ]);

        $results = $service->getSamplesForPrompt($company->fresh(), 'Tell me about refunds please');

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('refund', strtolower($results[0]['question']));
    }

    public function test_hybrid_retrieval_prefers_semantic_match(): void
    {
        $this->seedPlatform();
        $this->seedAiProvider();
        $company = $this->companyWithLearning();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => [1.0, 0.0, 0.0]]],
                'usage' => ['prompt_tokens' => 8, 'total_tokens' => 8],
            ], 200),
        ]);

        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'What are your delivery hours?',
            'assistant_reply' => 'We deliver Monday through Saturday from 8am until 8pm in most areas.',
            'question_fingerprint' => hash('xxh128', 'hours'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'question_embedding' => [1.0, 0.0, 0.0],
        ]);
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'Do you sell gift cards?',
            'assistant_reply' => 'Yes, digital gift cards are available in fifty and one hundred dollar amounts.',
            'question_fingerprint' => hash('xxh128', 'gift'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'question_embedding' => [0.0, 1.0, 0.0],
        ]);

        $results = app(ConversationLearningService::class)->getSamplesForPrompt(
            $company->fresh(),
            'when do you deliver packages',
        );

        $this->assertNotEmpty($results);
        $this->assertStringContainsString('delivery', strtolower($results[0]['question']));
        $this->assertGreaterThan(0, $results[0]['score']);
    }

    public function test_negative_feedback_rejects_linked_sample(): void
    {
        $this->seedPlatform();
        $company = $this->companyWithLearning();
        $user = $this->companyUser($company);
        $chat = $this->createChat($company);
        $chat->update(['customer_phone' => '+15559876543']);

        $sample = ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'Is parking free?',
            'assistant_reply' => 'Yes, we have free customer parking behind the building all day.',
            'question_fingerprint' => hash('xxh128', 'parking'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'chat_id' => $chat->id,
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Yes, we have free customer parking behind the building all day.',
            'sender' => 'bot',
            'reply_source' => 'openai',
            'learning_sample_id' => $sample->id,
            'status' => 'sent',
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/company/chats/{$chat->id}/messages/{$message->id}/learning-feedback", [
            'feedback' => -1,
        ])->assertOk()->assertJsonPath('success', true);

        $sample->refresh();
        $this->assertSame(ConversationLearningSample::STATUS_REJECTED, $sample->status);
        $this->assertSame(-1, $message->fresh()->learning_feedback);
    }

    public function test_link_sample_to_message_sets_message_id(): void
    {
        $this->seedPlatform();
        $company = $this->companyWithLearning();
        $chat = $this->createChat($company);
        $chat->update(['customer_phone' => '+15559876543']);

        $question = 'Do you offer same-day delivery?';
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => $question,
            'assistant_reply' => 'Same-day delivery is available in the city center before 2pm on weekdays.',
            'question_fingerprint' => hash('xxh128', mb_strtolower(trim($question))),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
            'chat_id' => $chat->id,
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => 'Same-day delivery is available in the city center before 2pm on weekdays.',
            'sender' => 'bot',
            'status' => 'sent',
        ]);

        $sampleId = app(ConversationLearningService::class)->linkSampleToMessage(
            $company->id,
            $chat->id,
            $question,
            $message->id,
        );

        $this->assertNotNull($sampleId);
        $this->assertDatabaseHas('conversation_learning_samples', [
            'id' => $sampleId,
            'message_id' => $message->id,
        ]);
    }

    public function test_store_sample_dispatches_embed_job_when_approved(): void
    {
        Queue::fake();
        $this->seedPlatform();
        $company = $this->companyWithLearning();

        $sample = app(ConversationLearningService::class)->storeSample(
            $company->id,
            'What payment methods do you accept?',
            'We accept M-Pesa, Visa, and Mastercard for all online orders.',
        );

        $this->assertNotNull($sample);
        Queue::assertPushed(EmbedLearningSampleJob::class, fn ($job) => $job->sampleId === $sample->id);
    }

    public function test_admin_can_sync_learning_embeddings(): void
    {
        $this->seedPlatform();
        $this->seedAiProvider();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['embedding' => [0.2, 0.3, 0.4]]],
                'usage' => ['prompt_tokens' => 10, 'total_tokens' => 10],
            ], 200),
        ]);

        $company = $this->companyWithLearning();
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'Where are you located?',
            'assistant_reply' => 'Our main store is downtown on Market Street next to the plaza.',
            'question_fingerprint' => hash('xxh128', 'location'),
            'source' => 'openai',
            'status' => ConversationLearningSample::STATUS_APPROVED,
        ]);

        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/ai-learning/sync-learning-embeddings')
            ->assertOk()
            ->assertJsonPath('success', true);

        $sample = ConversationLearningSample::first()->fresh();
        $this->assertNotNull($sample->question_embedding);
    }
}
