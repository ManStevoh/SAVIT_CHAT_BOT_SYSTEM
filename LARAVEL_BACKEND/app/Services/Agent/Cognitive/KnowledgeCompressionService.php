<?php

namespace App\Services\Agent\Cognitive;

use App\Models\Chat;
use App\Models\Company;
use App\Models\KnowledgeArtifact;
use App\Models\Message;

/**
 * Knowledge compression (#45) — durable knowledge from chat volume.
 */
final class KnowledgeCompressionService
{
    /**
     * @return list<KnowledgeArtifact>
     */
    public function compressForCompany(Company $company): array
    {
        $companyId = (int) $company->id;
        $created = [];

        $confusion = $this->detectProductConfusion($companyId);
        if ($confusion) {
            $created[] = $confusion;
        }

        return $created;
    }

    private function detectProductConfusion(int $companyId): ?KnowledgeArtifact
    {
        $messages = Message::query()
            ->whereHas('chat', fn ($q) => $q->where('company_id', $companyId))
            ->where('sender', 'customer')
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($q) {
                $q->where('content', 'like', '%difference%')
                    ->orWhere('content', 'like', '%which one%')
                    ->orWhere('content', 'like', '%model%')
                    ->orWhere('content', 'like', '%confus%');
            })
            ->count();

        if ($messages < 3) {
            return null;
        }

        $title = 'Product comparison guide needed';
        $exists = KnowledgeArtifact::query()
            ->where('company_id', $companyId)
            ->where('title', $title)
            ->where('status', 'draft')
            ->exists();

        if ($exists) {
            return null;
        }

        return KnowledgeArtifact::create([
            'company_id' => $companyId,
            'artifact_type' => 'comparison_guide',
            'title' => $title,
            'content' => 'Customers frequently ask about product differences. Draft a comparison guide and update FAQs.',
            'source_chat_count' => $messages,
            'evidence' => ['confusion_message_count' => $messages, 'period_days' => 90],
            'status' => 'draft',
        ]);
    }
}
