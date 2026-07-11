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
