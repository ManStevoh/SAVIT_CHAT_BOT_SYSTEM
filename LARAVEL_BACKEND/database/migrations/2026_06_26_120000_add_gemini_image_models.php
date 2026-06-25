<?php

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $geminiKey = config('gemini.api_key');

        $google = AiProvider::where('slug', 'google')->first();
        if (! $google) {
            return;
        }

        if ($geminiKey && ! $google->hasConfiguredApiKey()) {
            $google->update([
                'api_key' => $geminiKey,
                'is_enabled' => true,
            ]);
        } elseif ($geminiKey) {
            $google->update(['is_enabled' => true]);
        }

        $imageModels = [
            ['gemini-2.5-flash-image', 'Nano Banana (Gemini 2.5 Flash Image)', 30.00, 0.00, true],
            ['gemini-3.1-flash-image-preview', 'Nano Banana 2 (Gemini 3.1 Flash Image)', 30.00, 0.00, false],
            ['gemini-3-pro-image-preview', 'Nano Banana Pro (Gemini 3 Pro Image)', 40.00, 0.00, false],
        ];

        foreach ($imageModels as $i => $row) {
            [$key, $name, $in, $out, $isDefault] = $row;
            AiModel::updateOrCreate(
                [
                    'ai_provider_id' => $google->id,
                    'model_key' => $key,
                    'capability' => AiModel::CAPABILITY_IMAGE,
                ],
                [
                    'display_name' => $name,
                    'input_cost_per_million' => $in,
                    'output_cost_per_million' => $out,
                    'max_output_tokens' => 1290,
                    'is_enabled' => true,
                    'is_platform_default' => $isDefault,
                    'sort_order' => $i,
                ]
            );
        }

        $defaultKey = config('gemini.image_model', 'gemini-2.5-flash-image');
        AiModel::query()
            ->where('ai_provider_id', $google->id)
            ->where('capability', AiModel::CAPABILITY_IMAGE)
            ->update(['is_platform_default' => false]);

        AiModel::query()
            ->where('ai_provider_id', $google->id)
            ->where('capability', AiModel::CAPABILITY_IMAGE)
            ->where('model_key', $defaultKey)
            ->update(['is_platform_default' => true]);
    }

    public function down(): void
    {
        $google = AiProvider::where('slug', 'google')->first();
        if (! $google) {
            return;
        }

        AiModel::query()
            ->where('ai_provider_id', $google->id)
            ->where('capability', AiModel::CAPABILITY_IMAGE)
            ->delete();
    }
};
