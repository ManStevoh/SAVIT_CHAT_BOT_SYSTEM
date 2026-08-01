<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $providers = [
            ['slug' => 'openai', 'name' => 'OpenAI', 'sort' => 0, 'base' => 'https://api.openai.com/v1'],
            ['slug' => 'anthropic', 'name' => 'Anthropic (Claude)', 'sort' => 1, 'base' => 'https://api.anthropic.com/v1'],
            ['slug' => 'google', 'name' => 'Google Gemini', 'sort' => 2, 'base' => 'https://generativelanguage.googleapis.com/v1beta'],
            ['slug' => 'octopus', 'name' => 'Octopus / OpenAI-compatible', 'sort' => 3, 'base' => null],
        ];

        $platform = PlatformSetting::first();
        $openaiKey = $platform?->openai_api_key ?? config('openai.api_key');

        foreach ($providers as $p) {
            AiProvider::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'api_base_url' => $p['base'],
                    'is_enabled' => $p['slug'] === 'openai' && filled($openaiKey),
                    'api_key' => $p['slug'] === 'openai' ? $openaiKey : null,
                    'sort_order' => $p['sort'],
                ]
            );
        }

        $catalog = [
            'openai' => [
                ['gpt-4o-mini', 'GPT-4o Mini', 'chat', 0.15, 0.60, 16384, true],
                ['gpt-4o', 'GPT-4o', 'chat', 2.50, 10.00, 16384, false],
                ['gpt-4-turbo', 'GPT-4 Turbo', 'chat', 10.00, 30.00, 4096, false],
                ['gpt-3.5-turbo', 'GPT-3.5 Turbo', 'chat', 0.50, 1.50, 4096, false],
                ['o1-mini', 'OpenAI o1-mini', 'chat', 3.00, 12.00, 65536, false],
                ['text-embedding-3-small', 'Embedding 3 Small', 'embedding', 0.02, 0.00, 0, true],
            ],
            'anthropic' => [
                ['claude-3-5-haiku-20241022', 'Claude 3.5 Haiku', 'chat', 0.80, 4.00, 8192, false],
                ['claude-3-5-sonnet-20241022', 'Claude 3.5 Sonnet', 'chat', 3.00, 15.00, 8192, false],
                ['claude-3-opus-20240229', 'Claude 3 Opus', 'chat', 15.00, 75.00, 4096, false],
            ],
            'google' => [
                ['gemini-2.0-flash', 'Gemini 2.0 Flash', 'chat', 0.10, 0.40, 8192, false],
                ['gemini-1.5-flash', 'Gemini 1.5 Flash', 'chat', 0.075, 0.30, 8192, false],
                ['gemini-1.5-pro', 'Gemini 1.5 Pro', 'chat', 1.25, 5.00, 8192, false],
                ['text-embedding-004', 'Gemini Embedding', 'embedding', 0.00, 0.00, 0, false],
            ],
            'octopus' => [
                ['gpt-4o-mini', 'Octopus GPT-4o Mini', 'chat', 0.15, 0.60, 4096, false],
            ],
        ];

        foreach ($catalog as $slug => $models) {
            $provider = AiProvider::where('slug', $slug)->first();
            if (! $provider) {
                continue;
            }
            foreach ($models as $i => $row) {
                [$key, $name, $cap, $in, $out, $maxTok, $isDefault] = $row;
                if ($slug === 'openai' && $platform?->openai_model && $key === $platform->openai_model) {
                    $isDefault = true;
                }
                AiModel::updateOrCreate(
                    [
                        'ai_provider_id' => $provider->id,
                        'model_key' => $key,
                        'capability' => $cap,
                    ],
                    [
                        'display_name' => $name,
                        'input_cost_per_million' => $in,
                        'output_cost_per_million' => $out,
                        'max_output_tokens' => $maxTok ?: (int) ($platform?->openai_max_tokens ?? 512),
                        'is_enabled' => true,
                        'is_platform_default' => (bool) $isDefault,
                        'sort_order' => $i,
                    ]
                );
            }
        }

        if (! AiModel::where('is_platform_default', true)->where('capability', 'chat')->exists()) {
            $openai = AiProvider::where('slug', 'openai')->first();
            if ($openai) {
                AiModel::where('ai_provider_id', $openai->id)
                    ->where('model_key', 'gpt-4o-mini')
                    ->update(['is_platform_default' => true]);
            }
        }
    }

    public function down(): void
    {
        DB::table('ai_models')->delete();
        DB::table('ai_providers')->delete();
    }
};
