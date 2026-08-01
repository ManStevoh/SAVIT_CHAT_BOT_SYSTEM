<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\CommerceAgentOrchestrator;
use App\Services\AI\TokenEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AgentOsTokenBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_trim_system_prompt_to_token_budget_trims_from_bottom_when_exceeding_budget(): void
    {
        $orchestrator = app(CommerceAgentOrchestrator::class);
        $reflection = new ReflectionMethod(CommerceAgentOrchestrator::class, 'trimSystemPromptToTokenBudget');
        $reflection->setAccessible(true);

        // Create a prompt with multiple blocks
        $block1 = "BLOCK 1: Essential system prompt context.\nLine 2 of essential context.";
        $block2 = "BLOCK 2: Secondary rules and product catalog details.\nLine 2 of catalog.";
        $block3 = "BLOCK 3: Extra company brain snapshot data.\nLine 2 of brain data.";
        $block4 = "BLOCK 4: Low priority background memory.\nLine 2 of memory.";

        $fullPrompt = implode("\n", [$block1, $block2, $block3, $block4]);
        $totalTokens = TokenEstimator::estimate($fullPrompt);

        // Set budget lower than total tokens + reserved space
        // reserved = 800 + 500 + (24 * 100) = 3700
        // available = budget - 3700
        // So setting budget to 3700 + tokens_for_block1_and_block2 will force trimming block3 and block4
        $availableTokensNeeded = TokenEstimator::estimate(implode("\n", [$block1, $block2]));
        $tightBudget = 3700 + $availableTokensNeeded;

        $trimmedPrompt = $reflection->invoke($orchestrator, $fullPrompt, $tightBudget);

        $this->assertStringContainsString('BLOCK 1', $trimmedPrompt);
        $this->assertStringContainsString('BLOCK 2', $trimmedPrompt);
        $this->assertStringNotContainsString('BLOCK 4', $trimmedPrompt);
    }

    public function test_trim_system_prompt_preserves_prompt_when_within_budget(): void
    {
        $orchestrator = app(CommerceAgentOrchestrator::class);
        $reflection = new ReflectionMethod(CommerceAgentOrchestrator::class, 'trimSystemPromptToTokenBudget');
        $reflection->setAccessible(true);

        $prompt = "Small system prompt within normal limits.";
        $trimmedPrompt = $reflection->invoke($orchestrator, $prompt, 12000);

        $this->assertSame($prompt, $trimmedPrompt);
    }
}
