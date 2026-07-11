<?php

namespace App\Services\Agent\Company;

use App\Models\AgentReflection;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\AgentMemoryService;
use App\Services\Agent\CommerceAgentReplyService;
use App\Services\AI\AiGateway;
use Illuminate\Support\Facades\Log;

/**
 * Full LLM post-conversation reflection: satisfaction, mistakes, operating guides, improvement notes.
 */
final class ConversationReflectionService
{
    public function __construct(
        protected AiGateway $aiGateway,
        protected AgentMemoryService $agentMemory,
        protected AgentOperatingGuideService $operatingGuides,
    ) {}

    public function reflect(Company $company, Chat $chat): bool
    {
        $company->loadMissing('settings');
        if (! CommerceAgentReplyService::isEnabledForCompany($company)) {
            return false;
        }

        $messages = Message::query()
            ->where('chat_id', $chat->id)
            ->orderBy('id')
            ->limit(30)
            ->get(['sender', 'content']);

        if ($messages->count() < 3) {
            return false;
        }

        $transcript = $messages->map(fn (Message $m) => strtoupper($m->sender).': '.trim((string) $m->content))->implode("\n");

        $system = <<<'TEXT'
Reflect on this WhatsApp commerce conversation. Return JSON:
{
  "goal_achieved": true|false,
  "customer_satisfaction_estimate": "high|medium|low",
  "satisfaction_score": 0-100,
  "mistakes": ["..."],
  "missed_opportunities": ["..."],
  "tool_efficiency": "brief note",
  "improvement_notes": ["actionable improvements for next time"],
  "operating_guide_updates": [{"topic":"snake_case","guidance":"..."}],
  "insight": "one sentence for future conversations"
}
TEXT;

        try {
            $result = $this->aiGateway->chatCompletion(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => mb_substr($transcript, 0, 8000)],
                ],
                useCase: 'agent_reflection',
                company: $company,
                chatId: (int) $chat->id,
                maxTokens: 600,
                temperature: 0.2,
                jsonMode: true,
                timeoutSeconds: 30,
            );

            if (! $result->success || ! $result->content) {
                return false;
            }

            $parsed = json_decode($result->content, true);
            if (! is_array($parsed)) {
                return false;
            }

            $this->persistReflection($company, $chat, $parsed);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Conversation reflection failed', [
                'company_id' => $company->id,
                'chat_id' => $chat->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function persistReflection(Company $company, Chat $chat, array $parsed): void
    {
        $satisfaction = (string) ($parsed['customer_satisfaction_estimate'] ?? 'medium');
        $score = max(0, min(100, (int) ($parsed['satisfaction_score'] ?? 50)));

        AgentReflection::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'reflection_type' => 'conversation_review',
            'content' => (string) ($parsed['insight'] ?? 'Conversation reviewed.'),
            'metadata' => [
                'goal_achieved' => (bool) ($parsed['goal_achieved'] ?? false),
                'customer_satisfaction' => $satisfaction,
                'satisfaction_score' => $score,
                'mistakes' => $parsed['mistakes'] ?? [],
                'missed_opportunities' => $parsed['missed_opportunities'] ?? [],
                'tool_efficiency' => $parsed['tool_efficiency'] ?? null,
                'improvement_notes' => $parsed['improvement_notes'] ?? [],
                'full_reflection' => $parsed,
            ],
        ]);

        if (! empty($parsed['insight']) && is_string($parsed['insight'])) {
            $this->agentMemory->store(
                (int) $company->id,
                (int) $chat->id,
                'improvement',
                $parsed['insight'],
                ['reflection' => true, 'satisfaction_score' => $score],
            );
        }

        foreach ($parsed['operating_guide_updates'] ?? [] as $update) {
            if (! is_array($update)) {
                continue;
            }
            $this->operatingGuides->upsert(
                (int) $company->id,
                (string) ($update['topic'] ?? 'general'),
                (string) ($update['guidance'] ?? ''),
                'reflection',
            );
        }

        foreach ($parsed['improvement_notes'] ?? [] as $note) {
            if (! is_string($note) || trim($note) === '') {
                continue;
            }
            $this->agentMemory->store(
                (int) $company->id,
                (int) $chat->id,
                'improvement_note',
                trim($note),
                ['satisfaction' => $satisfaction],
            );
        }

        if ($satisfaction === 'low' || $score < 40) {
            $this->agentMemory->store(
                (int) $company->id,
                (int) $chat->id,
                'pattern',
                'Recent conversation may have left customer unsatisfied — review tone, empathy, and resolution speed.',
                ['satisfaction_score' => $score],
            );
        }
    }
}
