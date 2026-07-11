<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Agent\Channels\ChannelIngestAuthService;
use App\Services\Agent\Channels\ChannelReplyDispatcher;
use App\Services\Agent\Channels\ChatChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChannelWebhookController extends Controller
{
    /**
     * Generic email ingest webhook (SendGrid/Mailgun-style JSON body).
     */
    public function email(
        Request $request,
        ChannelIngestAuthService $auth,
        ChannelReplyDispatcher $dispatcher,
        int $companyId,
    ): JsonResponse {
        $company = $auth->companyFromRequest($request, $companyId);
        if (! $company) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $validated = $request->validate([
            'from' => 'required|string|max:200',
            'fromName' => 'nullable|string|max:120',
            'subject' => 'nullable|string|max:300',
            'body' => 'required|string|max:10000',
            'messageId' => 'nullable|string|max:200',
        ]);

        $text = trim($validated['body']);
        if (! empty($validated['subject'])) {
            $text = 'Subject: '.$validated['subject']."\n\n".$text;
        }

        $result = $dispatcher->ingestAndReply(
            $company,
            ChatChannel::EMAIL,
            mb_strtolower(trim($validated['from'])),
            mb_substr($text, 0, 5000),
            $validated['fromName'] ?? null,
            $validated['from'],
            syncReply: false,
        );

        return response()->json([
            'accepted' => true,
            'chatId' => $result['chatId'],
            'queued' => $result['queued'],
        ], 202);
    }

    /**
     * Instagram DM webhook (Meta messaging_events subset or generic JSON).
