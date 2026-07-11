<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    public const CAPABILITY_CHAT = 'chat';

    public const CAPABILITY_REASONING = 'reasoning';

    public const CAPABILITY_FAST_CHAT = 'fast_chat';

    public const CAPABILITY_VISION = 'vision';

    public const CAPABILITY_EMBEDDING = 'embedding';

    public const CAPABILITY_IMAGE = 'image';

    public const CAPABILITY_STT = 'stt';

    public const CAPABILITY_TTS = 'tts';

    /** @return array<int, string> */
    public static function capabilities(): array
    {
        return [
            self::CAPABILITY_CHAT,
            self::CAPABILITY_REASONING,
            self::CAPABILITY_FAST_CHAT,
            self::CAPABILITY_VISION,
            self::CAPABILITY_EMBEDDING,
            self::CAPABILITY_IMAGE,
            self::CAPABILITY_STT,
            self::CAPABILITY_TTS,
        ];
    }

    public static function isDedicatedOrchestrationCapability(string $capability): bool
    {
        return in_array($capability, [
            self::CAPABILITY_REASONING,
            self::CAPABILITY_FAST_CHAT,
            self::CAPABILITY_VISION,
            self::CAPABILITY_EMBEDDING,
            self::CAPABILITY_IMAGE,
            self::CAPABILITY_STT,
            self::CAPABILITY_TTS,
        ], true);
    }

    protected $fillable = [
        'ai_provider_id',
        'model_key',
        'display_name',
        'capability',
        'input_cost_per_million',
        'output_cost_per_million',
        'max_output_tokens',
        'is_enabled',
        'is_platform_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'input_cost_per_million' => 'float',
            'output_cost_per_million' => 'float',
            'is_enabled' => 'boolean',
            'is_platform_default' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public function estimateCostUsd(int $promptTokens, int $completionTokens): float
    {
        $input = ($promptTokens / 1_000_000) * (float) $this->input_cost_per_million;
        $output = ($completionTokens / 1_000_000) * (float) $this->output_cost_per_million;

        return round($input + $output, 6);
    }
}
