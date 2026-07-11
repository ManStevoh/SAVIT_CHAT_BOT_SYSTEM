<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Onboarding\OnboardingInterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingInterviewController extends Controller
{
    public function start(Request $request, OnboardingInterviewService $interview): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($interview->start($company), 201);
    }

    public function respond(Request $request, OnboardingInterviewService $interview): JsonResponse
    {
        $company = $request->user()->company;
