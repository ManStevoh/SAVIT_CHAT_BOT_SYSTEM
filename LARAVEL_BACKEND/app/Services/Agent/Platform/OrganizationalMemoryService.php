<?php

namespace App\Services\Agent\Platform;

use App\Models\Company;
use App\Models\OrganizationalMemory;

/**
 * Organizational / institutional memory (#23).
 */
final class OrganizationalMemoryService
{
    public function store(
        int $companyId,
        string $category,
        string $title,
        string $content,
        string $source = 'agent',
    ): OrganizationalMemory {
        return OrganizationalMemory::create([
            'company_id' => $companyId,
            'category' => mb_substr($category, 0, 60),
            'title' => mb_substr($title, 0, 200),
            'content' => mb_substr(trim($content), 0, 4000),
            'source' => mb_substr($source, 0, 40),
        ]);
    }

    public function getForPrompt(Company $company, int $limit = 6): string
    {
        $rows = OrganizationalMemory::query()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['category', 'title', 'content']);

        if ($rows->isEmpty()) {
            return '';
        }

        $lines = ['Organizational memory (institutional knowledge):'];
        foreach ($rows as $row) {
            $lines[] = "- [{$row->category}] {$row->title}: ".mb_substr($row->content, 0, 200);
        }

        return implode("\n", $lines);
    }
}
