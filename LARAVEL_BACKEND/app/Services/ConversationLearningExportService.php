<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ConversationLearningSample;
use Illuminate\Support\Facades\Response;

final class ConversationLearningExportService
{
    public function exportCsvForCompany(Company $company): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'learning-samples-company-'.$company->id.'-'.now()->format('Y-m-d').'.csv';

        return Response::streamDownload(function () use ($company) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id', 'customer_message', 'assistant_reply', 'source', 'status',
                'language', 'use_count', 'positive_feedback', 'negative_feedback',
                'created_at', 'last_used_at',
            ]);

            ConversationLearningSample::query()
                ->where('company_id', $company->id)
                ->orderBy('id')
                ->chunk(100, function ($samples) use ($handle) {
                    foreach ($samples as $sample) {
                        fputcsv($handle, [
                            $sample->id,
                            $sample->customer_message,
                            $sample->assistant_reply,
                            $sample->source,
                            $sample->status,
                            $sample->language,
                            $sample->use_count,
                            $sample->positive_feedback_count,
                            $sample->negative_feedback_count,
                            $sample->created_at?->toIso8601String(),
                            $sample->last_used_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function purgeForCompany(int $companyId): int
    {
        return ConversationLearningSample::query()
            ->where('company_id', $companyId)
            ->delete();
    }
}
