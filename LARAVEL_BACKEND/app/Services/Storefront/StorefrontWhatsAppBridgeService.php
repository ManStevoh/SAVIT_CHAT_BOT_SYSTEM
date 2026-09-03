<?php

namespace App\Services\Storefront;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontSession;
use App\Models\WhatsAppAccount;
use App\Support\MoneyFormatter;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

/**
 * Links storefront shoppers to WhatsApp chats and sends commerce notifications.
 */
class StorefrontWhatsAppBridgeService
{
    public function __construct(
        protected WhatsAppMessageSenderService $whatsapp,
    ) {}

    public function resolveOrCreateChat(Company $company, string $phone, ?string $name = null): ?Chat
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $displayName = trim((string) $name);
        if ($displayName === '') {
            $displayName = 'Customer';
        }

        $chat = Chat::firstOrCreate(
            [
                'company_id' => $company->id,
                'customer_phone' => $digits,
            ],
            [
                'customer_name' => $displayName,
                'customer_avatar' => null,
                'last_message' => null,
                'last_message_at' => now(),
                'unread_count' => 0,
                'status' => 'active',
                'ai_handled' => false,
                'agent_handling_at' => null,
                'channel' => 'whatsapp',
            ]
        );

        if (! $chat->wasRecentlyCreated && $displayName !== 'Customer' && $chat->customer_name !== $displayName) {
            $chat->update(['customer_name' => $displayName]);
        }

        return $chat;
    }

    public function attachOrderToChat(Order $order): ?Chat
    {
        $phone = (string) ($order->customer_phone ?? '');
        if ($phone === '') {
            return null;
        }

        $company = $order->company;
        if (! $company) {
            return null;
        }

        $chat = $this->resolveOrCreateChat($company, $phone, $order->customer_name);
        if (! $chat) {
            return null;
        }

        if ((int) $order->chat_id !== (int) $chat->id) {
            $order->update(['chat_id' => $chat->id]);
            $order->setRelation('chat', $chat);
        }

        return $chat;
    }

    /**
     * Send order-placed WhatsApp confirmation with pay + track links.
     */
    public function notifyOrderPlaced(Order $order): bool
    {
        try {
            $order->loadMissing(['company.settings', 'chat', 'orderProducts']);
            $company = $order->company;
            if (! $company) {
                return false;
            }

            $company->loadMissing('settings');
            if (($company->settings?->storefront_whatsapp_order_notify ?? true) === false) {
                return false;
            }

            $chat = $order->chat ?: $this->attachOrderToChat($order);
            if (! $chat) {
                return false;
            }

            $account = WhatsAppAccount::where('company_id', $company->id)->where('status', 'active')->first();
            if (! $account) {
                return false;
            }

            $phone = preg_replace('/\D+/', '', (string) ($order->customer_phone ?: $chat->customer_phone)) ?? '';
            if ($phone === '') {
                return false;
            }

            $text = $this->composeOrderPlacedMessage($order);
            $result = $this->whatsapp->sendText($account, $phone, $text);
            if (! ($result['success'] ?? false)) {
                Log::warning('Storefront WhatsApp order notify failed', [
                    'order_id' => $order->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);

                return false;
            }

            Message::create([
                'chat_id' => $chat->id,
                'content' => $text,
                'sender' => 'bot',
                'status' => 'sent',
                'whatsapp_message_id' => $result['message_id'] ?? null,
            ]);
            $chat->update([
                'last_message' => $text,
                'last_message_at' => now(),
                'ai_handled' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Storefront WhatsApp order notify exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function composeOrderPlacedMessage(Order $order): string
    {
        $order->loadMissing(['company.settings', 'orderProducts']);
        $company = $order->company;
        $settings = $company?->settings;
        $currency = $settings?->displayCurrencyCode() ?? 'KES';
        $moneyOpts = MoneyFormatter::displayOptionsFromSettings($settings);
        $total = MoneyFormatter::format((float) $order->total, $currency, $moneyOpts);

        $lines = [
            'Thanks'.($order->customer_name ? ' '.$order->customer_name : '').'!',
            'Your order #'.$order->order_number.' from '.($company?->name ?? 'us').' is confirmed.',
            'Total: '.$total,
            '',
        ];

        $items = $order->orderProducts->take(5)->map(fn ($i) => '• '.$i->quantity.'× '.$i->name)->all();
        if ($items !== []) {
            $lines = array_merge($lines, $items, ['']);
        }

        $order->ensurePublicTokens();
        $lines[] = 'Pay / complete payment:';
        $lines[] = $order->publicPayUrl();
        $lines[] = '';

        if ($company?->store_slug) {
            $lines[] = 'Track your order:';
            $lines[] = rtrim((string) config('app.url'), '/').'/s/'.$company->store_slug.'/track';
        }

        $lines[] = '';
        $lines[] = 'Reply here if you need help with this order.';

        return implode("\n", $lines);
    }

    /**
     * @return array{text: string, item_count: int, total: float}
     */
    public function composeAbandonedCartMessage(Company $company, StorefrontSession $session): array
    {
        $cart = is_array($session->cart) ? $session->cart : [];
        $itemCount = 0;
        $subtotal = 0.0;
        foreach ($cart as $line) {
            $qty = max(0, (int) ($line['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $product = Product::find($line['product_id'] ?? null);
            $price = $product
                ? (float) $product->price
                : (float) ($line['price'] ?? 0);
            if (! empty($line['product_variant_id']) && $product) {
                $variant = $product->variants()->find($line['product_variant_id']);
                if ($variant) {
                    $price = (float) $variant->price;
                }
            }
            $itemCount += $qty;
            $subtotal += $qty * $price;
        }

        $company->loadMissing('settings');
        $currency = $company->settings?->displayCurrencyCode() ?? 'KES';
        $moneyOpts = MoneyFormatter::displayOptionsFromSettings($company->settings);
        $totalLabel = MoneyFormatter::format($subtotal, $currency, $moneyOpts);
        $cartUrl = rtrim((string) config('app.url'), '/').'/s/'.$company->store_slug.'/cart';

        $greeting = 'Hi'.($session->customer_name ? ' '.$session->customer_name : '').'!';
        $summary = $itemCount > 0
            ? "You left {$itemCount} item".($itemCount === 1 ? '' : 's')." (about {$totalLabel}) in your cart at {$company->name}."
            : "You left items in your cart at {$company->name}.";

        $text = $greeting."\n".$summary."\nComplete your order here:\n".$cartUrl;

        return [
            'text' => $text,
            'item_count' => $itemCount,
            'total' => round($subtotal, 2),
        ];
    }
}
