<?php

namespace App\Services\Workflow;

use App\DTOs\ConversationState;
use App\Enums\ResponseSpec;
use App\Models\Company;
use App\Models\Product;
use App\Support\MoneyFormatter;

final class ResponseSpecRenderer
{
    public const RENDERER_VERSION = 'v2.3.0-conversational-os';

    public function render(
        ResponseSpec $spec,
        ConversationState $state,
        Company $company,
        array $domainFacts = []
    ): string {
        return match ($spec) {
            ResponseSpec::CART_SUMMARY => $this->renderCartSummary($state, $company, $domainFacts),
            ResponseSpec::PROMPT_VARIANT_SELECTION => $this->renderVariantPrompt($domainFacts),
            ResponseSpec::PROMPT_DELIVERY_ADDRESS => "What is your delivery address?\n(Reply with street, building, or area name, or say 'pickup' if picking up in store).",
            ResponseSpec::REPROMPT_DELIVERY_ADDRESS => "Please provide a valid delivery address (street, building, or area name), or reply 'pickup' to pick up your order.",
            ResponseSpec::PROMPT_ORDER_CONFIRMATION => $this->renderOrderConfirmationPrompt($state, $company),
            ResponseSpec::PROMPT_PAYMENT_SELECTION => $this->renderPaymentSelectionPrompt($company),
            ResponseSpec::PROMPT_MPESA_PHONE => "We'll send an M-Pesa payment request to your phone.\n\nReply with:\n1 - Send to this number ({$state->customerPhone})\n2 - Use a different number",
            ResponseSpec::ORDER_RECEIPT_CONFIRMATION => $this->renderOrderReceipt($domainFacts),
            ResponseSpec::PAYMENT_INSTRUCTIONS => $this->renderPaymentInstructions($domainFacts),
            ResponseSpec::MPESA_PUSH_SENT_NOTICE => "📱 M-Pesa STK Push prompt sent to ".($domainFacts['phone'] ?? $state->customerPhone).". Enter your M-Pesa PIN on your phone to complete payment.",
            ResponseSpec::CLARIFICATION_NEEDED => $domainFacts['clarification_prompt'] ?? "Could you please clarify which item you'd like to select?",
            ResponseSpec::GENERAL_ASSIST => $domainFacts['assist_reply'] ?? "How can I help you today?",
        };
    }

    public static function renderCatalogPrompt(Company $company): string
    {
        $products = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            return "🛍️ Our product catalog is currently being updated. Please check back shortly or visit our online store!";
        }

        $lines = ["🛍️ *Here's our product catalog:*", ""];
        foreach ($products as $idx => $prod) {
            $num = $idx + 1;
            $formattedPrice = MoneyFormatter::format((float) $prod->price, $company->currency ?? 'USD');
            $lines[] = "{$num}. *{$prod->name}* — {$formattedPrice}";
            if (! empty($prod->description)) {
                $shortDesc = mb_substr(strip_tags($prod->description), 0, 80);
                $lines[] = "   _{$shortDesc}_";
            }
        }

        $lines[] = "";
        $lines[] = "Reply with the *product name or number* to order!";

