<?php

namespace App\Services\Conversation;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for reply routing (keyword / FAQ / OpenAI / fallback).
 */
class ConversationRoutingLogger
{
    public function log(int $companyId, ?int $chatId, string $route, array $meta = []): void
    {
        if (! config('conversation.log_routing', true)) {
            return;
        }

        Log::info('conversation_routing', array_merge([
            'company_id' => $companyId,
            'chat_id' => $chatId,
            'route' => $route,
        ], $meta));
    }
}
