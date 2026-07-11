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

            'scenario_inputs' => 'nullable|array',

            'include_plan' => 'nullable|boolean',

            'persist_plan' => 'nullable|boolean',

            'open_case' => 'nullable|boolean',

        ]);



        $result = $intelligence->reason($company, $validated);



        return response()->json(['reasoning' => $result], 201);

    }



    public function cases(Request $request): JsonResponse

    {

        $company = $request->user()->company;

        if (! $company) {

            return response()->json(['message' => 'No company.'], 403);

        }



        $cases = InvestigationCase::where('company_id', $company->id)

            ->orderByDesc('created_at')

            ->limit(30)

            ->get();



        return response()->json(['cases' => $cases]);

    }



    public function showCase(Request $request, int $id, InvestigationCaseService $cases): JsonResponse

    {

        $company = $request->user()->company;

        if (! $company) {

            return response()->json(['message' => 'No company.'], 403);

        }



        $detail = $cases->showForCompany($company, $id);

        if (! $detail) {

            return response()->json(['message' => 'Not found.'], 404);

        }



        return response()->json([

            'case' => $detail['case'],

            'investigation' => $detail['investigation'],

        ]);

    }



    public function recordOutcome(Request $request, IntelligenceOutcomeService $outcomes): JsonResponse

    {

        $company = $request->user()->company;

        if (! $company) {

            return response()->json(['message' => 'No company.'], 403);

        }



        $validated = $request->validate([

            'source_type' => 'required|string|in:investigation,reasoning,approval,brief,opportunity',

            'source_id' => 'required|integer|min:1',

            'recommended_action' => 'required|string|max:2000',

            'outcome' => 'required|string|in:positive,neutral,negative,pending',

            'notes' => 'nullable|string|max:2000',

            'metrics' => 'nullable|array',

        ]);



        $record = $outcomes->record(

            $company,

            $request->user(),

            $validated['source_type'],

            (int) $validated['source_id'],

            $validated['recommended_action'],

            $validated['outcome'],

            $validated['notes'] ?? null,

            $validated['metrics'] ?? [],

        );



        return response()->json(['outcome' => $record], 201);

    }

    public function outcomes(Request $request): JsonResponse
    {
        $company = $request->user()->company;
