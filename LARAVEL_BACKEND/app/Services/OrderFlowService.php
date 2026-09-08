<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Services\Workflow\ResponseSpecRenderer;

/**
 * @deprecated Decommissioned in favor of ConversationalOSPipeline and WorkflowEngine.
 * Maintained only for legacy backward compatibility and test mock safety.
 */
class OrderFlowService
{
    public const STEP_NONE = null;
    public const STEP_PRODUCT = 'product';
    public const STEP_VARIANT = 'variant';
    public const STEP_PRODUCT_QTY = 'product_qty';
    public const STEP_ADDRESS = 'address';
    public const STEP_CONFIRM = 'confirm';
    public const STEP_PAYMENT_METHOD = 'payment_method';
    public const STEP_MPESA_PHONE = 'mpesa_phone';
    public const STEP_EXISTING_ORDER_ADDRESS = 'existing_order_address';
    public const STEP_EXISTING_ORDER_PAYMENT_METHOD = 'existing_order_payment_method';
    public const STEP_EXISTING_ORDER_PROMPT = 'existing_order_prompt';
    public const STEP_TRACKING_ACTIONS = 'tracking_actions';

    public function formatCatalogForDisplay(Company $company): string
    {
        return ResponseSpecRenderer::renderCatalogPrompt($company);
    }

    public function resetOrderState(Chat $chat): void
    {
        \App\Services\Conversation\ConversationStateHydrator::resetChatState($chat);
    }

    public function initializeProductStep(Chat $chat, Company $company): void
    {
        $draft = is_array($chat->order_draft) ? $chat->order_draft : [];
        $draft['items'] = $draft['items'] ?? [];
        $chat->update([
            'conversation_step' => self::STEP_PRODUCT,
            'order_draft' => $draft,
        ]);
    }

    public function getRememberedCustomerAddress(Chat $chat, Company $company): ?string
    {
        $query = Order::where('company_id', $company->id)
            ->whereNotNull('delivery_address')
            ->where('delivery_address', '!=', '')
            ->where('delivery_address', '!=', 'Store Pickup')
            ->where('delivery_address', '!=', 'Dine-In')
            ->where('delivery_address', '!=', 'N/A');

        if ($chat->customer_phone) {
            $query->where(function ($q) use ($chat) {
                $q->where('chat_id', $chat->id)
                  ->orWhere('customer_phone', $chat->customer_phone);
            });
        } else {
            $query->where('chat_id', $chat->id);
        }

        $addr = $query->orderByDesc('id')->value('delivery_address');
        return $addr ? trim((string) $addr) : null;
    }
}
