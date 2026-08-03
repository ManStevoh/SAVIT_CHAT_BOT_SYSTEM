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
                ->get(['id', 'order_number', 'status', 'payment_status', 'subtotal', 'tax_total', 'total', 'created_at', 'customer_name']);
        } catch (\Throwable $e) {
            // Don't let schema drift (e.g. missing optional columns) kill auto-replies.
            \Illuminate\Support\Facades\Log::warning('AgentCustomerIntelligenceContext: orders lookup failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($orders->isNotEmpty()) {
            $ccy = $company->settings?->displayCurrencyCode() ?? 'USD';
            $moneyOpts = MoneyFormatter::displayOptionsFromSettings($company->settings);
            $lines = ['Recent orders for this customer (authoritative — use search_orders for full detail):'];
            foreach ($orders as $order) {
                $total = MoneyFormatter::format((float) $order->total, $ccy, $moneyOpts);
                $when = $order->created_at instanceof Carbon
                    ? $order->created_at->toFormattedDateString()
                    : (string) $order->created_at;
                $taxBit = '';
                $taxTotal = (float) ($order->tax_total ?? 0);
                if ($taxTotal > 0) {
                    $taxBit = ' | tax='.MoneyFormatter::format($taxTotal, $ccy, $moneyOpts);
                }
                $lines[] = sprintf(
                    '- #%s | %s | payment=%s | %s%s | %s',
                    $order->order_number ?: $order->id,
                    $order->status,
                    $order->payment_status ?? 'unknown',
                    $total,
                    $taxBit,
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
1. Speak like a confident, helpful human sales/support teammate for THIS business. Match language and energy.
2. Infer intent from meaning across the whole thread — any wording, language, or shorthand. Never require fixed phrases.
3. Continuity: if you offered to confirm an order, take payment, or send a document, treat the next customer reply as answering that offer and finish it with tools.
4. Use tools for facts and do-actions. Reason from tool results — never invent payment methods, prices, or stock.
5. When action is required, call the matching capability immediately. Do not promise then skip the tool. Do not hand off mid-thread unless the customer wants a person.
6. Checkout: call process_order_message with commands YOU synthesize (e.g. "10 x Headphones", "done", "confirm"). Never ask the customer to type a magic phrase like "10 x ProductName".
7. Remember the person via memory tools (remember_customer); sell with integrity from the real catalog.
8. You ARE the front line. Keep the conversation smooth and coherent for every customer style.
9. Payment & Confirmation: If the customer specifies a payment method (e.g., "pesapal", "paystack", "cod", "1", "2", "3") or says "yes"/"confirm"/"go ahead", call process_order_message or share_payment_details IMMEDIATELY. Do NOT ask them to confirm again.
OS;

        return implode("\n\n", $parts);
    }
}
