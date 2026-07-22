<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\SocialPost;
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

        if ($request->boolean('attributedOnly')) {
            $query->where(function ($q) {
                $q->whereNotNull('social_post_id')->orWhereNotNull('attribution_link_id');
            });
        }

        if ($request->filled('socialPostId')) {
            $query->where('social_post_id', (int) $request->socialPostId);
        }

        $limit = $request->filled('limit') ? max(1, min(100, (int) $request->limit)) : null;
        $chats = $limit ? $query->take($limit)->get() : $query->get();

        $postLabels = SocialPost::whereIn('id', $chats->pluck('social_post_id')->filter())
            ->get(['id', 'title', 'platform', 'content'])
            ->keyBy('id');

        $data = $chats->map(function (Chat $chat) use ($postLabels) {
            $post = $chat->social_post_id ? $postLabels->get($chat->social_post_id) : null;

            return [
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
                'isAttributed' => (bool) ($chat->social_post_id || $chat->attribution_link_id),
                'attribution' => $post ? [
                    'socialPostId' => (string) $post->id,
                    'postTitle' => $post->title ?? \Illuminate\Support\Str::limit($post->content, 40),
                    'platform' => $post->platform,
                ] : ($chat->attribution_link_id ? ['socialPostId' => null, 'postTitle' => 'Tracking link', 'platform' => null] : null),
            ];
        });

        return response()->json($data);
    }

    /**
     * Find or create a chat by customer phone (mobile "Add contact" / start conversation).
     * POST /api/company/chats/start
     */
    public function start(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
        ]);

        $phone = preg_replace('/\D+/', '', $validated['phone']) ?? '';
        if ($phone === '') {
            return response()->json([
                'message' => 'A valid phone number is required.',
                'errors' => ['phone' => ['A valid phone number is required.']],
            ], 422);
        }

        $name = trim((string) ($validated['name'] ?? ''));
        if ($name === '') {
            $name = 'Customer';
        }

        $chat = Chat::firstOrCreate(
            [
                'company_id' => $companyId,
                'customer_phone' => $phone,
            ],
            [
                'customer_name' => $name,
                'customer_avatar' => null,
                'last_message' => null,
                'last_message_at' => now(),
                'unread_count' => 0,
                'status' => 'active',
                'ai_handled' => false,
                'agent_handling_at' => now(),
            ]
        );

        $created = $chat->wasRecentlyCreated;
        if (! $created && $name !== 'Customer') {
            $chat->update([
                'customer_name' => $name,
                'agent_handling_at' => now(),
            ]);
        } elseif (! $created) {
            $chat->update(['agent_handling_at' => now()]);
        }

        $chat->refresh();

        return response()->json([
            'success' => true,
            'created' => $created,
            'chat' => [
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
            ],
        ], $created ? 201 : 200);
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
