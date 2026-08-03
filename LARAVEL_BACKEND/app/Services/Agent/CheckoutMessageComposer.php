<?php

namespace App\Services\Agent;

use App\Models\Product;
use App\Services\OrderFlowService;

/**
 * Turns natural / shorthand customer checkout phrasing into OrderFlowService commands.
 * The agent must synthesize these — never ask the customer to type a magic phrase.
 */
final class CheckoutMessageComposer
{
    /**
     * @return list<string> Ordered process_order_message payloads to try after the raw message.
     */
    public function candidateMessages(AgentToolContext $context, string $rawMessage): array
    {
        $raw = trim($rawMessage);
        if ($raw === '') {
            return [];
        }

        $candidates = [];
        $lower = mb_strtolower($raw);
        $step = $context->chat->conversation_step;
        $draft = is_array($context->chat->order_draft) ? $context->chat->order_draft : [];
        $hasItems = ! empty($draft['items']);

        $qtyProduct = $this->composeQtyProductLine($context, $raw);
        if ($qtyProduct !== null) {
            $candidates[] = $qtyProduct;
        }

        $isPureNumber = (bool) preg_match('/^\d+$/', $lower);
        if ($isPureNumber && ($step === OrderFlowService::STEP_CONFIRM || $step === 'confirm') && $lower === '1') {
            $candidates[] = 'confirm';
        }

        if (! $isPureNumber && ($this->looksLikeAffirm($lower) || $this->looksLikePayIntent($lower))) {
            if ($step === OrderFlowService::STEP_CONFIRM || $step === 'confirm') {
                $candidates[] = 'confirm';
            } elseif ($step === OrderFlowService::STEP_PRODUCT && $hasItems) {
                $candidates[] = 'done';
                $candidates[] = 'confirm';
            } elseif ($step === OrderFlowService::STEP_ADDRESS) {
                // Keep address step alone — pay wording is not an address.
            } elseif (! $step || $step === OrderFlowService::STEP_NONE) {
                if ($qtyProduct === null) {
                    $product = $this->resolveProductNameFromThread($context);
                    $qty = $this->resolveQuantityFromThread($context, $raw) ?? 1;
                    if ($product !== null) {
                        $candidates[] = "{$qty} x {$product}";
                        $candidates[] = 'done';
                        $candidates[] = 'confirm';
                    }
                } else {
                    $candidates[] = 'done';
                    $candidates[] = 'confirm';
                }
            } elseif ($step === OrderFlowService::STEP_PRODUCT_QTY) {
                $qty = $this->extractLeadingQuantity($raw);
                if ($qty !== null) {
                    $candidates[] = (string) $qty;
                }
            }
        }

        // Qty-only shorthand while a product is pending ("10x" / "10 x").
        if ($step === OrderFlowService::STEP_PRODUCT_QTY) {
            $qty = $this->extractLeadingQuantity($raw);
            if ($qty !== null) {
                $candidates[] = (string) $qty;
            }
        }

        // Deduplicate while preserving order; skip identical to raw (already tried).
        $out = [];
        $seen = [mb_strtolower($raw) => true];
        foreach ($candidates as $c) {
            $c = trim($c);
            if ($c === '') {
                continue;
            }
            $key = mb_strtolower($c);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
        }

        return $out;
    }

    public function looksLikePayIntent(string $lower): bool
    {
        $lower = trim($lower);
        if ($lower === '') {
            return false;
        }
        if (preg_match('/\b(pay|payment|mpesa|m-pesa|till|paybill|invoice|receipt|stk)\b/u', $lower)) {
            return true;
        }

        return in_array($lower, ['pay', 'payment', 'lipa'], true);
    }

    public function looksLikeAffirm(string $lower): bool
    {
        $lower = trim($lower);
        if ($lower === '') {
            return false;
        }
        if (in_array($lower, [
            'ok', 'okay', 'k', 'yes', 'y', 'yeah', 'yep', 'yup', 'sure', 'sawa', 'saa',
            'confirm', 'confirmed', 'proceed', 'go ahead', 'go for it', 'place order',
            'confirm order', 'place it', 'do it', 'alright', 'all right', 'fine',
        ], true)) {
            return true;
        }

        return (bool) preg_match('/^(ok|okay|yes|sure|sawa|alright)\b/u', $lower);
    }

