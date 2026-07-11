<?php

namespace App\Services\Agent\Company;

use App\Models\AgentOperatingGuide;
use App\Models\Company;

/**
 * Self-improving operating instructions learned from reflections.
 */
final class AgentOperatingGuideService
{
    public function getForPrompt(Company $company): string
    {
        $guides = AgentOperatingGuide::query()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit((int) config('agent.company.operating_guide_limit', 8))
            ->get(['topic', 'guidance']);

        if ($guides->isEmpty()) {
            return '';
        }

        $lines = ['Operating guides (learned from past conversations):'];
        foreach ($guides as $guide) {
            $lines[] = "- [{$guide->topic}] {$guide->guidance}";
        }

        return implode("\n", $lines);
    }

    public function upsert(int $companyId, string $topic, string $guidance, string $source = 'reflection'): void
    {
        $topic = mb_substr(trim($topic), 0, 120);
        $guidance = mb_substr(trim($guidance), 0, 2000);
        if ($topic === '' || $guidance === '') {
            return;
        }

        AgentOperatingGuide::updateOrCreate(
            ['company_id' => $companyId, 'topic' => $topic],
            ['guidance' => $guidance, 'source' => mb_substr($source, 0, 40)],
        );
    }
}
