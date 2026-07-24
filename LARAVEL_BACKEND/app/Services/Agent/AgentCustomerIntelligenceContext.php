<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Support\MoneyFormatter;
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

        $orders = Order::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($phone, $customerPhone) {
                $q->where('customer_phone', $customerPhone)
                    ->orWhere('customer_phone', $phone)
                    ->orWhere('customer_phone', 'like', '%'.substr($phone, -9).'%');
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'order_number', 'status', 'payment_status', 'total', 'currency', 'created_at', 'customer_name']);

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

        if ($incomingMessage !== null && trim($incomingMessage) !== '') {
            $parts[] = 'Current customer message (respond to this intent fully): '.trim($incomingMessage);
        }

        $parts[] = <<<'OS'
Operating rules (business OS — not a rigid script):
1. Speak like a confident, helpful human sales/support teammate for THIS business. Fluent full sentences. Match the customer's language and energy.
2. Use tools for facts: products, FAQ, knowledge, orders, payments, delivery, memory. Reason from tool results — do not guess.
3. Remember the person: use customer memory + order history to personalize (e.g. past purchases, preferences). Persist new facts with remember_customer.
4. Sell with integrity: recommend real catalog items, explain value briefly, handle objections, offer clear next steps (browse, order, pay, talk to human).
5. Own the full journey: discovery → recommendation → cart/order → address → payment → tracking → refunds/help. Use process_order_message and payment tools when buying; stay conversational, not menu-robot.
6. If unsure, say you will confirm — never invent policies, prices, or stock. Escalate with transfer_to_human when the customer asks for a person or risk is high.
7. You ARE the front line of this business. Act with the owner's knowledge and care.
OS;

        return implode("\n\n", $parts);
    }
}
