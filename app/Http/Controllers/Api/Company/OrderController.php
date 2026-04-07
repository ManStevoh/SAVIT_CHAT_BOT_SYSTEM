<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\OrderPaymentService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
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

        $page = max(1, (int) $request->input('page', 1));
        $limit = max(1, min(100, (int) $request->input('limit', 10)));
        $total = $query->count();
        $orders = $query->skip(($page - 1) * $limit)->take($limit)->get();

        $ordersData = $orders->map(fn (Order $order) => [
            'id' => (string) $order->id,
            'orderNumber' => $order->order_number,
            'customerName' => $order->customer_name,
            'customerPhone' => $order->customer_phone,
            'products' => $order->orderProducts->map(fn ($p) => [
                'id' => (string) $p->id,
                'name' => $p->name,
                'quantity' => (int) $p->quantity,
                'price' => (float) $p->price,
            ])->values()->all(),
            'total' => (float) $order->total,
            'status' => $order->status,
            'paymentStatus' => $order->payment_status,
            'createdAt' => $order->created_at->toIso8601String(),
            'updatedAt' => $order->updated_at->toIso8601String(),
        ]);

        return response()->json([
            'orders' => $ordersData,
            'total' => $total,
            'page' => $page,
            'totalPages' => (int) ceil($total / $limit),
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
            'items.*.name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1|max:9999',
            'items.*.price' => 'required|numeric|min:0',
            'sendWhatsApp' => 'sometimes|boolean',
        ]);

        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $chat = Chat::where('id', $validated['chatId'])
            ->where('company_id', $companyId)
            ->firstOrFail();

        $total = 0.0;
        foreach ($validated['items'] as $item) {
            $total += ((float) $item['price']) * ((int) $item['quantity']);
        }

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
            'total' => round($total, 2),
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        foreach ($validated['items'] as $item) {
            OrderProduct::create([
                'order_id' => $order->id,
                'name' => $item['name'],
                'quantity' => (int) $item['quantity'],
                'price' => (float) $item['price'],
            ]);
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
                $invoiceMessage = "Order #{$order->order_number} created for {$order->customer_name}.\n"
                    ."Total: {$order->total}.\n\n"
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

        $updates = [];
        if ($request->has('status')) {
            $updates['status'] = $request->status;
        }
        if ($request->has('paymentStatus')) {
            $updates['payment_status'] = $request->paymentStatus;
        }
        if ($updates !== []) {
            $order->update($updates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
        ]);
    }
}
