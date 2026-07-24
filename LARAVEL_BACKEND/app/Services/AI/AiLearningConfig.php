<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide AI knowledge & learning policy (GDPR-aware defaults).
 * Super admin configures via platform_settings.ai_learning_config.
 */
final class AiLearningConfig
{
    private const CACHE_KEY = 'platform_ai_learning_config';

    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'learningEnabled' => true,
            'defaultLearnFromChats' => true,
            'allowCompanyOverride' => true,
            'maxSamplesPerCompany' => 200,
            'promptSampleLimit' => 8,
            'retentionDays' => 365,
            'piiRedactionEnabled' => true,
            'storeFaqExchanges' => true,
            'storeAgentReplies' => true,
            'faqEmbeddingsEnabled' => true,
            'learningEmbeddingsEnabled' => true,
            'faqSemanticMinScore' => 0.82,
            'learningSemanticMinScore' => 0.78,
            'faqDirectMinScore' => 72.0,
            'minReplyLength' => 20,
            'maxPromptTokens' => (int) config('openai.max_prompt_tokens', 12000),
            'embeddingModelKey' => (string) config('openai.embedding_model', 'text-embedding-3-small'),
            'requireLearningReview' => false,
            'autoDetectLanguage' => true,
            'fallbackLanguage' => 'en',
            'aiCostMarkupPercent' => 0.0,
        ];
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $stored = PlatformSetting::query()->value('ai_learning_config');
            if (! is_array($stored)) {
                return self::defaults();
            }

            return array_merge(self::defaults(), $stored);
        });
    }

    public function isLearningEnabled(): bool
    {
        return (bool) ($this->all()['learningEnabled'] ?? true);
    }

    public function companyCanLearn(Company $company): bool
    {
        if (! $this->isLearningEnabled()) {
            return false;
        }

        return $this->companyLearnFromChatsEnabled($company);
    }

    public function companyLearnFromChatsEnabled(Company $company): bool
    {
        if (! $this->isLearningEnabled()) {
            return false;
        }

        $company->loadMissing('settings');
        $allowOverride = (bool) ($this->all()['allowCompanyOverride'] ?? true);

        if ($allowOverride && $company->settings) {
            return ($company->settings->learn_from_conversations ?? true) !== false;
        }

        return (bool) ($this->all()['defaultLearnFromChats'] ?? true);
    }

    public function maxSamplesPerCompany(): int
    {
        return max(10, min(2000, (int) ($this->all()['maxSamplesPerCompany'] ?? 200)));
    }

    public function promptSampleLimit(): int
    {
        return max(1, min(25, (int) ($this->all()['promptSampleLimit'] ?? 8)));
    }

    public function retentionDays(): int
    {
        return max(7, min(1825, (int) ($this->all()['retentionDays'] ?? 365)));
    }

    public function piiRedactionEnabled(): bool
    {
        return (bool) ($this->all()['piiRedactionEnabled'] ?? true);
    }

    public function storeFaqExchanges(): bool
    {
        return (bool) ($this->all()['storeFaqExchanges'] ?? true);
    }

    public function storeAgentReplies(): bool
    {
        return (bool) ($this->all()['storeAgentReplies'] ?? false);
    }

    public function faqEmbeddingsEnabled(): bool
    {
        return (bool) ($this->all()['faqEmbeddingsEnabled'] ?? true);
    }

    public function learningEmbeddingsEnabled(): bool
    {
        return (bool) ($this->all()['learningEmbeddingsEnabled'] ?? true);
    }

    public function learningSemanticMinScore(): float
    {
        return max(0.5, min(0.99, (float) ($this->all()['learningSemanticMinScore'] ?? 0.78)));
    }

    public function faqSemanticMinScore(): float
    {
        return max(0.5, min(0.99, (float) ($this->all()['faqSemanticMinScore'] ?? 0.82)));
    }

    public function faqDirectMinScore(): float
    {
        return max(50.0, min(100.0, (float) ($this->all()['faqDirectMinScore'] ?? 72.0)));
    }

    public function minReplyLength(): int
    {
        return max(5, min(500, (int) ($this->all()['minReplyLength'] ?? 20)));
    }

    public function maxPromptTokens(): int
    {
        return max(2000, min(128000, (int) ($this->all()['maxPromptTokens'] ?? 12000)));
    }

    public function embeddingModelKey(): string
    {
        $key = trim((string) ($this->all()['embeddingModelKey'] ?? 'text-embedding-3-small'));

        return $key !== '' ? $key : 'text-embedding-3-small';
    }

    public function requireLearningReview(): bool
    {
        return (bool) ($this->all()['requireLearningReview'] ?? false);
    }

    public function autoDetectLanguage(): bool
    {
        return (bool) ($this->all()['autoDetectLanguage'] ?? true);
    }

    public function fallbackLanguage(): string
    {
        $lang = trim((string) ($this->all()['fallbackLanguage'] ?? 'en'));

        return $lang !== '' ? $lang : 'en';
    }

    public function aiCostMarkupPercent(): float
    {
        return max(0.0, min(100.0, (float) ($this->all()['aiCostMarkupPercent'] ?? 0)));
    }
}
