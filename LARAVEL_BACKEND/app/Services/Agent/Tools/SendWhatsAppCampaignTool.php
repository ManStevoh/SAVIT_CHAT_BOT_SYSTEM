<?php

namespace App\Services\Agent\Tools;

use App\Models\WhatsAppCampaign;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;

final class SendWhatsAppCampaignTool implements AgentTool
{
    public function name(): string
    {
        return 'send_whatsapp_campaign';
    }

    public function description(): string
    {
        return 'Send an approved WhatsApp campaign to a customer segment. Requires owner approval before dispatch.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'campaign_id' => ['type' => 'integer', 'description' => 'Draft campaign ID to send'],
            ],
            'required' => ['campaign_id'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $campaignId = (int) ($arguments['campaign_id'] ?? 0);
        $campaign = WhatsAppCampaign::where('company_id', $context->company->id)->find($campaignId);

        if (! $campaign) {
            return ['found' => false, 'message' => 'Campaign not found.'];
        }

        return [
            'found' => true,
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'segment' => $campaign->segment,
            'note' => 'Queued for owner approval — campaign will dispatch after approve.',
        ];
    }
}
