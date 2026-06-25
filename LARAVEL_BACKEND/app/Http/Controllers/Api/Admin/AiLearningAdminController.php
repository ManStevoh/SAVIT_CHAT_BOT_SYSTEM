<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EmbedLearningSampleJob;
use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\Faq;
use App\Models\User;
use App\Services\AI\AiLearningConfig;
use App\Services\ConversationLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AiLearningAdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $config = app(AiLearningConfig::class)->all();
        $totalSamples = ConversationLearningSample::count();
        $activeFaqs = Faq::query()->where('is_active', true)->count();
        $faqWithEmbeddings = Faq::query()
            ->where('is_active', true)
            ->whereNotNull('question_embedding')
            ->count();
        $approvedSamples = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->count();
        $learningWithEmbeddings = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_APPROVED)
            ->whereNotNull('question_embedding')
            ->count();

        $byCompany = ConversationLearningSample::query()
            ->select('company_id', DB::raw('count(*) as total'))
            ->groupBy('company_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $company = Company::find($row->company_id);

                return [
                    'companyId' => (string) $row->company_id,
                    'companyName' => $company?->name ?? 'Unknown',
                    'samples' => (int) $row->total,
                ];
            });

        $pendingReview = ConversationLearningSample::query()
            ->where('status', ConversationLearningSample::STATUS_PENDING)
            ->count();

        $globalQuality = [
            'deadSamples' => ConversationLearningSample::query()
                ->where('status', ConversationLearningSample::STATUS_APPROVED)
                ->where('use_count', 0)
                ->count(),
            'lowQualitySamples' => ConversationLearningSample::query()
                ->where('status', ConversationLearningSample::STATUS_APPROVED)
                ->where('negative_feedback_count', '>', 0)
                ->whereColumn('negative_feedback_count', '>=', 'positive_feedback_count')
                ->count(),
        ];

        return response()->json([
            'config' => $config,
            'stats' => [
                'totalLearningSamples' => $totalSamples,
                'pendingReviewSamples' => $pendingReview,
                'companiesWithSamples' => (int) ConversationLearningSample::query()->distinct('company_id')->count('company_id'),
                'activeFaqs' => $activeFaqs,
                'faqsWithEmbeddings' => $faqWithEmbeddings,
                'embeddingCoveragePercent' => $activeFaqs > 0
                    ? (int) round(($faqWithEmbeddings / $activeFaqs) * 100)
                    : 0,
                'approvedLearningSamples' => $approvedSamples,
                'learningSamplesWithEmbeddings' => $learningWithEmbeddings,
                'learningEmbeddingCoveragePercent' => $approvedSamples > 0
                    ? (int) round(($learningWithEmbeddings / $approvedSamples) * 100)
                    : 0,
                'samplesBySource' => ConversationLearningSample::query()
                    ->selectRaw('source, count(*) as total')
                    ->groupBy('source')
                    ->pluck('total', 'source'),
                'topCompaniesBySamples' => $byCompany,
                'learningQuality' => $globalQuality,
                'oldestSampleAt' => ConversationLearningSample::query()->min('created_at'),
                'newestSampleAt' => ConversationLearningSample::query()->max('created_at'),
            ],
        ]);
    }

    public function purgeSamples(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companyId' => 'nullable|integer|exists:companies,id',
            'confirm' => 'required|string|in:DELETE_ALL_LEARNING_DATA',
        ]);

        $query = ConversationLearningSample::query();
        if (! empty($validated['companyId'])) {
            $query->where('company_id', $validated['companyId']);
        }

        $deleted = $query->delete();

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'message' => $deleted > 0
                ? "Removed {$deleted} learning sample(s)."
                : 'No learning samples to remove.',
        ]);
    }

    public function pruneExpired(ConversationLearningService $learning): JsonResponse
    {
        $total = 0;
        Company::query()->select('id')->orderBy('id')->chunkById(100, function ($companies) use ($learning, &$total) {
            foreach ($companies as $company) {
                $total += $learning->pruneExpiredForCompany((int) $company->id);
            }
        });

        return response()->json([
            'success' => true,
            'deleted' => $total,
            'message' => "Pruned {$total} expired sample(s) per retention policy.",
        ]);
    }

    public function syncFaqEmbeddings(Request $request): JsonResponse
    {
        if (! app(AiLearningConfig::class)->faqEmbeddingsEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'FAQ embeddings are disabled in platform AI learning settings.',
            ], 422);
        }

        $companyId = $request->validate([
            'companyId' => 'nullable|integer|exists:companies,id',
        ])['companyId'] ?? null;

        $params = ['--no-interaction' => true];
        if ($companyId) {
            $params['--company'] = $companyId;
        }

        Artisan::call('faqs:sync-embeddings', $params);

        return response()->json([
            'success' => true,
            'message' => trim(Artisan::output()) ?: 'FAQ embedding sync completed.',
        ]);
    }

    public function syncLearningEmbeddings(Request $request): JsonResponse
    {
        if (! app(AiLearningConfig::class)->learningEmbeddingsEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Learning sample embeddings are disabled in platform AI learning settings.',
            ], 422);
        }

        $companyId = $request->validate([
            'companyId' => 'nullable|integer|exists:companies,id',
        ])['companyId'] ?? null;

        $params = ['--no-interaction' => true, '--missing-only' => true];
        if ($companyId) {
            $params['--company'] = $companyId;
        }

        Artisan::call('learning:sync-embeddings', $params);

        return response()->json([
            'success' => true,
            'message' => trim(Artisan::output()) ?: 'Learning sample embedding sync completed.',
        ]);
    }

    public function syncProductEmbeddings(Request $request): JsonResponse
    {
        $companyId = $request->validate([
            'companyId' => 'nullable|integer|exists:companies,id',
        ])['companyId'] ?? null;

        $params = ['--no-interaction' => true, '--missing-only' => true];
        if ($companyId) {
            $params['--company'] = $companyId;
        }

        Artisan::call('products:sync-embeddings', $params);

        return response()->json([
            'success' => true,
            'message' => trim(Artisan::output()) ?: 'Product embedding sync completed.',
        ]);
    }

    public function listSamples(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,approved,rejected',
            'companyId' => 'nullable|integer|exists:companies,id',
            'perPage' => 'nullable|integer|min:5|max:100',
        ]);

        $query = ConversationLearningSample::query()
            ->with(['company:id,name'])
            ->orderByDesc('created_at');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['companyId'])) {
            $query->where('company_id', $validated['companyId']);
        }

        $paginated = $query->paginate($validated['perPage'] ?? 20);

        return response()->json([
            'samples' => collect($paginated->items())->map(fn (ConversationLearningSample $s) => [
                'id' => (string) $s->id,
                'companyId' => (string) $s->company_id,
                'companyName' => $s->company?->name ?? 'Unknown',
                'customerMessage' => $s->customer_message,
                'assistantReply' => $s->assistant_reply,
                'source' => $s->source,
                'status' => $s->status,
                'language' => $s->language,
                'createdAt' => $s->created_at?->toIso8601String(),
                'reviewedAt' => $s->reviewed_at?->toIso8601String(),
                'reviewNotes' => $s->review_notes,
            ]),
            'meta' => [
                'currentPage' => $paginated->currentPage(),
                'lastPage' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function reviewSample(Request $request, ConversationLearningSample $sample): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'reviewNotes' => 'nullable|string|max:1000',
            'assistantReply' => 'nullable|string|max:2000',
        ]);

        $sample->status = $validated['action'] === 'approve'
            ? ConversationLearningSample::STATUS_APPROVED
            : ConversationLearningSample::STATUS_REJECTED;

        if (! empty($validated['assistantReply'])) {
            $sample->assistant_reply = mb_substr(trim($validated['assistantReply']), 0, 2000);
        }

        $sample->review_notes = $validated['reviewNotes'] ?? null;
        $sample->reviewed_by = $request->user()->id;
        $sample->reviewed_at = now();
        $sample->save();

        if ($sample->status === ConversationLearningSample::STATUS_APPROVED) {
            EmbedLearningSampleJob::dispatch($sample->id);
        }

        return response()->json([
            'success' => true,
            'message' => $sample->status === ConversationLearningSample::STATUS_APPROVED
                ? 'Sample approved for prompt use.'
                : 'Sample rejected.',
        ]);
    }
}
