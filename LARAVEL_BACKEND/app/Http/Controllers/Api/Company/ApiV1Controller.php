<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only API v1 surface authenticated via company API keys.
 */
class ApiV1Controller extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        $companyId = (int) $request->attributes->get('api_key_company_id');
        if (! $companyId) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $orders = Order::where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->limit(50)
