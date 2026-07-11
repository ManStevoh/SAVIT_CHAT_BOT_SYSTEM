<?php

namespace App\Services\Agent\Specialists;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\Specialists\Contracts\CommerceSpecialist;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

final class SupportSpecialistService implements CommerceSpecialist
{
    public function __construct(protected AiGateway $aiGateway) {}

    public function type(): string
    {
        return 'support';
    }

    public function consultForTurn(Company $company, Chat $chat, string $incomingMessage, array $perception): array
    {
        $rule = $this->rulePerspective($perception);
        if (! config('agent.specialists.use_llm', true)) {
            return ['perspective' => $rule, 'confidence' => 0.75, 'source' => 'rules'];
        }

        $llm = $this->llmPerspective($company, $chat, $incomingMessage, $perception, $rule);

        return $llm ?? ['perspective' => $rule, 'confidence' => 0.68, 'source' => 'rules_fallback'];
    }

    public function analyzeBackground(Company $company, array $input = []): array
    {
        $frustrated = Chat::query()
            ->where('company_id', $company->id)
            ->where('detected_sentiment', 'frustrated')
            ->where('last_message_at', '>=', now()->subDays(7))
            ->count();

        $unresolved = Message::query()
            ->whereHas('chat', fn ($q) => $q
                ->where('company_id', $company->id)
                ->whereNull('agent_handling_at')
                ->where('last_message_at', '>=', now()->subDays(3)))
            ->where('sender', 'customer')
            ->distinct('chat_id')
            ->count('chat_id');

        return [
            'frustrated_chats_7d' => $frustrated,
            'active_customer_threads_3d' => $unresolved,
            'recommendation' => $frustrated > 0
                ? 'Review frustrated threads; prioritize empathy and fast resolution.'
                : 'Support load normal — maintain response clarity.',
        ];
    }

    /**
     * @param  array<string, mixed>  $perception
     */
    private function rulePerspective(array $perception): string
    {
        $emotion = $perception['emotion'] ?? 'neutral';
        $topic = $perception['topic'] ?? 'general';

        if ($emotion === 'disappointed' || $topic === 'wrong product') {
            return 'Support: Acknowledge issue, verify order, offer replacement or clear resolution path.';
        }
        if ($topic === 'delivery') {
            return 'Support: Use check_delivery_status tool; set realistic expectations.';
        }
        if ($topic === 'refund') {
            return 'Support: Explain policy, gather facts, escalate if refund authority needed.';
        }

        return 'Support: Resolve clearly; use transfer_to_human if policy unclear.';
    }

    /**
     * @param  array<string, mixed>  $perception
     * @return array{perspective: string, confidence: float, source: string}|null
     */
    private function llmPerspective(
        Company $company,
        Chat $chat,
        string $message,
        array $perception,
        string $ruleHint,
    ): ?array {
        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => 'You are the Support Director AI. One sentence internal advice only.'],
                    ['role' => 'user', 'content' => "Message: {$message}\nPerception: ".json_encode($perception)."\nHint: {$ruleHint}"],
                ],
                useCase: 'agent_specialist_support',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 120,
                temperature: 0.3,
                timeoutSeconds: 15,
            );
            if ($result->success && $result->content) {
                return ['perspective' => 'Support: '.trim($result->content), 'confidence' => 0.84, 'source' => 'llm'];
            }
        } catch (\Throwable $e) {
            Log::debug('Support specialist LLM skipped', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
