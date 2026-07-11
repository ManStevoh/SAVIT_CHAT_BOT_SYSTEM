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

        $query = CommerceAgentEvent::where('company_id', $company->id)
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['type'])) {
            $query->where('event_type', $validated['type']);
        }

        $events = $query->limit(50)->get()->map(fn ($e) => $this->formatEvent($e));

        return response()->json(['events' => $events]);
    }

    public function ownerAlerts(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $types = config('agent.events.owner_alert_types', ['low_stock', 'sales_drop']);
        $events = CommerceAgentEvent::where('company_id', $company->id)
            ->whereIn('event_type', $types)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($e) => $this->formatEvent($e));

        return response()->json(['alerts' => $events]);
    }

    public function detect(Request $request, CommerceEventDetector $detector): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $created = $detector->detectForCompany($company);

        return response()->json([
            'detected' => count($created),
            'events' => collect($created)->map(fn ($e) => $this->formatEvent($e)),
        ], 201);
    }
