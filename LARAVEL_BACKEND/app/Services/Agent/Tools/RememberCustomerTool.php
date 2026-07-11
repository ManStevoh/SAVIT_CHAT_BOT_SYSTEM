<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\CustomerMemoryService;

final class RememberCustomerTool implements AgentTool
{
    public function __construct(
        protected CustomerMemoryService $customerMemory,
    ) {}

    public function name(): string
    {
        return 'remember_customer';
    }

    public function description(): string
    {
        return 'Store a persistent fact about this customer for future conversations (preferences, location, budget, etc.).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'key' => ['type' => 'string', 'description' => 'Short label, e.g. preferred_brand'],
                'value' => ['type' => 'string', 'description' => 'The fact to remember'],
                'category' => ['type' => 'string', 'enum' => ['preference', 'location', 'budget', 'context', 'other']],
            ],
            'required' => ['key', 'value'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $key = trim((string) ($arguments['key'] ?? ''));
        $value = trim((string) ($arguments['value'] ?? ''));
        if ($key === '' || $value === '') {
            return ['stored' => false, 'error' => 'key and value required'];
        }

        $this->customerMemory->upsert(
            (int) $context->company->id,
            $context->customerPhone,
            $key,
            $value,
            (string) ($arguments['category'] ?? 'preference'),
        );

        return ['stored' => true, 'key' => $key];
    }
}
