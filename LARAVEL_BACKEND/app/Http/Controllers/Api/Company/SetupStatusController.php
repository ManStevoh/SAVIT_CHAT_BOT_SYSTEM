<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\CompanySetupStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupStatusController extends Controller
{
    public function show(Request $request, CompanySetupStatusService $setup): JsonResponse
    {
        $company = $request->user()?->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($setup->status($company));
    }

    public function dismiss(Request $request): JsonResponse
    {
        $company = $request->user()?->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        if ($company->setup_checklist_dismissed_at === null) {
            $company->forceFill([
                'setup_checklist_dismissed_at' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'dismissed' => true,
            'setupChecklistDismissedAt' => $company->fresh()?->setup_checklist_dismissed_at?->toIso8601String(),
        ]);
    }
}
