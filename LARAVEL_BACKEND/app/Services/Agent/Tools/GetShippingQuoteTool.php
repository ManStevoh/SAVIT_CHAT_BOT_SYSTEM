<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\External\ShippingQuoteService;
use App\Support\MoneyFormatter;

final class GetShippingQuoteTool implements AgentTool
{
    public function __construct(protected ShippingQuoteService $shipping) {}

    public function name(): string
    {
        return 'get_shipping_quote';
    }

    public function description(): string
    {
        return 'Get estimated shipping/delivery cost and ETA for a destination.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'destination' => ['type' => 'string', 'description' => 'Delivery city or area'],
                'order_total' => ['type' => 'number', 'description' => 'Order total amount'],
                'weight_kg' => ['type' => 'number', 'description' => 'Optional package weight in kg'],
            ],
            'required' => ['destination'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        if (! config('agent.external.shipping_enabled', true)) {
            return ['enabled' => false, 'message' => 'Shipping quotes are disabled.'];
        }

        $destination = trim((string) ($arguments['destination'] ?? ''));
        $orderTotal = (float) ($arguments['order_total'] ?? 0);
        $weightKg = isset($arguments['weight_kg']) ? (float) $arguments['weight_kg'] : null;

        $result = $this->shipping->quote(
            (int) $context->company->id,
            $destination,
            $orderTotal,
            $weightKg,
        );

        if (! ($result['success'] ?? false)) {
            return ['quote' => null, 'message' => $result['message'] ?? 'Could not get quote.'];
        }

        $quote = $result['quote'] ?? [];
        $currency = $context->company->settings?->displayCurrencyCode() ?? ($quote['currency'] ?? 'KES');

        return [
            'quote' => [
                'amount' => MoneyFormatter::format((float) ($quote['amount'] ?? 0), $currency),
                'eta_days' => (int) ($quote['eta_days'] ?? 2),
                'carrier' => (string) ($quote['carrier'] ?? 'standard'),
                'note' => (string) ($quote['note'] ?? ''),
            ],
            'source' => $result['source'] ?? 'heuristic',
            'destination' => $destination,
        ];
    }
}
