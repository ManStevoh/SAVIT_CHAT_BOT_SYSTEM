<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifyAiOrchestrationCommand extends Command
{
    protected $signature = 'ai:verify-orchestration';

    protected $description = 'Verify AI orchestration capability slots and config';

    public function handle(AiModelResolver $resolver): int
    {
        $this->info('AI Orchestration verification');
        $this->newLine();

        $requiredCapabilities = [
            AiModel::CAPABILITY_REASONING,
            AiModel::CAPABILITY_CHAT,
            AiModel::CAPABILITY_FAST_CHAT,
            AiModel::CAPABILITY_VISION,
            AiModel::CAPABILITY_EMBEDDING,
            AiModel::CAPABILITY_STT,
        ];

        $ok = true;
        foreach ($requiredCapabilities as $cap) {
            $hasDefault = AiModel::where('capability', $cap)
                ->where('is_platform_default', true)
                ->where('is_enabled', true)
                ->exists();
            $this->line($hasDefault ? "  [OK] platform default: {$cap}" : "  [MISSING] platform default: {$cap}");
            $ok = $ok && $hasDefault;
        }

        $useCases = config('ai.use_cases', []);
        $this->line('  [OK] use_cases configured: '.count($useCases));
