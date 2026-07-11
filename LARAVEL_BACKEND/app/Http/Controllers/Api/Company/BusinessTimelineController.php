<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Timeline\BusinessTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessTimelineController extends Controller
{
    public function index(Request $request, BusinessTimelineService $timeline): JsonResponse
