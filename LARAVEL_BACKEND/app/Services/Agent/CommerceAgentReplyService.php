<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;

final class CommerceAgentReplyService
{
    public function __construct(
        protected CommerceAgentOrchestrator $orchestrator,
    ) {}

    public static function isEnabledForCompany(Company $company): bool
    {
        $settings = $company->settings;

        return (bool) ($settings?->agent_commerce_enabled ?? config('agent.default_agent_commerce_enabled', false));
    }

    /**
     * Whether the company's plan includes agent commerce (Growth+ by default).
     */
    public static function isEntitledForCompany(Company $company): bool
    {
        return app(AgentCommerceProvisioningService::class)->isEntitled($company);
    }

    /**
     * @return array{reply: ?string, route: string, handoff: bool}|null null = fall back to legacy pipeline
     */
    public function generate(
        Company $company,
        Chat $chat,
        string $customerPhone,
        ?string $customerName,
        string $incomingMessage,
    ): ?array {
        if (! self::isEnabledForCompany($company)) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('COMMERCE_AGENT_DISABLED_FOR_COMPANY', ['company_id' => $company->id]);

            return null;
        }

        \App\Services\WhatsApp\WhatsAppDebugLogger::info('COMMERCE_AGENT_START', [
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'customer_phone' => $customerPhone,
        ]);

        try {
            $result = $this->orchestrator->run(
                $company,
                $chat,
                $customerPhone,
                $customerName,
                $incomingMessage,
            );
        } catch (\Throwable $e) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('COMMERCE_AGENT_EXCEPTION', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
            ], $e);
            \Illuminate\Support\Facades\Log::error('CommerceAgentReplyService: generate failed, falling back', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($result['reply'] === null || trim($result['reply']) === '') {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('COMMERCE_AGENT_EMPTY_REPLY', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'route' => $result['route'] ?? null,
            ]);

            return null;
        }

        return [
            'reply' => $result['reply'],
            'route' => $result['route'],
            'handoff' => $result['handoff'],
            'log_id' => $result['log_id'] ?? null,
            'pay_url' => $result['pay_url'] ?? $result['cta_url'] ?? null,
            'cta_button_text' => $result['cta_button_text'] ?? $result['button_text'] ?? null,
        ];
    }
}
