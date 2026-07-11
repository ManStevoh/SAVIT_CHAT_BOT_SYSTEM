<?php

namespace App\Services\Agent\Platform;

use App\Models\AgentActionRequest;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\AgentToolRegistry;
use App\Services\WhatsApp\WhatsAppCampaignDispatchService;
use Illuminate\Support\Facades\Log;

/**
 * Executes owner-approved high-risk agent actions.
 */
final class AgentApprovalExecutionService
{
    public function __construct(
        protected AgentToolRegistry $registry,
        protected WhatsAppCampaignDispatchService $campaignDispatch,
    ) {}

    /**
     * @return array{success: bool, result?: array<string, mixed>, message?: string}
     */
    public function execute(AgentActionRequest $request, User $approver): array
    {
        if ($request->status !== 'pending') {
            return ['success' => false, 'message' => 'Request is not pending.'];
        }

        if ($request->company_id !== $approver->company_id) {
            return ['success' => false, 'message' => 'Unauthorized.'];
        }

        $payload = $request->payload ?? [];
        $arguments = is_array($payload['arguments'] ?? null) ? $payload['arguments'] : $payload;
        $action = $request->action_type;

        try {
            $result = match ($action) {
                'send_whatsapp_campaign' => $this->executeCampaign((int) $request->company_id, $arguments),
                'issue_order_refund' => $this->executeRefund((int) $request->company_id, $arguments),
                default => $this->executeViaTool($request, $arguments),
            };
        } catch (\Throwable $e) {
            Log::warning('Approval execution failed', [
                'request_id' => $request->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        $request->update([
            'status' => ($result['success'] ?? false) ? 'executed' : 'failed',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'execution_result' => $result,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{success: bool, result?: array<string, mixed>, message?: string}
     */
    private function executeCampaign(int $companyId, array $arguments): array
    {
        $campaignId = (int) ($arguments['campaign_id'] ?? 0);
        $campaign = WhatsAppCampaign::where('company_id', $companyId)->find($campaignId);
        if (! $campaign) {
            return ['success' => false, 'message' => 'Campaign not found.'];
        }

        $company = Company::with('whatsappAccount')->find($companyId);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        $dispatch = $this->campaignDispatch->dispatch($campaign, $company);
        if (! ($dispatch['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($dispatch['message'] ?? 'Dispatch failed.')];
        }

        return [
            'success' => true,
            'result' => [
                'campaign_id' => $campaign->id,
                'status' => $campaign->fresh()?->status,
                'total_recipients' => $campaign->fresh()?->total_recipients,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{success: bool, result?: array<string, mixed>, message?: string}
     */
    private function executeRefund(int $companyId, array $arguments): array
    {
        $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
        if ($orderNumber === '') {
            return ['success' => false, 'message' => 'Order number required.'];
        }

        $order = Order::where('company_id', $companyId)
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        if ($order->payment_status === 'refunded') {
            return ['success' => true, 'result' => ['order_number' => $orderNumber, 'already_refunded' => true]];
        }

        $order->update(['payment_status' => 'refunded', 'status' => 'cancelled']);

        return [
            'success' => true,
            'result' => [
                'order_number' => $orderNumber,
                'payment_status' => 'refunded',
                'total' => (float) $order->total,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{success: bool, result?: array<string, mixed>, message?: string}
     */
    private function executeViaTool(AgentActionRequest $request, array $arguments): array
    {
        $company = Company::find($request->company_id);
        if (! $company) {
            return ['success' => false, 'message' => 'Company not found.'];
        }

        $chat = $request->chat_id
            ? Chat::find($request->chat_id)
            : Chat::where('company_id', $company->id)->orderByDesc('id')->first();

        if (! $chat) {
            return ['success' => false, 'message' => 'No chat context for tool execution.'];
        }

        $context = new AgentToolContext(
            $company,
            $chat,
            $chat->customer_phone ?? '',
            $chat->customer_name,
            '[approved action]',
        );

        $result = $this->registry->execute($request->action_type, $context, $arguments);

        return ['success' => ! isset($result['error']), 'result' => $result];
    }
}
