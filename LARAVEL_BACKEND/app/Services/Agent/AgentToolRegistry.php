<?php

namespace App\Services\Agent;

use App\Services\Agent\Contracts\AgentTool;
use InvalidArgumentException;

final class AgentToolRegistry
{
    /** @var array<string, AgentTool> */
    private array $tools = [];

    public function register(AgentTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?AgentTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<int, AgentTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /**
     * OpenAI tools payload.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openAiDefinitions(): array
    {
        $out = [];
        foreach ($this->tools as $tool) {
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->parametersSchema(),
                ],
            ];
        }

        return $out;
    }

    public function execute(string $name, AgentToolContext $context, array $arguments): array
    {
        $tool = $this->get($name);
        if ($tool === null) {
            throw new InvalidArgumentException("Unknown agent tool: {$name}");
        }

        return $tool->execute($context, $arguments);
    }
}
