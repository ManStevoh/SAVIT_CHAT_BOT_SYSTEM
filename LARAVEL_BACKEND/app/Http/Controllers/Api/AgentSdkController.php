<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agent\Platform\MarketplaceModuleService;
use Illuminate\Http\JsonResponse;

class AgentSdkController extends Controller
{
    public function manifest(MarketplaceModuleService $marketplace): JsonResponse
    {
