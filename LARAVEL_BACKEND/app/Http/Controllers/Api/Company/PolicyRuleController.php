<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyPolicyRule;
use App\Services\Platform\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $rules = CompanyPolicyRule::where('company_id', $company->id)
            ->orderBy('action_type')
            ->get();

        return response()->json(['rules' => $rules]);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
