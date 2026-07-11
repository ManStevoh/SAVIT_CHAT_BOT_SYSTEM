<?php

namespace App\Services\Agent\Platform;

use App\Models\AgentActionRequest;
use App\Models\CompanyPolicyRule;
use App\Models\Order;
use App\Models\User;

/**
 * ABI Level 7 — org-chart approval routing by role and amount limits.
 */
final class CompanyPolicyService
{
    /**
     * @return array{allowed: bool, reason?: string, requires_role?: string}
     */
    public function canApprove(User $user, AgentActionRequest $request): array
    {
        $action = $request->action_type;
        $rules = CompanyPolicyRule::where('company_id', $request->company_id)
            ->where('action_type', $action)
            ->where('is_active', true)
            ->get();

        if ($rules->isEmpty()) {
            return $this->checkConfigDefaults($user, $request);
        }

        foreach ($rules as $rule) {
            if ($rule->requires_role && $user->role !== $rule->requires_role) {
                return [
                    'allowed' => false,
                    'reason' => 'This action requires '.$rule->requires_role.' approval.',
                    'requires_role' => $rule->requires_role,
                ];
            }

            if ($rule->subject_role && $user->role === $rule->subject_role && $rule->max_amount !== null) {
                $amount = $this->resolveAmount($request);
                if ($amount > (float) $rule->max_amount) {
                    return [
                        'allowed' => false,
                        'reason' => 'Amount exceeds '.$rule->subject_role.' limit of '.$rule->max_amount.'.',
                        'requires_role' => 'company_owner',
                    ];
                }
            }
        }

        return ['allowed' => true];
    }

    /**
     * @return array{allowed: bool, reason?: string, requires_role?: string}
     */
    private function checkConfigDefaults(User $user, AgentActionRequest $request): array
    {
        $policies = config('agent.platform.approval_policies', []);
        $policy = $policies[$request->action_type] ?? null;

        if (! is_array($policy)) {
            if ($request->risk_level === 'high' && $user->role !== 'company_owner') {
                return [
                    'allowed' => false,
                    'reason' => 'High-risk actions require company owner approval.',
                    'requires_role' => 'company_owner',
                ];
            }

            return ['allowed' => true];
        }

        $requiredRole = $policy['requires_role'] ?? null;
        if ($requiredRole && $user->role !== $requiredRole) {
            return [
                'allowed' => false,
                'reason' => 'This action requires '.$requiredRole.' approval.',
                'requires_role' => $requiredRole,
            ];
        }

        $maxForRole = $policy['max_amount_'.$user->role] ?? null;
        if ($maxForRole !== null) {
            $amount = $this->resolveAmount($request);
            if ($amount > (float) $maxForRole) {
                return [
                    'allowed' => false,
                    'reason' => 'Amount exceeds your approval limit.',
                    'requires_role' => 'company_owner',
                ];
            }
        }

        return ['allowed' => true];
    }

    private function resolveAmount(AgentActionRequest $request): float
    {
        $payload = $request->payload ?? [];
        $arguments = is_array($payload['arguments'] ?? null) ? $payload['arguments'] : $payload;

        if ($request->action_type === 'issue_order_refund') {
            $orderNumber = trim((string) ($arguments['order_number'] ?? ''));
            if ($orderNumber !== '') {
                $order = Order::where('company_id', $request->company_id)
                    ->where('order_number', $orderNumber)
                    ->first();
                if ($order) {
                    return (float) $order->total;
                }
            }
        }

        return (float) ($arguments['amount'] ?? $payload['amount'] ?? 0);
    }
}
