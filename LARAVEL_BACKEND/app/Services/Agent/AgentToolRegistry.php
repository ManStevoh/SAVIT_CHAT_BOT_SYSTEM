<?php

namespace App\Services\Agent;

use App\Models\Company;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\Platform\ExternalModuleToolBridge;
use App\Services\Agent\Platform\SkillModuleRegistry;
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

    /**
     * @param  list<string>  $allowedNames
     * @return array<int, array<string, mixed>>
     */
    public function openAiDefinitionsFiltered(array $allowedNames): array
    {
        if ($allowedNames === []) {
            return $this->openAiDefinitions();
        }

        $allowed = array_flip($allowedNames);
        $out = [];
        foreach ($this->tools as $tool) {
            if (isset($allowed[$tool->name()])) {
                $out[] = [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->name(),
                        'description' => $tool->description(),
                        'parameters' => $tool->parametersSchema(),
                    ],
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openAiDefinitionsForCompany(Company $company): array
    {
        $skills = app(SkillModuleRegistry::class);
        $external = app(ExternalModuleToolBridge::class);

        if (! config('agent.marketplace.restrict_tools_when_installed', true)
            || ! $skills->hasInstalledModules($company)) {
            return array_merge(
                $this->openAiDefinitions(),
                $external->openAiDefinitionsForCompany($company),
            );
        }

        return array_merge(
            $this->openAiDefinitionsFiltered($skills->allowedToolNamesForCompany($company)),
            $external->openAiDefinitionsForCompany($company),
        );
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
