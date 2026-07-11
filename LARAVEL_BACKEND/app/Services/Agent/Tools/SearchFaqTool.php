<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Conversation\FaqMatchingService;

final class SearchFaqTool implements AgentTool
{
    public function __construct(
        protected FaqMatchingService $faqMatcher,
    ) {}

    public function name(): string
    {
        return 'search_faq';
    }

    public function description(): string
    {
        return 'Search business FAQs and policies. Use for shipping, returns, hours, and common questions.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Customer question or topic'],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(AgentToolContext $context, array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ['found' => false];
        }

        $match = $this->faqMatcher->matchBest($context->company, $query, mb_strtolower($query));
        if ($match === null) {
            return ['found' => false, 'message' => 'No FAQ match.'];
        }

        return [
            'found' => true,
            'answer' => $match['answer'],
            'faq_id' => $match['faq_id'],
            'confidence' => $match['score'] ?? null,
        ];
    }
}
