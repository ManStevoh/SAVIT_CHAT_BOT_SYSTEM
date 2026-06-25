<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Company;
use App\Models\SocialPost;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function companyUser(): User
    {
        $company = Company::create([
            'name' => 'Poster Biz',
            'email' => 'poster@test.local',
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

        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
    }

    private function seedGeminiImageProvider(): void
    {
        config(['gemini.api_key' => 'test-gemini-key']);

        $google = AiProvider::updateOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google Gemini',
                'api_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'api_key' => 'test-gemini-key',
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
    }

    public function test_generate_image_requires_auth(): void
    {
        $company = Company::create(['name' => 'X', 'email' => 'x@test.local', 'status' => 'active']);
        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'facebook',
            'content' => 'Test',
            'content_type' => 'text',
            'status' => 'draft',
        ]);
        $this->postJson("/api/company/growth/posts/{$post->id}/generate-image")
            ->assertUnauthorized();
    }

    public function test_generate_image_attaches_poster_to_draft(): void
    {
        Storage::fake('public');
        $this->seedGeminiImageProvider();

        $user = $this->companyUser();
        $post = SocialPost::create([
            'company_id' => $user->company_id,
            'platform' => 'whatsapp',
            'title' => 'Summer Sale',
            'content' => 'Get 20% off all items this weekend!',
            'content_type' => 'text',
            'status' => 'draft',
            'hashtags' => ['sale', 'weekend'],
        ]);

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['inlineData' => ['mimeType' => 'image/png', 'data' => $png]],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => ['promptTokenCount' => 100, 'candidatesTokenCount' => 1290],
            ], 200),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/company/growth/posts/{$post->id}/generate-image");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['url', 'post']);

        $post->refresh();
        $this->assertNotEmpty($post->media_urls);
        $this->assertSame('image', $post->content_type);
        Storage::disk('public')->assertExists('growth-posts/'.$user->company_id.'/'.basename(parse_url($post->media_urls[0], PHP_URL_PATH)));
    }

    public function test_generate_image_fails_without_gemini_key(): void
    {
        config(['gemini.api_key' => null]);

        $user = $this->companyUser();
        $post = SocialPost::create([
            'company_id' => $user->company_id,
            'platform' => 'instagram',
            'content' => 'Hello',
            'content_type' => 'text',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/company/growth/posts/{$post->id}/generate-image")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
