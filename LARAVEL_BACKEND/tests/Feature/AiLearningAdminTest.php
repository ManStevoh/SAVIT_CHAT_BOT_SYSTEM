<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiLearningAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    public function test_stats_requires_admin(): void
    {
        $this->getJson('/api/admin/ai-learning/stats')->assertUnauthorized();
    }

    public function test_admin_can_view_learning_stats(): void
    {
        PlatformSetting::create(['platform_name' => 'Test']);
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/ai-learning/stats')
            ->assertOk()
            ->assertJsonStructure([
                'config' => ['learningEnabled', 'retentionDays', 'learningEmbeddingsEnabled'],
                'stats' => ['totalLearningSamples', 'embeddingCoveragePercent', 'learningEmbeddingCoveragePercent', 'samplesBySource'],
            ]);
    }

    public function test_admin_can_purge_all_learning_samples_with_confirmation(): void
    {
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local']);
        ConversationLearningSample::create([
            'company_id' => $company->id,
            'customer_message' => 'Q',
            'assistant_reply' => 'Answer long enough to qualify for storage',
            'question_fingerprint' => hash('xxh128', 'q'),
            'source' => 'openai',
        ]);

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/ai-learning/purge', [
            'confirm' => 'DELETE_ALL_LEARNING_DATA',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('conversation_learning_samples', 0);
    }

    public function test_purge_rejects_without_confirmation_phrase(): void
    {
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/admin/ai-learning/purge', [
            'confirm' => 'wrong',
        ])->assertUnprocessable();
    }
}
