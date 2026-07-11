<?php



namespace App\Http\Controllers\Api\Company;



use App\Http\Controllers\Controller;

use App\Models\InvestigationCase;
use App\Models\IntelligenceOutcome;

use App\Services\Agent\Intelligence\IntelligenceOutcomeService;

use App\Services\Agent\Intelligence\IntelligenceReasoningService;

use App\Services\Agent\Intelligence\InvestigationCaseService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



/**

 * ABI Level 19 — Decision Intelligence API.

 */

class IntelligenceController extends Controller

{

    public function reason(Request $request, IntelligenceReasoningService $intelligence): JsonResponse

    {

        $company = $request->user()->company;

        if (! $company) {

            return response()->json(['message' => 'No company.'], 403);

        }



        $validated = $request->validate([

            'goal' => 'required|string|max:1000',

            'period' => 'nullable|string|in:7d,30d,90d',

            'time_horizon' => 'nullable|string|max:40',

            'constraints' => 'nullable|array',

            'constraints.*' => 'string|max:300',

            'context' => 'nullable|array',

            'simulate' => 'nullable|boolean',

            'scenario_type' => 'nullable|string|max:80',
