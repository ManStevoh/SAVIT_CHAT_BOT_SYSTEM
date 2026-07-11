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
            return response()->json(['message' => 'No company.'], 403);
        }

        if ($request->user()->role !== 'company_owner') {
            return response()->json(['message' => 'Only company owner can manage policies.'], 403);
        }

        $validated = $request->validate([
            'action_type' => 'required|string|max:80',
            'subject_role' => 'nullable|string|max:40',
            'max_amount' => 'nullable|numeric|min:0',
            'requires_role' => 'nullable|string|max:40',
            'is_active' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ]);

        $rule = CompanyPolicyRule::create(array_merge($validated, [
            'company_id' => $company->id,
            'is_active' => $validated['is_active'] ?? true,
        ]));

        $audit->log('policy_rule.created', CompanyPolicyRule::class, $rule->id, null, $rule->toArray(), $company->id, $request->user());

        return response()->json(['rule' => $rule], 201);
    }

    public function update(Request $request, int $id, AuditService $audit): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company || $request->user()->role !== 'company_owner') {
