<?php

namespace App\Services\Conversation;

use App\Models\Company;

/**
 * Resolves company reply routing mode (AI-first vs balanced legacy).
 */
final class AiReplyModeResolver
{
    public function isAiFirst(Company $company): bool
    {
        $mode = $company->settings?->ai_reply_mode
            ?? config('conversation.default_reply_mode', 'ai_first');

        return $mode === 'ai_first';
    }

    public function shouldSkipScriptedOpening(Company $company, string $message, ConversationGreetingService $greetings): bool
    {
        if (! $this->isAiFirst($company)) {
            return false;
        }

        return ! $greetings->isPureGreeting($message);
    }
}
