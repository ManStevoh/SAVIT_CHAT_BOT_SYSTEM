<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Support\MoneyFormatter;
use App\Services\Orders\OrderPaymentDetailsService;
use Illuminate\Support\Carbon;

/**
 * Rich per-customer context so the agent operates as the business OS —
 * not a script: orders, open chats, catalog snapshot, relationship history.
 */
final class AgentCustomerIntelligenceContext
{
    public function build(
        Company $company,
        string $customerPhone,
        ?string $customerName,
        ?string $incomingMessage = null,
    ): string {
        $company->loadMissing('settings');
        $phone = preg_replace('/\D+/', '', $customerPhone) ?? $customerPhone;
        $parts = [];

        $who = $customerName ? trim($customerName) : 'Customer';
        $parts[] = "Live customer session:\n- Name: {$who}\n- Phone: {$customerPhone}";

        $orders = collect();
        try {
            $orders = Order::query()
                ->where('company_id', $company->id)
                ->where(function ($q) use ($phone, $customerPhone) {
                    $q->where('customer_phone', $customerPhone)
                        ->orWhere('customer_phone', $phone)
                        ->orWhere('customer_phone', 'like', '%'.substr($phone, -9).'%');
                })
                ->orderByDesc('id')
                ->limit(8)
                ->get(['id', 'order_number', 'status', 'payment_status', 'total', 'created_at', 'customer_name']);
        } catch (\Throwable $e) {
            // Don't let schema drift (e.g. missing optional columns) kill auto-replies.
            \Illuminate\Support\Facades\Log::warning('AgentCustomerIntelligenceContext: orders lookup failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($orders->isNotEmpty()) {
            $ccy = $company->settings?->displayCurrencyCode() ?? 'USD';
            $lines = ['Recent orders for this customer (authoritative — use search_orders for full detail):'];
            foreach ($orders as $order) {
                $total = MoneyFormatter::format((float) $order->total, $ccy);
                $when = $order->created_at instanceof Carbon
                    ? $order->created_at->toFormattedDateString()
                    : (string) $order->created_at;
                $lines[] = sprintf(
                    '- #%s | %s | payment=%s | %s | %s',
                    $order->order_number ?: $order->id,
                    $order->status,
                    $order->payment_status ?? 'unknown',
                    $total,
                    $when
                );
            }
            $parts[] = implode("\n", $lines);
        } else {
            $parts[] = 'Recent orders: none on file for this phone yet (new or returning without prior orders).';
        }

        $openChats = Chat::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($phone, $customerPhone) {
                $q->where('customer_phone', $customerPhone)
                    ->orWhere('customer_phone', $phone);
            })
            ->where('status', '!=', 'closed')
            ->orderByDesc('last_message_at')
            ->limit(3)
            ->get(['id', 'status', 'last_message', 'conversation_step']);

        if ($openChats->isNotEmpty()) {
            $lines = ['Open conversation state:'];
            foreach ($openChats as $c) {
                $step = $c->conversation_step ? " step={$c->conversation_step}" : '';
                $snippet = mb_substr(trim((string) $c->last_message), 0, 80);
                $lines[] = "- chat #{$c->id} status={$c->status}{$step} last=\"{$snippet}\"";
            }
            $parts[] = implode("\n", $lines);
        }

        $productCount = Product::where('company_id', $company->id)->where('status', 'active')->count();
        $parts[] = "Catalog size: {$productCount} active products. Prefer tools search_products / get_catalog for precise stock and variants; never invent SKUs or prices.";

        $parts[] = app(OrderPaymentDetailsService::class)->promptBlockForCompany($company);

        if ($incomingMessage !== null && trim($incomingMessage) !== '') {
            $parts[] = 'Current customer message (respond to this intent fully): '.trim($incomingMessage);
        }

        $parts[] = <<<'OS'
Operating rules (business OS — not a rigid script):
1. Speak like a confident, helpful human sales/support teammate for THIS business. Fluent full sentences. Match the customer's language and energy.
2. Infer intent from meaning — any wording, language, or shorthand. Never require fixed phrases.
3. Use tools for facts and for do-actions (orders, payments, documents, delivery, refunds, memory). Reason from tool results — do not guess or invent.
4. When the customer wants something done, call the matching capability immediately. Do not say you will send/check/create something and then skip the tool.
5. Invoice / bill / receipt: call send_order_invoice. Pay / payment details / till / how to pay: call share_payment_details. Do not transfer_to_human for those.
6. Remember the person: use customer memory + order history; persist new facts with remember_customer.
7. Sell with integrity: recommend real catalog items, explain value briefly, offer clear next steps.
8. Own the full journey conversationally (discover → recommend → order → pay → track → resolve) via tools — not menu robots.
9. Escalate with transfer_to_human only when the customer clearly wants a person, risk is high, or no available tool can fulfill the request. If they say not to transfer, do not transfer.
10. You ARE the front line of this business. Act with the owner's knowledge and care.
OS;

        return implode("\n\n", $parts);
    }
}
