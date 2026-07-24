<?php

namespace App\Services\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

/**
 * Post-conversation LLM extraction of persistent customer facts.
 */
final class CustomerMemoryExtractionService
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected CustomerMemoryService $customerMemory,
    ) {}

    public function extractFromChat(Company $company, Chat $chat, string $customerPhone): int
    {
        $company->loadMissing('settings');

        if (! CommerceAgentReplyService::isEnabledForCompany($company)) {
            return 0;
        }

        if (! ($company->settings?->learn_from_conversations ?? false)) {
            return 0;
        }

        $messages = Message::query()
            ->where('chat_id', $chat->id)
            ->orderBy('id')
            ->limit(40)
            ->get(['sender', 'content']);

        if ($messages->count() < 2) {
            return 0;
        }

        $transcript = $messages->map(fn (Message $m) => strtoupper($m->sender).': '.trim((string) $m->content))->implode("\n");

        $system = <<<'TEXT'
Extract durable customer facts from this WhatsApp conversation. Return JSON:
{"memories":[{"key":"snake_case_key","value":"short fact","category":"preference|location|behavior|need"}]}
Only include facts explicitly stated or strongly implied. Max 5 items. Empty array if none.
TEXT;

        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => mb_substr($transcript, 0, 6000)],
                ],
                useCase: 'agent_memory_extraction',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 400,
                temperature: 0.2,
                jsonMode: true,
                timeoutSeconds: 25,
            );

            if (! $result->success || ! $result->content) {
                return 0;
            }

            $parsed = json_decode($result->content, true);
            if (! is_array($parsed) || ! isset($parsed['memories']) || ! is_array($parsed['memories'])) {
                return 0;
            }

            $stored = 0;
            foreach ($parsed['memories'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $key = trim((string) ($item['key'] ?? ''));
                $value = trim((string) ($item['value'] ?? ''));
                if ($key === '' || $value === '') {
                    continue;
                }
                $this->customerMemory->upsert(
                    (int) $company->id,
                    $customerPhone,
                    $key,
                    $value,
                    mb_substr((string) ($item['category'] ?? 'preference'), 0, 40),
                    'extraction',
                    0.75,
                );
                $stored++;
            }

            return $stored;
        } catch (\Throwable $e) {
            Log::warning('Customer memory extraction failed', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
