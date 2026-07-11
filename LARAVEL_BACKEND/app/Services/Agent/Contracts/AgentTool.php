<?php

namespace App\Services\Agent\Contracts;

use App\Services\Agent\AgentToolContext;

interface AgentTool
{
    public function name(): string;

    public function description(): string;

    /**
     * OpenAI-compatible JSON schema for function parameters.
     *
     * @return array<string, mixed>
     */
    public function parametersSchema(): array;

    /**
     * Execute tool; return JSON-serializable array (kept small).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(AgentToolContext $context, array $arguments): array;
}
