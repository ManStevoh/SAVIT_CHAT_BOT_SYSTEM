<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    public function __construct(
        protected WhatsAppTemplateService $templates
    ) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json([], 403);
        }

        $items = WhatsAppMessageTemplate::where('company_id', $companyId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (WhatsAppMessageTemplate $t) => $this->toArray($t));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $account = $this->activeAccount($companyId);
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Connect WhatsApp first.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'language' => 'nullable|string|max:10',
            'category' => 'nullable|in:utility,marketing,authentication',
            'body' => 'required|string|max:1024',
        ]);

        $result = $this->templates->createOnMeta($account, $validated);
        $status = $result['success'] ? 200 : 422;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'template' => isset($result['template']) ? $this->toArray($result['template']) : null,
        ], $status);
    }

    public function sync(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $account = $this->activeAccount($companyId);
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Connect WhatsApp first.'], 422);
        }

        $result = $this->templates->syncFromMeta($account);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function destroy(Request $request, WhatsAppMessageTemplate $template): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId || (int) $template->company_id !== (int) $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $account = $this->activeAccount($companyId);
        if (! $account) {
            return response()->json(['success' => false, 'message' => 'Connect WhatsApp first.'], 422);
        }

        $result = $this->templates->deleteOnMeta($template, $account);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    protected function activeAccount(int $companyId): ?WhatsAppAccount
    {
        return WhatsAppAccount::where('company_id', $companyId)->where('status', 'active')->first();
    }

    protected function toArray(WhatsAppMessageTemplate $template): array
    {
        return [
            'id' => (string) $template->id,
            'metaTemplateId' => $template->meta_template_id,
            'name' => $template->name,
            'language' => $template->language,
            'category' => $template->category,
            'status' => $template->status,
            'bodyPreview' => $template->body_preview,
            'rejectionReason' => $template->rejection_reason,
            'updatedAt' => $template->updated_at?->toIso8601String(),
        ];
    }
}
