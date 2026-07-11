<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CommerceBrief;
use App\Services\Agent\Company\CommerceMorningBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceBriefController extends Controller
{
    public function today(Request $request, CommerceMorningBriefService $briefs): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $brief = CommerceBrief::where('company_id', $company->id)
            ->whereDate('brief_date', now()->toDateString())
            ->first();

        if (! $brief) {
            $brief = $briefs->generateForCompany($company);
        }

        if (! $brief) {
            return response()->json(['brief' => null]);
        }

        return response()->json([
            'brief' => [
                'date' => $brief->brief_date->toDateString(),
                'summary' => $brief->summary,
                'metrics' => $brief->metrics,
                'recommendations' => $brief->recommendations,
                'executiveDecisions' => $brief->executive_decisions,
            ],
        ]);
    }
}
