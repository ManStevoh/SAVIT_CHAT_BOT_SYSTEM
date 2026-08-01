<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;

final class GetBusinessInfoTool implements AgentTool
{
    public function __construct(
        protected ?\App\Services\PaymentGateways\PaymentGatewayRegistry $registry = null,
    ) {
        $this->registry = $registry ?? app(\App\Services\PaymentGateways\PaymentGatewayRegistry::class);
    }

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
        $drivers = $this->registry->getAvailableDrivers($company);

        $paymentSummary = [];
        foreach ($drivers as $d) {
            $paymentSummary[$d->getId()] = true;
        }

        $manualDriver = $this->registry->getDriver('manual');

        return [
            'business_name' => $company->name,
            'currency' => $settings?->displayCurrencyCode() ?? 'USD',
            'timezone' => $settings?->timezone,
            'working_hours' => $settings?->working_hours,
            'ai_tone' => $settings?->ai_tone,
            'payments' => array_merge([
                'mpesa' => false,
                'stripe' => false,
                'paystack' => false,
                'cod' => false,
                'manual' => false,
            ], $paymentSummary, [
                'manual_instructions' => ($manualDriver && $manualDriver->isReady($company))
                    ? $manualDriver->getInstructions($company)
                    : null,
            ]),
            'industry' => $company->industry ?? null,
            'note' => 'If payments.manual_instructions is set, share those exact details when the customer wants to pay. Never invent till numbers or claim methods are being set up.',
        ];
    }
}
