<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\MissionControl\MissionControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionControlController extends Controller
{
    public function index(Request $request, MissionControlService $missionControl): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json($missionControl->build($company));
    }

    public function explainability(Request $request, MissionControlService $missionControl, int $id): JsonResponse
    {
        $company = $request->user()->company;