        return implode("\n", $lines);
    }

    public static function getActivePaymentMethods(Company $company): array
    {
        $settings = $company->settings;
        $methods = [];

        if ($settings?->orders_accept_mpesa ?? true) {
            $methods[] = ['key' => 'mpesa', 'label' => 'M-Pesa'];
        }
        if ($settings?->orders_accept_pesapal ?? false) {
            $methods[] = ['key' => 'pesapal', 'label' => 'Pesapal (Card / M-Pesa / Mobile)'];
        }
        if ($settings?->orders_accept_paystack ?? false) {
            $methods[] = ['key' => 'paystack', 'label' => 'Paystack (Card / Bank / Mobile)'];
        }
        if ($settings?->orders_accept_stripe ?? false) {
            $methods[] = ['key' => 'stripe', 'label' => 'Stripe (Credit / Debit Card)'];
        }
        if ($settings?->orders_accept_flutterwave ?? false) {
            $methods[] = ['key' => 'flutterwave', 'label' => 'Flutterwave'];
        }
        if ($settings?->orders_accept_cod ?? true) {
            $methods[] = ['key' => 'cod', 'label' => 'Cash on Delivery / Manual'];
        }

        if (empty($methods)) {
            $methods[] = ['key' => 'mpesa', 'label' => 'M-Pesa'];
            $methods[] = ['key' => 'cod', 'label' => 'Cash on Delivery / Manual'];
        }

        return $methods;
    }

    private function renderPaymentSelectionPrompt(Company $company): string
    {
        $methods = self::getActivePaymentMethods($company);
        $lines = ["💳 *Please select your preferred payment method:*"];

        foreach ($methods as $idx => $m) {
            $num = $idx + 1;
            $lines[] = "{$num} - {$m['label']}";
        }

        $lines[] = "\nReply with the option number (e.g. 1) or payment method name!";

        return implode("\n", $lines);
    }

    private function renderCartSummary(ConversationState $state, Company $company, array $domainFacts): string
    {
        $addedName = $domainFacts['added_product_name'] ?? null;
        $addedQty = $domainFacts['added_product_qty'] ?? 1;

        $lines = [];
        if ($addedName) {
            $lines[] = "✅ *Added to cart*";
            $lines[] = "*{$addedName}* x {$addedQty}";
            $lines[] = "";
        }

        if (! $state->hasItems()) {
            return "🛒 Your cart is currently empty.\n\nReply with a product name or 'prices' to browse products!";
        }

        $lines[] = "🛒 *Your Cart Summary:*";
        $total = 0.0;
        foreach ($state->cartItems as $item) {
            $name = $item['name'] ?? 'Item';
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0.0);
            $lineTotal = $price * $qty;
            $total += $lineTotal;
            $formattedPrice = MoneyFormatter::format($price, $company->currency ?? 'USD');
            if ($qty > 1) {
                $formattedLineTotal = MoneyFormatter::format($lineTotal, $company->currency ?? 'USD');
                $lines[] = "• *{$name}* — {$qty} x {$formattedPrice} ({$formattedLineTotal})";
            } else {
                $lines[] = "• *{$name}* — 1 x {$formattedPrice}";
            }
        }

        $formattedTotal = MoneyFormatter::format($total, $company->currency ?? 'USD');
        $lines[] = "\n*Total:* {$formattedTotal}";
        $lines[] = "\n📋 *Next steps:*";
        $lines[] = "• Reply with a *product name/number* to add more items";
        $lines[] = "• Reply *'checkout'* or *'done'* to place your order";

        return implode("\n", $lines);
    }

    private function renderOrderConfirmationPrompt(ConversationState $state, Company $company): string
    {
        $lines = [];
        $lines[] = "📋 *Order Review:*";

        if ($state->fulfillmentType === 'pickup') {
            $lines[] = "📦 Fulfillment: *Store Pickup*";
        } elseif ($state->deliveryAddress) {
            $lines[] = "📍 Delivery Address: *{$state->deliveryAddress}*";
        }

        $total = 0.0;
        foreach ($state->cartItems as $item) {
            $name = $item['name'] ?? 'Item';
            $qty = (int) ($item['quantity'] ?? 1);
            $price = (float) ($item['price'] ?? 0.0);
            $lineTotal = $price * $qty;
            $total += $lineTotal;
            $formattedPrice = MoneyFormatter::format($price, $company->currency ?? 'USD');
            if ($qty > 1) {
                $formattedLineTotal = MoneyFormatter::format($lineTotal, $company->currency ?? 'USD');
                $lines[] = "• *{$name}* — {$qty} x {$formattedPrice} ({$formattedLineTotal})";
            } else {
                $lines[] = "• *{$name}* — 1 x {$formattedPrice}";
            }
        }

        $formattedTotal = MoneyFormatter::format($total, $company->currency ?? 'USD');
        $lines[] = "\n*Total:* {$formattedTotal}";
        $lines[] = "\nWhat would you like to do next?";
        $lines[] = "1 - Confirm & place order";
        $lines[] = "2 - Cancel";

        return implode("\n", $lines);
    }

    private function renderOrderReceipt(array $domainFacts): string
    {
        $order = $domainFacts['order'] ?? null;
        if (! $order) {
            return "Order confirmed! Thank you for your purchase.";
        }

        return "🎉 *Order Confirmed!*\nOrder #: *{$order->order_number}*\nTotal: *{$order->formatted_total}*\nWe'll prepare it and contact you shortly.";
    }

    private function renderVariantPrompt(array $domainFacts): string
    {
        $product = $domainFacts['product'] ?? null;
        $variants = $domainFacts['variants'] ?? [];
        $prodName = $product->name ?? 'Product';

        $lines = [];
        $lines[] = "Sure! To help you order *{$prodName}*, please select your preferred option:";
        foreach ($variants as $idx => $var) {
            $num = $idx + 1;
            $varName = is_array($var) ? ($var['label'] ?? $var['name'] ?? 'Option') : ($var->label ?? $var->name ?? 'Option');
            $varPrice = MoneyFormatter::format((float) (is_array($var) ? ($var['price'] ?? 0) : ($var->price ?? $product->price ?? 0)), 'USD');
            $lines[] = "{$num} - {$varName} ({$varPrice})";
        }

        $lines[] = "\nReply with the option number (e.g. 1) or color/option name!";

        return implode("\n", $lines);
    }

    private function renderPaymentInstructions(array $domainFacts): string
    {
        $instructions = $domainFacts['instructions'] ?? null;
        $payUrl = $domainFacts['pay_url'] ?? null;

        $lines = ["Payment details:"];
        if ($instructions) {
            $lines[] = $instructions;
        }
        if ($payUrl) {
            $lines[] = "\nPay Link: {$payUrl}";
        }

        return implode("\n", $lines);
    }
}
