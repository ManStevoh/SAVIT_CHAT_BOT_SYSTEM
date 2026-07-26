<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\SocialPost;
use App\Services\OrderPaymentService;
use App\Services\Orders\TaxCalculationService;
use App\Services\WhatsAppMessageSenderService;
use App\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected function formatOrderStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst((string) $status),
        };
    }

    protected function buildOrderUpdateWhatsAppMessage(Order $order, ?string $oldStatus, ?string $oldPaymentStatus): string
    {
        $companyName = $order->company?->name ?: 'RelayIQ';
        $customerName = $order->customer_name ?: 'Customer';
        $settings = $order->company?->settings;

        $lines = [];
        $lines[] = "Hi {$customerName},";
        $lines[] = "Update on your order #{$order->order_number}:";

        if ($oldStatus !== $order->status) {
            $lines[] = "• Status: ".$this->formatOrderStatusLabel($order->status);
            $lines[] = match ($order->status) {
                'confirmed' => "We’ve confirmed your order and we’re preparing it.",
                'shipped' => "Your order has been shipped and is on the way.",
                'delivered' => "Your order has been delivered. Thank you for shopping with us!",
                'cancelled' => "Your order has been cancelled. Reply to this message if you’d like help.",
                default => "We’ll keep you updated as it progresses.",
            };
        }

        if ($oldPaymentStatus !== $order->payment_status) {
            $paymentLabel = match ($order->payment_status) {
                'paid' => 'Paid',
                'pending' => 'Pending',
                'refunded' => 'Refunded',
                default => ucfirst((string) $order->payment_status),
            };
            $lines[] = "• Payment: {$paymentLabel}";
        }

        $shouldIncludePaymentHelp = ($order->payment_status !== 'paid')
            && (bool) ($settings?->orders_collect_payment_enabled ?? true)
            && (
                (bool) ($settings?->orders_accept_stripe ?? false)
                || (bool) ($settings?->orders_accept_mpesa ?? false)
                || (bool) ($settings?->orders_accept_paystack ?? false)
                || (is_string($settings?->order_payment_manual_instructions ?? null) && trim((string) $settings?->order_payment_manual_instructions) !== '')
            );

        if ($shouldIncludePaymentHelp) {
            $lines[] = '';
            $lines[] = 'How to pay:';

            // If the order is linked to a chat, customer can reply "1" and the bot will guide payment.
            if (! empty($order->chat_id)) {
                $lines[] = "Reply with:\n1 - Continue and pay\n2 - Cancel";
            }

            if ((bool) ($settings?->orders_accept_stripe ?? false)) {
                $stripe = app(OrderPaymentService::class)->createStripePaymentLinkForOrder($order);
                if (! empty($stripe['success']) && ! empty($stripe['url'])) {
                    $lines[] = "Pay online now: {$stripe['url']}";
                }
            }

            if ((bool) ($settings?->orders_accept_mpesa ?? false)) {
                $lines[] = 'M-Pesa: If you prefer M-Pesa, reply "1" to proceed and we’ll prompt payment on your phone.';
            }

            if ((bool) ($settings?->orders_accept_paystack ?? false)) {
                $paystack = app(OrderPaymentService::class)->createPaystackPaymentLinkForOrder($order);
                if (! empty($paystack['success']) && ! empty($paystack['url'])) {
                    $lines[] = "Pay with Paystack: {$paystack['url']}";
                }
            }

            $manual = $settings?->order_payment_manual_instructions ?? null;
            if (is_string($manual) && trim($manual) !== '') {
                $lines[] = "Manual payment instructions:\n".trim($manual);
            }
        }

        if (! empty($order->delivery_address)) {
            $lines[] = "Delivery address: {$order->delivery_address}";
        }

        $lines[] = "Receipt: {$order->publicReceiptUrl()}";
        $lines[] = "— {$companyName}";

        return implode("\n", $lines);
    }

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Order::with('orderProducts')->where('company_id', $companyId)->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('attributedOnly')) {
            $query->whereNotNull('social_post_id');
        }

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $total = $query->count();
        $orders = $query->skip(($page - 1) * $limit)->take($limit)->get();

        $posts = SocialPost::whereIn('id', $orders->pluck('social_post_id')->filter())
            ->get(['id', 'title', 'platform', 'content'])
            ->keyBy('id');

        $ordersData = $orders->map(function (Order $order) use ($posts) {
            $post = $order->social_post_id ? $posts->get($order->social_post_id) : null;

            return [
                'id' => (string) $order->id,
                'orderNumber' => $order->order_number,
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'chatId' => $order->chat_id ? (string) $order->chat_id : null,
                'products' => $order->orderProducts->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'quantity' => (int) $p->quantity,
                    'price' => (float) $p->price,
                    'taxAmount' => (float) ($p->tax_amount ?? 0),
                    'lineSubtotal' => (float) ($p->line_subtotal ?? ((float) $p->price * (int) $p->quantity)),
                    'taxName' => $p->tax_name,
                    'taxRate' => $p->tax_rate !== null ? (float) $p->tax_rate : null,
                    'taxInclusive' => (bool) ($p->tax_inclusive ?? false),
                ])->values()->all(),
                'subtotal' => (float) ($order->subtotal ?? $order->total),
                'taxTotal' => (float) ($order->tax_total ?? 0),
                'taxBreakdown' => is_array($order->tax_breakdown) ? $order->tax_breakdown : [],
                'total' => (float) $order->total,
                'status' => $order->status,
                'paymentStatus' => $order->payment_status,
                'attribution' => $post ? [
                    'socialPostId' => (string) $post->id,
                    'postTitle' => $post->title ?? Str::limit($post->content, 40),
                    'platform' => $post->platform,
                ] : null,
                'createdAt' => $order->created_at->toIso8601String(),
                'updatedAt' => $order->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'orders' => $ordersData,
            'total' => $total,
            'page' => $page,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId || (int) $order->company_id !== (int) $companyId) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->load('orderProducts');

        return response()->json([
            'order' => [
                'id' => (string) $order->id,
                'orderNumber' => $order->order_number,
                'customerName' => $order->customer_name,
                'customerPhone' => $order->customer_phone,
                'products' => $order->orderProducts->map(fn ($p) => [
                    'id' => (string) $p->id,
                    'name' => $p->name,
                    'quantity' => (int) $p->quantity,
                    'price' => (float) $p->price,
                    'taxAmount' => (float) ($p->tax_amount ?? 0),
                    'lineSubtotal' => (float) ($p->line_subtotal ?? ((float) $p->price * (int) $p->quantity)),
                    'taxName' => $p->tax_name,
                    'taxRate' => $p->tax_rate !== null ? (float) $p->tax_rate : null,
                    'taxInclusive' => (bool) ($p->tax_inclusive ?? false),
                ])->values()->all(),
                'subtotal' => (float) ($order->subtotal ?? $order->total),
                'taxTotal' => (float) ($order->tax_total ?? 0),
                'taxBreakdown' => is_array($order->tax_breakdown) ? $order->tax_breakdown : [],
                'total' => (float) $order->total,
                'status' => $order->status,
                'paymentStatus' => $order->payment_status,
                'createdAt' => $order->created_at->toIso8601String(),
                'updatedAt' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Preview subtotal / tax / total for cart lines without creating an order.
     */
    public function previewTotals(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.productId' => 'nullable|integer|exists:products,id',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.taxRateId' => 'nullable|integer|exists:tax_rates,id',
        ]);

        $company = $request->user()->company;
        $company?->loadMissing('settings');

        $calcItems = [];
        foreach ($validated['items'] as $item) {
            $calcItems[] = [
                'product_id' => $item['productId'] ?? null,
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
                'tax_rate_id' => $item['taxRateId'] ?? null,
            ];
        }

        $calc = app(TaxCalculationService::class)->calculateForCompany($company, $calcItems);

        return response()->json([
            'subtotal' => $calc['subtotal'],
            'taxTotal' => $calc['tax_total'],
            'total' => $calc['total'],
            'taxBreakdown' => $calc['tax_breakdown'],
        ]);
    }

    public function store(
        Request $request,
        WhatsAppMessageSenderService $waSender,
        OrderPaymentService $orderPaymentService
    ): JsonResponse {
        $validated = $request->validate([
            'chatId' => 'required|integer|exists:chats,id',
            'items' => 'required|array|min:1',
            'items.*.productId' => 'nullable|integer|exists:products,id',
            'items.*.productVariantId' => 'nullable|integer|exists:product_variants,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.fulfillmentData' => 'nullable|array',
            'sendWhatsApp' => 'sometimes|boolean',
        ]);

        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $chat = Chat::where('id', $validated['chatId'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        $company = $chat->company;
        $company?->loadMissing('settings');

        $calcItems = [];
        foreach ($validated['items'] as $item) {
            $calcItems[] = [
                'product_id' => $item['productId'] ?? null,
                'name' => $item['name'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ];
        }
        $calc = app(TaxCalculationService::class)->calculateForCompany($company, $calcItems);

        $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-'.strtoupper(Str::random(8));
        }

        $order = Order::create([
            'company_id' => $companyId,
            'chat_id' => $chat->id,
            'order_number' => $orderNumber,
            'customer_name' => $chat->customer_name ?: 'Customer',
            'customer_phone' => $chat->customer_phone ?: '',
            'subtotal' => $calc['subtotal'],
            'tax_total' => $calc['tax_total'],
            'tax_breakdown' => $calc['tax_breakdown'] !== [] ? $calc['tax_breakdown'] : null,
            'total' => $calc['total'],
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $digitalAccess = app(\App\Services\DigitalAccessService::class);

        try {
            foreach ($validated['items'] as $index => $item) {
                $product = null;
                $productId = $item['productId'] ?? null;
                if ($productId) {
                    $product = Product::query()
                        ->where('company_id', $companyId)
                        ->where('id', $productId)
                        ->first();
                    if (! $product) {
                        throw new \RuntimeException('Invalid product for this company.');
                    }

                    $poolError = $digitalAccess->assertPoolCapacity($product, (int) $item['quantity']);
                    if ($poolError) {
                        throw new \RuntimeException($poolError);
                    }
                }

                $fulfillment = is_array($item['fulfillmentData'] ?? null) ? $item['fulfillmentData'] : [];
                // Strip any client-supplied file paths to prevent path injection.
                unset($fulfillment['digitalFilePath'], $fulfillment['digitalFileAbsolutePath'], $fulfillment['digitalFileUrl']);
                $fulfillment = $digitalAccess->hydrateFulfillmentData($fulfillment, $product);
                if ($product && empty($fulfillment)) {
                    $fulfillment = $product->fulfillmentSnapshot();
                }

                $lineTax = $calc['lines'][$index] ?? [
                    'tax_rate_id' => null,
                    'tax_name' => null,
                    'tax_code' => null,
                    'tax_rate' => null,
                    'tax_inclusive' => false,
                    'tax_amount' => 0.0,
                    'line_subtotal' => round(((float) $item['price']) * ((int) $item['quantity']), 2),
                ];

                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $item['productVariantId'] ?? null,
                    'name' => $item['name'],
                    'quantity' => (int) $item['quantity'],
                    'price' => (float) $item['price'],
                    'tax_rate_id' => $lineTax['tax_rate_id'],
                    'tax_name' => $lineTax['tax_name'],
                    'tax_code' => $lineTax['tax_code'],
                    'tax_rate' => $lineTax['tax_rate'],
                    'tax_inclusive' => $lineTax['tax_inclusive'],
                    'tax_amount' => $lineTax['tax_amount'],
                    'line_subtotal' => $lineTax['line_subtotal'],
                    'fulfillment_data' => $fulfillment !== [] ? $fulfillment : null,
                ]);
            }
        } catch (\RuntimeException $e) {
            $order->delete();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $whatsappSent = false;
        $whatsappError = null;
        $invoiceMessage = null;

        if (($validated['sendWhatsApp'] ?? true) === true) {
            $company = $chat->company;
            $account = $company?->whatsappAccount;
            $to = $chat->customer_phone;
            if (! $account || ! $account->isActive()) {
                $whatsappError = 'No active WhatsApp connection';
            } elseif (! $to) {
                $whatsappError = 'No customer phone number';
            } else {
                $paymentLine = "Reply with:\n1 - Continue and pay\n2 - Cancel\n\n";
                $stripe = $orderPaymentService->createStripePaymentLinkForOrder($order);
                if (! empty($stripe['success']) && ! empty($stripe['url'])) {
                    $paymentLine .= "Pay online now: {$stripe['url']}\n";
                }
                $moneyLines = app(TaxCalculationService::class)->formatSummaryLines([
                    'subtotal' => (float) ($order->subtotal ?? $order->total),
                    'tax_total' => (float) ($order->tax_total ?? 0),
                    'total' => (float) $order->total,
                    'tax_breakdown' => is_array($order->tax_breakdown) ? $order->tax_breakdown : [],
                ], fn (float $amount) => MoneyFormatter::formatFromSettings(
                    $amount,
                    $company?->settings
                ));
                $invoiceMessage = "Order #{$order->order_number} created for {$order->customer_name}.\n"
                    .implode("\n", $moneyLines)."\n\n"
                    ."View invoice / receipt:\n{$order->publicReceiptUrl()}\n\n"
                    .$paymentLine
                    ."Thank you!";

                $result = $waSender->sendText($account, $to, $invoiceMessage);
                $whatsappSent = (bool) ($result['success'] ?? false);
                $whatsappError = $result['error'] ?? null;

                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $invoiceMessage,
                    'sender' => 'agent',
                    'status' => $whatsappSent ? 'sent' : 'failed',
                    'whatsapp_message_id' => $result['message_id'] ?? null,
                ]);

                $chat->update([
                    'last_message' => $invoiceMessage,
                    'last_message_at' => now(),
                    // Don't lock the chat to agent handling; customer reply should be handled by the bot checkout flow.
                    'agent_handling_at' => null,
                    // Put chat into an explicit "existing order" flow so replies like "1" are not treated as "Prices".
                    'conversation_step' => \App\Services\OrderFlowService::STEP_EXISTING_ORDER_PROMPT,
                    'order_draft' => ['order_id' => $order->id],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => $whatsappSent ? 'Order created and invoice sent via WhatsApp.' : 'Order created.',
            'order' => [
                'id' => (string) $order->id,
                'orderNumber' => $order->order_number,
            ],
            'whatsappSent' => $whatsappSent,
            'whatsappError' => $whatsappError,
        ], 201);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:pending,confirmed,shipped,delivered,cancelled',
            'paymentStatus' => 'sometimes|in:pending,paid,refunded',
        ]);

        if ($order->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $oldStatus = $order->status;
        $oldPaymentStatus = $order->payment_status;

        $updates = [];
        if ($request->has('status')) {
            $updates['status'] = $request->status;
        }

        $markedPaidViaService = false;
        if ($request->has('paymentStatus')) {
            if ($request->paymentStatus === 'paid' && $oldPaymentStatus !== 'paid') {
                // Manual / till / offline payments: company staff confirm receipt.
                // Gateways also call OrderPaymentService::markOrderPaid from webhooks.
                app(OrderPaymentService::class)->markOrderPaid($order);
                $order->refresh();
                $markedPaidViaService = true;
                // markOrderPaid sets status=confirmed; don't regress it with a stale pending/confirmed patch.
                if (isset($updates['status']) && in_array($updates['status'], ['pending', 'confirmed'], true)) {
                    unset($updates['status']);
                }
            } else {
                $updates['payment_status'] = $request->paymentStatus;
            }
        }
        if ($updates !== []) {
            $order->update($updates);
            $order->refresh();
        }

        // markOrderPaid already sends its own payment confirmation + fulfillment WhatsApp.
        $whatsappSent = false;
        $whatsappError = null;

        $shouldNotify = ! $markedPaidViaService
            && (($oldStatus !== $order->status) || ($oldPaymentStatus !== $order->payment_status));
        if ($shouldNotify) {
            $company = $order->company;
            $account = $company?->whatsappAccount;
            $to = $order->customer_phone;

            if (! $account || ! $account->isActive()) {
                $whatsappError = 'No active WhatsApp connection';
            } elseif (empty($to)) {
                $whatsappError = 'No customer phone number';
            } else {
                $content = $this->buildOrderUpdateWhatsAppMessage($order, $oldStatus, $oldPaymentStatus);
                $result = app(WhatsAppMessageSenderService::class)->sendText($account, $to, $content);
                $whatsappSent = (bool) ($result['success'] ?? false);
                $whatsappError = $result['error'] ?? null;

                // If this order is linked to a chat, persist the outbound message in chat history.
                if (! empty($order->chat_id)) {
                    Message::create([
                        'chat_id' => $order->chat_id,
                        'content' => $content,
                        'sender' => 'agent',
                        'status' => $whatsappSent ? 'sent' : 'failed',
                        'whatsapp_message_id' => $result['message_id'] ?? null,
                    ]);

                    $chat = Chat::find($order->chat_id);
                    if ($chat) {
                        // Ensure customer replies like "1" are interpreted as a payment choice for this order.
                        if (($order->payment_status ?? null) !== 'paid') {
                            $chat->update([
                                'conversation_step' => \App\Services\OrderFlowService::STEP_EXISTING_ORDER_PROMPT,
                                'order_draft' => ['order_id' => $order->id],
                            ]);
                        }
                        $chat->update([
                            'last_message' => $content,
                            'last_message_at' => now(),
                            'agent_handling_at' => null,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $markedPaidViaService
                ? 'Order marked as paid. Customer notified if WhatsApp is connected.'
                : ($whatsappSent ? 'Order updated and customer notified via WhatsApp.' : 'Order updated successfully'),
            'whatsappSent' => $whatsappSent || $markedPaidViaService,
            'whatsappError' => $whatsappError,
        ]);
    }
}
