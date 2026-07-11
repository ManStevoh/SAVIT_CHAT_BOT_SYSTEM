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
     */
    public function instagramDm(
        Request $request,
        ChannelIngestAuthService $auth,
        ChannelReplyDispatcher $dispatcher,
        int $companyId,
    ): JsonResponse|Response
    {
        if ($request->isMethod('GET')) {
            return $this->verifyMetaWebhook($request);
        }

        $company = $auth->companyFromRequest($request, $companyId);
        if (! $company) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $body = $request->all();
        if (isset($body['entry'])) {
            return $this->processMetaInstagramPayload($body, $company, $dispatcher);
        }

        $validated = $request->validate([
            'senderId' => 'required|string|max:120',
            'senderUsername' => 'nullable|string|max:120',
            'text' => 'required|string|max:5000',
        ]);

        $result = $dispatcher->ingestAndReply(
            $company,
            ChatChannel::INSTAGRAM_DM,
            $validated['senderId'],
            $validated['text'],
            $validated['senderUsername'] ? '@'.$validated['senderUsername'] : null,
            syncReply: false,
        );

        return response()->json([
            'accepted' => true,
            'chatId' => $result['chatId'],
            'queued' => $result['queued'],
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function processMetaInstagramPayload(array $body, \App\Models\Company $company, ChannelReplyDispatcher $dispatcher): JsonResponse
    {
        $processed = 0;
        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                $senderId = (string) ($event['sender']['id'] ?? '');
                $text = (string) ($event['message']['text'] ?? '');
                if ($senderId === '' || $text === '') {
                    continue;
                }
                $dispatcher->ingestAndReply(
                    $company,
                    ChatChannel::INSTAGRAM_DM,
                    $senderId,
                    $text,
                    null,
                    syncReply: false,
                );
                $processed++;
            }
        }

        return response()->json(['accepted' => true, 'processed' => $processed], 200);
    }

