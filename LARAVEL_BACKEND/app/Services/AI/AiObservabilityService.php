<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\Faq;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

final class AiObservabilityService
{
    public function __construct(
        protected AiLearningConfig $learningConfig,
    ) {}

    /**
     * @return array<int, array{level: string, code: string, message: string}>
     */
    public function check(): array
    {
        $alerts = [];

        $this->checkLearningCoverage($alerts);
        $this->checkFaqCoverage($alerts);
        $this->checkByokFailures($alerts);
        $this->checkWebhook403($alerts);
        $this->checkQueueBacklog($alerts);

        return $alerts;
    }

    public function notifyAdminsIfNeeded(): int
    {
        $alerts = array_filter($this->check(), fn ($a) => $a['level'] === 'critical' || $a['level'] === 'warning');
        foreach ($alerts as $alert) {
            Log::warning('AI observability alert', $alert);
        }

        return count($alerts);
    }

    /**
     * @param  array<int, array{level: string, code: string, message: string}>  $alerts
     */
    private function checkLearningCoverage(array &$alerts): void
    {
        $approved = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->count();
        if ($approved < 10) {
            return;
        }

        $withEmb = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->whereNotNull('question_embedding')
            ->count();
        $pct = (int) round(($withEmb / $approved) * 100);
        if ($pct < 80) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'learning_embedding_coverage_low',
                'message' => "Learning embedding coverage is {$pct}% (target 80%). Run: php artisan learning:sync-embeddings --missing-only",
            ];
        }
    }

    /**
     * @param  array<int, array{level: string, code: string, message: string}>  $alerts
     */
    private function checkFaqCoverage(array &$alerts): void
    {
        $active = Faq::query()->where('is_active', true)->count();
        if ($active < 5) {
            return;
        }
        $withEmb = Faq::query()->where('is_active', true)->whereNotNull('question_embedding')->count();
        $pct = (int) round(($withEmb / $active) * 100);
        if ($pct < 80) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'faq_embedding_coverage_low',
                'message' => "FAQ embedding coverage is {$pct}% (target 80%). Run: php artisan faqs:sync-embeddings",
            ];
        }
    }

    /**
     * @param  array<int, array{level: string, code: string, message: string}>  $alerts
     */
    private function checkByokFailures(array &$alerts): void
    {
        $failures = DB::table('ai_request_logs')
            ->where('credential_source', 'company')
            ->where('success', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        if ($failures >= 5) {
            $alerts[] = [
                'level' => 'critical',
                'code' => 'byok_failures',
                'message' => "{$failures} BYOK AI request failures in the last 24 hours.",
            ];
        }
    }

    /**
     * @param  array<int, array{level: string, code: string, message: string}>  $alerts
     */
    private function checkWebhook403(array &$alerts): void
    {
        // Log channel or audit — use recent failed jobs as proxy if no dedicated webhook log
        $recent = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHours(6))
            ->where('payload', 'like', '%WhatsAppWebhook%')
            ->count();
        if ($recent > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'webhook_failures',
                'message' => "{$recent} WhatsApp webhook-related failed jobs in 6h. Verify meta_app_secret.",
            ];
        }
    }

    /**
     * @param  array<int, array{level: string, code: string, message: string}>  $alerts
     */
    private function checkQueueBacklog(array &$alerts): void
    {
        try {
            $size = Queue::size();
            if ($size > 500) {
                $alerts[] = [
                    'level' => 'critical',
                    'code' => 'queue_backlog',
                    'message' => "Queue backlog is {$size} jobs. Ensure queue:work is running.",
                ];
            }
        } catch (\Throwable) {
            // Queue driver may not support size()
        }
    }
}
