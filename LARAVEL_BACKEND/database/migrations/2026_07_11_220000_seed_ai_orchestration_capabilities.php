<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds orchestration capability slots (reasoning, fast_chat, vision, stt, tts).
 */
return new class extends Migration
{
    public function up(): void
    {
        $openai = AiProvider::where('slug', 'openai')->first();
        if (! $openai) {
            return;
        }

        $slots = [
            ['gpt-4o', 'GPT-4o (Reasoning)', AiModel::CAPABILITY_REASONING, 2.50, 10.00, 4096, true, 0],
            ['gpt-4o-mini', 'GPT-4o Mini (Fast chat)', AiModel::CAPABILITY_FAST_CHAT, 0.15, 0.60, 16384, true, 0],
            ['gpt-4o', 'GPT-4o (Vision)', AiModel::CAPABILITY_VISION, 2.50, 10.00, 2048, true, 0],
            ['whisper-1', 'Whisper (Speech-to-text)', AiModel::CAPABILITY_STT, 0.006, 0.00, 0, true, 0],
            ['tts-1', 'OpenAI TTS', AiModel::CAPABILITY_TTS, 15.00, 0.00, 0, true, 0],
        ];

        foreach ($slots as [$key, $name, $cap, $in, $out, $maxTok, $isDefault, $sort]) {
            AiModel::updateOrCreate(
                [
                    'ai_provider_id' => $openai->id,
                    'model_key' => $key,
                    'capability' => $cap,
                ],
                [
                    'display_name' => $name,
                    'input_cost_per_million' => $in,
                    'output_cost_per_million' => $out,
                    'max_output_tokens' => $maxTok,
                    'is_enabled' => true,
                    'is_platform_default' => $isDefault,
                    'sort_order' => $sort,
                ]
            );
        }

        // Optional quality reasoning alternatives (disabled by default)
        $anthropic = AiProvider::where('slug', 'anthropic')->first();
        if ($anthropic) {
            AiModel::updateOrCreate(
                [
                    'ai_provider_id' => $anthropic->id,
                    'model_key' => 'claude-3-5-sonnet-20241022',
                    'capability' => AiModel::CAPABILITY_REASONING,
                ],
                [
                    'display_name' => 'Claude 3.5 Sonnet (Reasoning alt)',
                    'input_cost_per_million' => 3.00,
                    'output_cost_per_million' => 15.00,
                    'max_output_tokens' => 8192,
                    'is_enabled' => true,
                    'is_platform_default' => false,
                    'sort_order' => 1,
                ]
            );
        }

        $google = AiProvider::where('slug', 'google')->first();
        if ($google) {
            AiModel::updateOrCreate(
                [
                    'ai_provider_id' => $google->id,
                    'model_key' => 'gemini-2.0-flash',
                    'capability' => AiModel::CAPABILITY_FAST_CHAT,
                ],
                [
                    'display_name' => 'Gemini 2.0 Flash (Fast chat alt)',
                    'input_cost_per_million' => 0.10,
                    'output_cost_per_million' => 0.40,
                    'max_output_tokens' => 8192,
                    'is_enabled' => true,
                    'is_platform_default' => false,
                    'sort_order' => 1,
                ]
            );
        }
    }

    public function down(): void
    {
        AiModel::whereIn('capability', [
            AiModel::CAPABILITY_REASONING,
            AiModel::CAPABILITY_FAST_CHAT,
            AiModel::CAPABILITY_VISION,
            AiModel::CAPABILITY_STT,
            AiModel::CAPABILITY_TTS,
        ])->delete();
    }
};
