<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CommerceAgentEvent;
use App\Services\Agent\Events\CommerceEventDetector;
use App\Services\Agent\Events\CommerceEventHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceAgentEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:open,alerted,handled,skipped,no_chat',
            'type' => 'nullable|string|max:40',
        ]);
