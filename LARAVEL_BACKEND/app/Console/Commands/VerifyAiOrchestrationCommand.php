<?php

namespace App\Console\Commands;

use App\Models\AiModel;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifyAiOrchestrationCommand extends Command
{
