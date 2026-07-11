<?php

namespace App\Services\Agent\Onboarding;

use App\Models\Company;
use App\Services\Agent\Cognitive\BusinessDnaService;
use App\Services\Agent\Company\CompanyDigitalTwinService;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\AiUseCase;
use Illuminate\Support\Facades\Cache;

/**
 * AI interviews the business owner → extracts DNA + digital twin (Concept 4).
 */
final class OnboardingInterviewService
{
    private const CACHE_TTL = 3600;

    public function __construct(
        protected AiOrchestrator $ai,
        protected BusinessDnaService $dna,
        protected CompanyDigitalTwinService $twin,
    ) {}

    /**
     * @return array{sessionId: string, message: string, complete: bool}
     */
    public function start(Company $company): array
    {
        $sessionId = 'onboard_'.$company->id.'_'.uniqid();
        $state = [
            'step' => 0,
            'messages' => [],
            'extracted' => [],
        ];
        Cache::put($this->cacheKey($sessionId), $state, self::CACHE_TTL);

        return [
            'sessionId' => $sessionId,
            'message' => 'Welcome! Tell me about your business — what do you sell, who are your customers, and what makes you different?',
            'complete' => false,
        ];
    }

    /**
     * @return array{message: string, complete: bool, extracted?: array<string, mixed>}
     */
    public function respond(Company $company, string $sessionId, string $ownerMessage): array
    {
        $key = $this->cacheKey($sessionId);
        $state = Cache::get($key);
        if (! is_array($state)) {
            return ['message' => 'Session expired. Please start again.', 'complete' => true];
        }

        $state['messages'][] = ['role' => 'user', 'content' => $ownerMessage];
        $state['step'] = ((int) ($state['step'] ?? 0)) + 1;

        if ($state['step'] >= 3 || $this->looksComplete($ownerMessage)) {
            $extracted = $this->extractProfile($company, $state['messages']);
            $state['extracted'] = $extracted;
            Cache::put($key, $state, self::CACHE_TTL);
            $this->applyToCompany($company, $extracted);

            return [
                'message' => 'Thank you! I have updated your Business DNA and Digital Twin from our conversation. You can refine them anytime in Settings.',
                'complete' => true,
                'extracted' => $extracted,
            ];
        }

        $followUps = [
            'What tone should your AI use with customers — formal, friendly, or luxury?',
            'Who are your main competitors, and what are your top business goals this quarter?',
        ];
        $reply = $followUps[min($state['step'] - 1, count($followUps) - 1)];
        $state['messages'][] = ['role' => 'assistant', 'content' => $reply];
        Cache::put($key, $state, self::CACHE_TTL);

        return ['message' => $reply, 'complete' => false];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function extractProfile(Company $company, array $messages): array
    {
        $transcript = collect($messages)
            ->map(fn ($m) => strtoupper($m['role']).': '.$m['content'])
            ->implode("\n");

        $system = <<<'TEXT'
Extract business profile from owner onboarding interview. Return JSON only:
{
  "business_dna": {"tone":"","values":[],"risk_tolerance":"medium","service_philosophy":"","communication_style":""},
  "digital_twin": {"mission":"","brand_voice":"","sales_strategy":"","target_customers":"","competitors":""},
  "industry_guess": ""
}
TEXT;

        $result = $this->ai->reason(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $transcript],
            ],
            $company,
            maxTokens: 500,
        );

        if (! $result->success || ! $result->content) {
            return ['business_dna' => [], 'digital_twin' => []];
        }

        $parsed = json_decode($result->content, true);

        return is_array($parsed) ? $parsed : ['business_dna' => [], 'digital_twin' => []];
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    private function applyToCompany(Company $company, array $extracted): void
    {
        $settings = $company->settings;
        if (! $settings) {
            return;
        }

        if (! empty($extracted['business_dna']) && is_array($extracted['business_dna'])) {
            $settings->business_dna = $extracted['business_dna'];
        }
        if (! empty($extracted['digital_twin']) && is_array($extracted['digital_twin'])) {
            $settings->digital_twin = $extracted['digital_twin'];
        }
        if (! empty($extracted['industry_guess']) && ! $company->industry) {
            $company->industry = (string) $extracted['industry_guess'];
            $company->save();
        }
        $settings->save();
    }

    private function looksComplete(string $message): bool
    {
        return mb_strlen(trim($message)) > 120;
    }

    private function cacheKey(string $sessionId): string
    {
        return 'onboarding_interview:'.$sessionId;
    }
}
