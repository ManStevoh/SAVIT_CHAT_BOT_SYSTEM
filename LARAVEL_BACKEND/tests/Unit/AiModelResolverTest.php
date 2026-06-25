<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Subscription;
use App\Services\AI\AiModelResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiModelResolver::clearCache();
    }

    private function enableOpenAi(string $key = 'sk-test'): AiProvider
    {
        $provider = AiProvider::where('slug', 'openai')->firstOrFail();
        $provider->update(['api_key' => $key, 'is_enabled' => true]);

        return $provider->fresh();
    }

    public function test_auto_mode_picks_cheapest_enabled_chat_model(): void
    {
        $this->enableOpenAi();
        $company = Company::create(['name' => 'Co', 'email' => 'c@test.local']);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_model_mode' => 'auto',
        ]);

        $resolved = app(AiModelResolver::class)->resolve($company->fresh(['settings']), AiModel::CAPABILITY_CHAT);

        $this->assertNotNull($resolved);
        $this->assertSame('gpt-4o-mini', $resolved->model->model_key);
    }

    public function test_platform_default_mode_uses_flagged_model(): void
    {
        $provider = $this->enableOpenAi();
        $default = AiModel::where('ai_provider_id', $provider->id)
            ->where('is_platform_default', true)
            ->where('capability', 'chat')
            ->first();

        if (! $default) {
            $default = AiModel::where('ai_provider_id', $provider->id)
                ->where('model_key', 'gpt-4o-mini')
                ->firstOrFail();
            $default->update(['is_platform_default' => true, 'is_enabled' => true]);
        }
        AiModelResolver::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c2@test.local']);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_model_mode' => 'platform_default',
        ]);

        $resolved = app(AiModelResolver::class)->resolve($company->fresh(['settings']), AiModel::CAPABILITY_CHAT);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->model->is_platform_default);
    }

    public function test_specific_mode_uses_company_selected_model(): void
    {
        $provider = $this->enableOpenAi();
        $gpt4o = AiModel::where('ai_provider_id', $provider->id)
            ->where('model_key', 'gpt-4o')
            ->where('capability', 'chat')
            ->firstOrFail();
        $gpt4o->update(['is_enabled' => true]);
        AiModelResolver::clearCache();

        $company = Company::create(['name' => 'Co', 'email' => 'c3@test.local']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'enterprise',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'ai_model_mode' => 'specific',
            'ai_model_id' => $gpt4o->id,
        ]);

        $resolved = app(AiModelResolver::class)->resolve($company->fresh(['settings']), AiModel::CAPABILITY_CHAT);

        $this->assertNotNull($resolved);
        $this->assertSame('gpt-4o', $resolved->model->model_key);
    }

    public function test_resolve_returns_null_when_no_provider_configured(): void
    {
        AiProvider::query()->update(['is_enabled' => false, 'api_key' => null]);
        AiModelResolver::clearCache();

        $resolved = app(AiModelResolver::class)->resolve(null, AiModel::CAPABILITY_CHAT);

        $this->assertNull($resolved);
    }

    public function test_embedding_capability_resolves_embedding_model(): void
    {
        $this->enableOpenAi();

        $resolved = app(AiModelResolver::class)->resolve(null, AiModel::CAPABILITY_EMBEDDING);

        $this->assertNotNull($resolved);
        $this->assertSame('embedding', $resolved->model->capability);
        $this->assertSame('text-embedding-3-small', $resolved->model->model_key);
    }
}
