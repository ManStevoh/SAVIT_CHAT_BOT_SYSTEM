<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;

final class GetBusinessInfoTool implements AgentTool
{
    public function name(): string
    {
        return 'get_business_info';
    }

    public function description(): string
    {
        return 'Get business settings: hours, timezone, tone, currency, payment methods accepted.';
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
        $settings = $context->company->settings;
        $company = $context->company;

        return [
            'business_name' => $company->name,
            'currency' => $settings?->displayCurrencyCode() ?? 'USD',
            'timezone' => $settings?->timezone,
            'working_hours' => $settings?->working_hours,
            'ai_tone' => $settings?->ai_tone,
            'payments' => [
                'mpesa' => (bool) ($settings?->orders_accept_mpesa ?? false),
                'stripe' => (bool) ($settings?->orders_accept_stripe ?? false),
                'paystack' => (bool) ($settings?->orders_accept_paystack ?? false),
                'manual' => $settings?->hasOrderPaymentManualInstructions() ?? false,
            ],
            'industry' => $company->industry ?? null,
        ];
    }
}
