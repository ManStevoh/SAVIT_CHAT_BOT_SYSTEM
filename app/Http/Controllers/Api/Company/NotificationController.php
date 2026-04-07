<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const LIMIT = 50;

    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;

        $query = CompanyNotification::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(self::LIMIT);

        $items = $query->get();
        $unreadCount = CompanyNotification::where('company_id', $companyId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $items->map(fn (CompanyNotification $n) => [
                'id' => (string) $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'read' => $n->read_at !== null,
                'createdAt' => $n->created_at->toIso8601String(),
                'orderId' => $n->order_id !== null ? (string) $n->order_id : null,
                'chatId' => $n->chat_id !== null ? (string) $n->chat_id : null,
            ]),
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $row = CompanyNotification::query()
            ->where('company_id', $companyId)
            ->where('id', (int) $notification)
            ->firstOrFail();

        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        CompanyNotification::query()
            ->where('company_id', $companyId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