    private function composeQtyProductLine(AgentToolContext $context, string $raw): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', str_replace('*', ' x ', $raw)) ?? $raw);

        // "10x" / "10 x" / "10×" with missing or numeric-only name → fill product from thread.
        if (preg_match('/^(\d+)\s*[x×]\s*(.*)$/iu', $text, $m)) {
            $qty = (int) $m[1];
            $namePart = trim((string) ($m[2] ?? ''));
            if ($qty < 1) {
                return null;
            }
            if ($namePart === '' || preg_match('/^\d+$/', $namePart)) {
                $product = $this->resolveProductNameFromThread($context);
                if ($product !== null) {
                    return "{$qty} x {$product}";
                }

                return null;
            }

            // Already a full line — still normalize spacing for the flow parser.
            return "{$qty} x {$namePart}";
        }

        // "Headphones x 10"
        if (preg_match('/^(.+?)\s*[x×]\s*(\d+)$/iu', $text, $m)) {
            $namePart = trim($m[1]);
            $qty = (int) $m[2];
            if ($qty >= 1 && $namePart !== '' && ! preg_match('/^\d+$/', $namePart)) {
                return "{$qty} x {$namePart}";
            }
        }

        return null;
    }

    private function extractLeadingQuantity(string $raw): ?int
    {
        $text = trim($raw);
        if (preg_match('/^(\d+)\s*[x×]?\s*$/u', $text, $m)) {
            $qty = (int) $m[1];

            return ($qty >= 1 && $qty <= 999) ? $qty : null;
        }

        return null;
    }

    private function resolveQuantityFromThread(AgentToolContext $context, string $raw): ?int
    {
        $fromRaw = null;
        if (preg_match('/(\d+)\s*[x×]/u', $raw, $m)) {
            $fromRaw = (int) $m[1];
        }
        if ($fromRaw !== null && $fromRaw >= 1) {
            return $fromRaw;
        }

        $messages = $context->chat->messages()
            ->where('sender', 'customer')
            ->orderByDesc('id')
            ->limit(8)
            ->pluck('content');

        foreach ($messages as $content) {
            if (preg_match('/(\d+)\s*[x×]/u', (string) $content, $m)) {
                $qty = (int) $m[1];
                if ($qty >= 1 && $qty <= 999) {
                    return $qty;
                }
            }
        }

        return null;
    }

    private function resolveProductNameFromThread(AgentToolContext $context): ?string
    {
        $draft = is_array($context->chat->order_draft) ? $context->chat->order_draft : [];
        if (! empty($draft['pending_product_id'])) {
            $pending = Product::query()
                ->where('company_id', $context->company->id)
                ->where('id', (int) $draft['pending_product_id'])
                ->where('status', 'active')
                ->first();
            if ($pending) {
                return $pending->name;
            }
        }
        if (! empty($draft['items']) && is_array($draft['items'])) {
            $last = end($draft['items']);
            if (is_array($last) && ! empty($last['name'])) {
                return (string) $last['name'];
            }
        }

        $products = Product::query()
            ->where('company_id', $context->company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($products->isEmpty()) {
            return null;
        }

        $haystack = $context->chat->messages()
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('content')
            ->map(fn ($c) => mb_strtolower((string) $c))
            ->implode("\n");

        $incoming = mb_strtolower($context->incomingMessage);
        $haystack = $incoming."\n".$haystack;

        // Longest name match first to avoid short false positives.
        $sorted = $products->sortByDesc(fn (Product $p) => mb_strlen($p->name))->values();
        foreach ($sorted as $product) {
            $name = mb_strtolower($product->name);
            if ($name !== '' && str_contains($haystack, $name)) {
                return $product->name;
            }
        }

        // Bot recovery copy often quotes '10 x Headphones' — catch product after x.
        if (preg_match_all('/\d+\s*[x×]\s*([A-Za-z][\w\s\-]{1,80})/u', $haystack, $matches)) {
            foreach (array_reverse($matches[1]) as $part) {
                $part = trim($part, " \t\n\r\0\x0B'\"");
                foreach ($sorted as $product) {
                    if (str_contains(mb_strtolower($product->name), mb_strtolower($part))
                        || str_contains(mb_strtolower($part), mb_strtolower($product->name))) {
                        return $product->name;
                    }
                }
            }
        }

        if ($products->count() === 1) {
            return $products->first()->name;
        }

        return null;
    }
}
