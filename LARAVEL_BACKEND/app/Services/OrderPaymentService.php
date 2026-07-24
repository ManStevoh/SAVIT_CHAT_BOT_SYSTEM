<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Services\Agent\AgentProactiveMessageService;
use App\Services\Agent\Platform\CommerceExperimentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles payment collection for customer orders: M-Pesa STK push, Stripe, and Paystack payment links.
 * Used by the bot after order confirm and by callbacks when payment is received.
 */
class OrderPaymentService
{
    public const CACHE_KEY_ORDER_PREFIX = 'mpesa_pending_order:';

    public const CACHE_TTL_MINUTES = 30;

    public function __construct(
        protected MpesaService $mpesa,
        protected StripeService $stripe,
        protected PaystackService $paystack,
        protected WhatsAppMessageSenderService $waSender,
        protected OrderFulfillmentService $fulfillmentService,
    ) {}

    /**
     * Send M-Pesa STK push for an order. Uses company's own M-Pesa config if set, otherwise platform config.
     *
     * @return array{success: bool, checkout_request_id?: string, error?: string}
     */
    public function sendStkPushForOrder(Order $order, string $phone): array
    {
        $settings = $order->company?->settings;
        $companyMpesa = $settings?->order_payment_mpesa_config;
        $useCompanyConfig = is_array($companyMpesa) && ! empty($companyMpesa['shortcode']) && ! empty($companyMpesa['passkey']);

        if (! $useCompanyConfig && ! MpesaService::isEnabled()) {
            return ['success' => false, 'error' => 'M-Pesa is not configured.'];
        }

        $platformConfig = PaymentGateway::getConfig('mpesa');
        $callbackUrl = $platformConfig['callback_url'] ?? '';
        if (! $callbackUrl) {
            return ['success' => false, 'error' => 'M-Pesa callback URL not configured. Contact support.'];
        }

        $amount = (float) $order->total;
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Order total must be greater than zero.'];
        }

        $configOverride = null;
        if ($useCompanyConfig) {
            $configOverride = array_merge($platformConfig, $companyMpesa);
            $configOverride['callback_url'] = $callbackUrl;
        }

        $result = $this->mpesa->stkPush(
            $phone,
            $amount,
            $order->order_number,
            'Order '.$order->order_number,
            $callbackUrl,
            $configOverride
        );

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        $checkoutRequestId = $result['CheckoutRequestID'] ?? null;
        if (! $checkoutRequestId) {
            return ['success' => false, 'error' => $result['ResponseDescription'] ?? 'STK push failed'];
        }

        Cache::put(self::CACHE_KEY_ORDER_PREFIX.$checkoutRequestId, [
            'order_id' => $order->id,
            'expected_amount' => $amount,
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return [
            'success' => true,
            'checkout_request_id' => $checkoutRequestId,
        ];
    }

    /**
     * Create a one-time Stripe Checkout URL for the order. Uses company's own Stripe config if set, otherwise platform config.
     *
     * @return array{success: bool, url?: string, error?: string}
     */
    public function createStripePaymentLinkForOrder(Order $order): array
    {
        $settings = $order->company?->settings;
        $companyStripe = $settings?->order_payment_stripe_config;
        $useCompanyConfig = is_array($companyStripe) && ! empty($companyStripe['secret']);

        if (! $useCompanyConfig && ! StripeService::isEnabled()) {
            return ['success' => false, 'error' => 'Online payment is not configured.'];
        }

        $url = $this->stripe->createOneTimePaymentSessionForOrder($order, $useCompanyConfig ? $companyStripe : null);
        if (! $url) {
            return ['success' => false, 'error' => 'Could not create payment link. Please try again.'];
        }

        return ['success' => true, 'url' => $url];
    }

    /**
     * Create a Paystack payment link for an order (platform config).
     *
     * @return array{success: bool, url?: string, reference?: string, error?: string}
     */
    public function createPaystackPaymentLinkForOrder(Order $order): array
    {
        if (! PaystackService::isEnabled()) {
            return ['success' => false, 'error' => 'Paystack is not configured.'];
        }

        $callbackUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')).'/orders/payment-complete';
        $result = $this->paystack->createPaymentLinkForOrder($order, $callbackUrl);
        if (! $result['success'] || empty($result['url'])) {
            return ['success' => false, 'error' => $result['error'] ?? 'Could not create payment link.'];
        }

        $reference = $result['reference'] ?? null;
        if ($reference) {
            Cache::put(
                PaystackService::CACHE_KEY_ORDER_PREFIX.$reference,
                ['order_id' => $order->id],
                now()->addMinutes(self::CACHE_TTL_MINUTES)
            );
        }

        return [
            'success' => true,
            'url' => $result['url'],
            'reference' => $reference,
        ];
    }

    /**
     * Mark order as paid and send WhatsApp confirmation to the customer.
     */
    public function markOrderPaid(Order $order): void
    {
        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        $fresh = $order->fresh(['orderProducts', 'chat', 'company.whatsappAccount']);
        app(DigitalAccessService::class)->preparePaidOrder($fresh);

        $this->recordExperimentConversionIfAssigned($order);
        $this->sendPaymentConfirmationToCustomer($fresh->fresh(['orderProducts', 'chat', 'company.whatsappAccount']));
        $this->fulfillmentService->sendPaidFulfillment($fresh->fresh(['orderProducts', 'chat', 'company.whatsappAccount']));
    }

    private function recordExperimentConversionIfAssigned(Order $order): void
    {
        $assign = Cache::get("exp_assign:order:{$order->id}");
        if (! is_array($assign)) {
            return;
        }

        $experimentId = (int) ($assign['experiment_id'] ?? 0);
        $variantId = (int) ($assign['variant_id'] ?? 0);
        if ($experimentId <= 0 || $variantId <= 0) {
            return;
        }

        app(CommerceExperimentService::class)->recordConversion(
            $experimentId,
            $variantId,
            (float) $order->total,
        );
        Cache::forget("exp_assign:order:{$order->id}");
    }

    /**
     * Send a WhatsApp message to the customer for this order (e.g. "Payment received. Order #X confirmed.").
     */
    public function sendPaymentConfirmationToCustomer(Order $order): void
    {
        $chat = $order->chat;
        if (! $chat) {
            return;
        }

        $company = $order->company;
        $account = $company?->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            Log::warning('OrderPaymentService: cannot send WhatsApp, no active account', ['order_id' => $order->id]);

            return;
        }

        $message = app(AgentProactiveMessageService::class)->paymentReceivedMessage($order)
            ?? "Payment received. Your order #{$order->order_number} is confirmed. Thank you!\n\nView invoice / receipt:\n".$order->publicReceiptUrl();
        $to = $order->customer_phone ?: $chat->customer_phone;
        if (! $to) {
            return;
        }

        $result = $this->waSender->sendText($account, $to, $message);
        if ($result['success']) {
            Message::create([
                'chat_id' => $chat->id,
                'content' => $message,
                'sender' => 'bot',
                'status' => 'sent',
                'whatsapp_message_id' => $result['message_id'] ?? null,
            ]);
            $chat->update([
                'last_message' => $message,
                'last_message_at' => now(),
                'ai_handled' => true,
            ]);
        } else {
            Log::warning('OrderPaymentService: WhatsApp send failed', ['order_id' => $order->id, 'error' => $result['error'] ?? 'unknown']);
        }
    }
}
