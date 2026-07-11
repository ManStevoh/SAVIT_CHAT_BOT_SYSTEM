<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\Agent\Channels\ChatChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChannelIngestController extends Controller
{
    public function ingest(Request $request, \App\Services\Agent\Channels\MultiChannelIngestService $ingest): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
