<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SystemLog::query();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->filled('source') && $request->source !== 'all') {
            $query->where('source', 'like', '%' . $request->source . '%');
        }

        $logs = $query->orderByDesc('created_at')->limit(500)->get();
        $data = $logs->map(fn (SystemLog $log) => [
            'id' => (string) $log->id,
            'type' => $log->type,
            'message' => $log->message,
            'source' => $log->source,
            'details' => $log->details,
            'timestamp' => $log->created_at->toIso8601String(),
        ]);

        return response()->json($data->values()->all());
    }
}
