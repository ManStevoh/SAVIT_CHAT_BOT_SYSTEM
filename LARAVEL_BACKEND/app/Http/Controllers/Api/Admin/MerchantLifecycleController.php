<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantLifecycleSend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantLifecycleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sends = MerchantLifecycleSend::query()
            ->with(['user:id,name,email,phone', 'company:id,name'])
            ->orderByDesc('id')
            ->limit(min(200, max(1, (int) $request->integer('limit', 50))))
            ->get()
            ->map(fn (MerchantLifecycleSend $row) => [
                'id' => $row->id,
                'userId' => $row->user_id,
                'companyId' => $row->company_id,
                'userEmail' => $row->user?->email,
                'userName' => $row->user?->name,
                'companyName' => $row->company?->name,
                'step' => $row->step,
                'channel' => $row->channel,
                'status' => $row->status,
                'error' => $row->error,
                'sentAt' => $row->sent_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'sends' => $sends]);
    }
}
