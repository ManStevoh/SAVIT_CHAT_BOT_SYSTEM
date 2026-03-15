<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (!$companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $query = Chat::where('company_id', $companyId)->orderByDesc('last_message_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $limit = $request->filled('limit') ? max(1, min(100, (int) $request->limit)) : null;
        $chats = $limit ? $query->take($limit)->get() : $query->get();

        $data = $chats->map(fn (Chat $chat) => [
            'id' => (string) $chat->id,
            'customerName' => $chat->customer_name,
            'customerPhone' => $chat->customer_phone,
            'customerAvatar' => $chat->customer_avatar,
            'lastMessage' => $chat->last_message ?? '',
            'lastMessageTime' => $chat->last_message_at ? Carbon::parse($chat->last_message_at)->diffForHumans() : '',
            'unreadCount' => (int) $chat->unread_count,
            'status' => $chat->status,
            'aiHandled' => (bool) $chat->ai_handled,
            'agentHandlingAt' => $chat->agent_handling_at?->toIso8601String(),
        ]);

        return response()->json($data);
    }

    /**
     * Clear agent_handling_at for this chat so the bot can auto-reply again (hand back to bot).
     * POST /api/company/chats/{chatId}/hand-back
     */
    public function handBack(Request $request, string $chatId): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $chat = Chat::where('id', $chatId)->where('company_id', $companyId)->first();
        if (! $chat) {
            return response()->json(['message' => 'Chat not found.'], 404);
        }

        $chat->update(['agent_handling_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Chat handed back to bot. Auto-reply will resume for new messages.',
        ]);
    }
}
