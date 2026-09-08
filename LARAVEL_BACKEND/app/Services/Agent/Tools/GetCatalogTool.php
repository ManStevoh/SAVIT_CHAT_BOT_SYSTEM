<?php

namespace App\Services\Agent\Tools;

use App\Enums\CheckoutStep;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Workflow\ResponseSpecRenderer;

final class GetCatalogTool implements AgentTool
{
    public function name(): string
    {
        return 'get_catalog';
    }

    public function description(): string
    {
        return 'Get the full numbered product catalog list. Do NOT call this tool when the customer is selecting a product number (e.g. 1, 2) or ordering an item — call process_order_message instead.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $draft = is_array($context->chat->order_draft) ? $context->chat->order_draft : [];
        $draft['items'] = $draft['items'] ?? [];
        $context->chat->update([
            'conversation_step' => CheckoutStep::BUILDING_CART->toLegacyStep(),
            'order_draft' => $draft,
        ]);

        return [
            'catalog' => ResponseSpecRenderer::renderCatalogPrompt($context->company),
        ];
    }
}
