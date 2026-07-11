<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Shares and revokes Meta extended credit lines for Solution Partner billing.
 *
 * @see https://developers.facebook.com/docs/whatsapp/embedded-signup/manage-accounts/share-and-revoke-credit-lines/
 */
final class WhatsAppCreditSharingService
{
    public function __construct(
        protected WhatsAppGraphClient $graph
    ) {}

    public function isSolutionPartnerModeActive(): bool
    {
        return WhatsAppPlatformConfig::billingModel() === WhatsAppBillingModel::SOLUTION_PARTNER
            && WhatsAppPlatformConfig::hasSolutionPartnerCredentials();
    }

    /**
     * Attach platform credit line to a customer WABA using Meta's combined API.
     *
     * @return array{success: bool, allocationConfigId?: string|null, message?: string}
     */
    public function shareCreditLineWithWaba(string $wabaId): array
    {
        if (! $this->isSolutionPartnerModeActive()) {
            return [
                'success' => false,
                'message' => 'Solution Partner billing is not configured. Set billing model and credit line credentials in Admin → Settings → Integrations.',
            ];
        }

        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            return [
                'success' => false,
                'message' => 'WhatsApp Business Account ID is required to share the platform credit line.',
            ];
        }

        $creditLineId = WhatsAppPlatformConfig::extendedCreditLineId();
        $systemToken = WhatsAppPlatformConfig::creditSharingSystemToken();
        $currency = WhatsAppPlatformConfig::wabaCurrency();

        $result = $this->graph->postWithQuery(
            "{$creditLineId}/whatsapp_credit_sharing_and_attach",
            $systemToken,
            [
                'waba_currency' => $currency,
                'waba_id' => $wabaId,
            ],
        );

        if ($result['ok']) {
            $allocationId = (string) ($result['data']['allocation_config_id'] ?? '');

            return [
                'success' => true,
                'allocationConfigId' => $allocationId !== '' ? $allocationId : null,
            ];
        }

        $error = $result['data']['error']['message'] ?? 'Credit line sharing failed';
        $code = (int) ($result['data']['error']['code'] ?? 0);

        if ($this->isAlreadySharedError($result)) {
            Log::info('WhatsApp credit line already shared with WABA', ['waba_id' => $wabaId, 'code' => $code]);

            return [
                'success' => true,
                'allocationConfigId' => null,
                'message' => 'Credit line was already shared with this WhatsApp Business Account.',
            ];
        }

        Log::warning('WhatsApp credit line share failed', [
            'waba_id' => $wabaId,
            'status' => $result['status'],
            'error' => $result['data']['error'] ?? $result['body'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to share platform credit line with Meta: ' . $error,
        ];
    }

    /**
     * Revoke credit sharing for a connected account (uses stored ID or resolves via Meta API).
     *
     * @return array{success: bool, message?: string}
     */
    public function revokeForAccount(\App\Models\WhatsAppAccount $account): array
    {
        if ($account->meta_billing_model !== WhatsAppBillingModel::SOLUTION_PARTNER) {
            return ['success' => true];
        }

        $allocationId = trim((string) ($account->credit_allocation_config_id ?? ''));
        if ($allocationId === '') {
            $allocationId = $this->resolveAllocationConfigIdForAccount($account) ?? '';
        }

        return $this->revokeCreditLine($allocationId !== '' ? $allocationId : null);
    }

    /**
     * Look up allocation config ID when Meta returned "already shared" without an ID.
     */
    public function resolveAllocationConfigIdForAccount(\App\Models\WhatsAppAccount $account): ?string
    {
        if (! WhatsAppPlatformConfig::hasSolutionPartnerCredentials()) {
            return null;
        }

        $wabaId = trim((string) ($account->whatsapp_business_account_id ?? ''));
        $businessToken = trim((string) ($account->access_token ?? ''));
        if ($wabaId === '' || $businessToken === '') {
            return null;
        }

        $ownerBusinessId = $this->graph->getWabaOwnerBusinessId($wabaId, $businessToken);
        if ($ownerBusinessId === null || $ownerBusinessId === '') {
            return null;
        }

        $creditLineId = WhatsAppPlatformConfig::extendedCreditLineId();
        $systemToken = WhatsAppPlatformConfig::creditSharingSystemToken();

        $result = $this->graph->get(
            "{$creditLineId}/owning_credit_allocation_configs",
            $systemToken,
            [
                'receiving_business_id' => $ownerBusinessId,
                'fields' => 'id,receiving_business',
            ],
        );

        if (! $result['ok']) {
            return null;
        }

        $configs = $result['data']['data'] ?? [];
        $first = is_array($configs) ? ($configs[0] ?? null) : null;

        return is_array($first) ? (string) ($first['id'] ?? '') ?: null : null;
    }

    /**
     * Revoke credit sharing using stored allocation config ID.
     *
     * @return array{success: bool, message?: string}
     */
    public function revokeCreditLine(?string $allocationConfigId): array
    {
        if ($allocationConfigId === null || trim($allocationConfigId) === '') {
            return ['success' => true, 'message' => 'No credit allocation to revoke.'];
        }

        if (! WhatsAppPlatformConfig::hasSolutionPartnerCredentials()) {
            return [
                'success' => false,
                'message' => 'Solution Partner credentials are not configured; cannot revoke credit line automatically.',
            ];
        }

        $systemToken = WhatsAppPlatformConfig::creditSharingSystemToken();
        $result = $this->graph->delete(trim($allocationConfigId), $systemToken);

        if ($result['ok'] || $this->isAlreadyRevokedError($result)) {
            return ['success' => true];
        }

        $error = $result['data']['error']['message'] ?? 'Credit line revocation failed';

        Log::warning('WhatsApp credit line revoke failed', [
            'allocation_config_id' => $allocationConfigId,
            'status' => $result['status'],
            'error' => $result['data']['error'] ?? $result['body'],
        ]);

        return [
            'success' => false,
            'message' => 'Failed to revoke credit line from Meta: ' . $error,
        ];
    }

    protected function isAlreadySharedError(array $result): bool
    {
        $message = strtolower((string) ($result['data']['error']['message'] ?? ''));

        return str_contains($message, 'already')
            || str_contains($message, 'attached')
            || str_contains($message, 'duplicate');
    }

    protected function isAlreadyRevokedError(array $result): bool
    {
        $message = strtolower((string) ($result['data']['error']['message'] ?? ''));
        $status = strtolower((string) ($result['data']['request_status'] ?? ''));

        return str_contains($message, 'not found')
            || str_contains($message, 'deleted')
            || $status === 'deleted';
    }
}
