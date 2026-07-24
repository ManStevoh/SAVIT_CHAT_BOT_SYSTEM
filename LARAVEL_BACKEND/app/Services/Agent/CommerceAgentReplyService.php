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
            return null;
        }

        $result = $this->orchestrator->run(
            $company,
            $chat,
            $customerPhone,
            $customerName,
            $incomingMessage,
        );

        if ($result['reply'] === null || trim($result['reply']) === '') {
            return null;
        }

        return [
            'reply' => $result['reply'],
            'route' => $result['route'],
            'handoff' => $result['handoff'],
        ];
    }
}
