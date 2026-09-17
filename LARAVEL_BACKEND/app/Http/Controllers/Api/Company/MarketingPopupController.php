<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\PlatformMarketingMessage;
use App\Services\PlatformMarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingPopupController extends Controller
{
    public function index(Request $request, PlatformMarketingService $marketing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'popups' => $marketing->pendingPopups($request->user()),
        ]);
    }

    public function dismiss(Request $request, PlatformMarketingMessage $marketingMessage, PlatformMarketingService $marketing): JsonResponse
    {
        $marketing->dismissPopup($request->user(), $marketingMessage);

        return response()->json(['success' => true]);
    }
}
