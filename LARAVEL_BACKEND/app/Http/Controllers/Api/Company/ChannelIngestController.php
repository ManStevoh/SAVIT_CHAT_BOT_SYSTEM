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

        $validated = $request->validate([
            'channel' => 'required|string|in:'.implode(',', ChatChannel::all()),
            'channelUserId' => 'required|string|max:120',
            'message' => 'required|string|max:5000',
            'customerName' => 'nullable|string|max:120',
            'customerEmail' => 'nullable|string|max:200',
        ]);

        $result = $ingest->ingest(
            $company,
            $validated['channel'],
            $validated['channelUserId'],
            $validated['message'],
            $validated['customerName'] ?? null,
            $validated['customerEmail'] ?? null,
        );

        return response()->json([
            'chatId' => $result['chat']->id,
            'messageId' => $result['message']->id,
            'queued' => $result['queued'],
        ], 201);
    }

    public function channels(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $company->loadMissing('settings');
        $settings = $company->settings;
        $baseUrl = rtrim(config('app.url'), '/');

