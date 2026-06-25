<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessageTemplate;
use App\Services\WhatsApp\WhatsAppCampaignCaptionService;
use App\Services\WhatsApp\WhatsAppCampaignDispatchService;
use App\Services\WhatsApp\WhatsAppCampaignLimitService;
use App\Services\WhatsApp\WhatsAppCampaignSegmentService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhatsAppCampaignController extends Controller
{
    public function __construct(
        private WhatsAppCampaignSegmentService $segments,
        private WhatsAppCampaignDispatchService $dispatch,
        private WhatsAppCampaignCaptionService $captions,
    ) {}

    public function limits(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return response()->json(WhatsAppCampaignLimitService::usageSummary($company));
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $campaigns = WhatsAppCampaign::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (WhatsAppCampaign $c) => $this->formatCampaign($c));

        return response()->json($campaigns->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:120',
            'segment' => 'nullable|string|in:all,recent,inactive,ordered',
            'templateName' => 'nullable|string|max:120',
            'languageCode' => 'nullable|string|max:10',
            'caption' => 'nullable|string|max:1024',
            'posterUrl' => 'nullable|string|max:500',
            'socialPostId' => 'nullable|integer',
        ]);

        $socialPost = null;
        if (! empty($validated['socialPostId'])) {
            $socialPost = SocialPost::query()
                ->where('company_id', $company->id)
                ->where('id', $validated['socialPostId'])
                ->first();
        }

        $posterUrl = $validated['posterUrl'] ?? null;
        if (! $posterUrl && $socialPost) {
            $media = $socialPost->media_urls ?? [];
            $posterUrl = $media[0] ?? null;
        }

        $campaign = WhatsAppCampaign::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'social_post_id' => $socialPost?->id,
            'name' => $validated['name'] ?? ('Campaign '.now()->format('M j, H:i')),
            'status' => WhatsAppCampaign::STATUS_DRAFT,
            'segment' => $validated['segment'] ?? 'all',
            'template_name' => $validated['templateName'] ?? null,
            'language_code' => $validated['languageCode'] ?? 'en',
            'poster_url' => $posterUrl ? $this->dispatch->absolutePublicUrl($posterUrl) : null,
            'caption' => $validated['caption'] ?? $socialPost?->content,
            'body_parameters' => isset($validated['caption']) ? [mb_substr($validated['caption'], 0, 1024)] : null,
        ]);

        return response()->json([
            'success' => true,
            'campaign' => $this->formatCampaign($campaign),
        ], 201);
    }

    public function show(Request $request, WhatsAppCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        return response()->json($this->formatCampaign($campaign->loadCount([
            'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'recipients as failed_count' => fn ($q) => $q->where('status', 'failed'),
        ])));
    }

    public function update(Request $request, WhatsAppCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        if (! $campaign->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Campaign is not editable.'], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'segment' => 'sometimes|string|in:all,recent,inactive,ordered',
            'templateName' => 'nullable|string|max:120',
            'languageCode' => 'nullable|string|max:10',
            'caption' => 'nullable|string|max:1024',
            'posterUrl' => 'nullable|string|max:500',
            'socialPostId' => 'nullable|integer',
        ]);

        if (array_key_exists('socialPostId', $validated) && $validated['socialPostId']) {
            $socialPost = SocialPost::query()
                ->where('company_id', $campaign->company_id)
                ->where('id', $validated['socialPostId'])
                ->first();
            if ($socialPost) {
                $campaign->social_post_id = $socialPost->id;
                if (empty($validated['posterUrl'])) {
                    $media = $socialPost->media_urls ?? [];
                    $campaign->poster_url = isset($media[0])
                        ? $this->dispatch->absolutePublicUrl($media[0])
                        : $campaign->poster_url;
                }
                if (! array_key_exists('caption', $validated) && $socialPost->content) {
                    $campaign->caption = $socialPost->content;
                }
            }
        }

        if (array_key_exists('name', $validated)) {
            $campaign->name = $validated['name'];
        }
        if (array_key_exists('segment', $validated)) {
            $campaign->segment = $validated['segment'];
        }
        if (array_key_exists('templateName', $validated)) {
            $campaign->template_name = $validated['templateName'];
        }
        if (array_key_exists('languageCode', $validated)) {
            $campaign->language_code = $validated['languageCode'];
        }
        if (array_key_exists('caption', $validated)) {
            $campaign->caption = $validated['caption'];
            $campaign->body_parameters = $validated['caption']
                ? [mb_substr($validated['caption'], 0, 1024)]
                : null;
        }
        if (array_key_exists('posterUrl', $validated)) {
            $campaign->poster_url = $validated['posterUrl']
                ? $this->dispatch->absolutePublicUrl($validated['posterUrl'])
                : null;
        }

        $campaign->save();

        return response()->json(['success' => true, 'campaign' => $this->formatCampaign($campaign->fresh())]);
    }

    public function audience(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $segment = $request->input('segment', 'all');

        return response()->json([
            'uniqueCustomers' => $this->segments->countAudience($company, $segment),
            'segment' => $segment,
            'recipientsLimit' => WhatsAppCampaignLimitService::getRecipientsLimit($company),
            'note' => 'Outside the 24-hour window, only approved Meta templates can be sent.',
        ]);
    }

    public function growthPosts(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json([], 403);
        }

        $posts = SocialPost::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['draft', 'scheduled', 'published'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get(['id', 'title', 'content', 'platform', 'media_urls', 'status'])
            ->map(fn (SocialPost $p) => [
                'id' => (string) $p->id,
                'title' => $p->title,
                'content' => $p->content,
                'platform' => $p->platform,
                'mediaUrls' => $p->media_urls ?? [],
                'status' => $p->status,
            ]);

        return response()->json($posts->values()->all());
    }

    public function generateCaption(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'topic' => 'nullable|string|max:200',
            'tone' => 'nullable|string|max:80',
            'posterHint' => 'nullable|string|max:300',
        ]);

        $outcome = $this->captions->generate($company, $validated);

        if (! $outcome['success']) {
            return response()->json([
                'success' => false,
                'message' => $outcome['error'] ?? 'Caption generation failed',
            ], 422);
        }

        return response()->json(['success' => true, 'caption' => $outcome['caption']]);
    }

    public function uploadPoster(Request $request, WhatsAppCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        if (! $campaign->isEditable()) {
            return response()->json(['success' => false, 'message' => 'Campaign is not editable.'], 422);
        }

        $validated = $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $path = $validated['image']->store('whatsapp-campaigns/'.$campaign->company_id, 'public');
        $url = $this->dispatch->absolutePublicUrl(Storage::disk('public')->url($path));

        $campaign->update(['poster_url' => $url]);

        return response()->json([
            'success' => true,
            'url' => $url,
            'campaign' => $this->formatCampaign($campaign->fresh()),
        ]);
    }

    public function sendCampaign(Request $request, WhatsAppCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $outcome = $this->dispatch->dispatch($campaign, $company);

        if (! $outcome['success']) {
            return response()->json([
                'success' => false,
                'message' => $outcome['message'] ?? 'Send failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign queued for delivery.',
            'campaign' => $this->formatCampaign($outcome['campaign']),
        ]);
    }

    public function testSend(Request $request, WhatsAppCampaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $outcome = $this->dispatch->sendTest($campaign, $company, $validated['phone']);

        return response()->json($outcome, $outcome['success'] ? 200 : 422);
    }

    /** @deprecated Use wizard sendCampaign — kept for Settings quick-send */
    public function send(Request $request, WhatsAppMessageSenderService $sender): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'mode' => 'required|string|in:template,image',
            'templateName' => 'required_if:mode,template|nullable|string|max:120',
            'languageCode' => 'nullable|string|max:10',
            'bodyParameters' => 'nullable|array',
            'bodyParameters.*' => 'string|max:1024',
            'imageUrl' => 'required_if:mode,image|nullable|url|max:500',
            'caption' => 'nullable|string|max:1024',
            'segment' => 'nullable|string|in:all,recent,inactive,ordered',
        ]);

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return response()->json(['success' => false, 'message' => 'No active WhatsApp connection.'], 422);
        }

        $recipients = $this->segments->recipients($company, $validated['segment'] ?? 'all');
        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No customers with phone numbers.'], 422);
        }

        if ($validated['mode'] === 'template') {
            $template = WhatsAppMessageTemplate::query()
                ->where('company_id', $company->id)
                ->where('name', $validated['templateName'])
                ->where('status', 'approved')
                ->first();
            if (! $template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found or not approved by Meta.',
                ], 422);
            }
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $row) {
            if ($validated['mode'] === 'template') {
                $result = $sender->sendTemplate(
                    $account,
                    $row['phone'],
                    $validated['templateName'],
                    $validated['languageCode'] ?? 'en',
                    $validated['bodyParameters'] ?? [],
                );
            } else {
                $result = $sender->sendImage(
                    $account,
                    $row['phone'],
                    $this->dispatch->absolutePublicUrl($validated['imageUrl']) ?? $validated['imageUrl'],
                    $validated['caption'] ?? null,
                );
            }

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                if (count($errors) < 3) {
                    $errors[] = $result['error'] ?? 'unknown';
                }
            }
        }

        return response()->json([
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'total' => $recipients->count(),
            'errors' => $errors,
            'message' => "Sent to {$sent} of {$recipients->count()} customers.",
        ]);
    }

    private function authorizeCampaign(Request $request, WhatsAppCampaign $campaign): void
    {
        if ((int) $request->user()->company_id !== (int) $campaign->company_id) {
            abort(403);
        }
    }

    private function formatCampaign(WhatsAppCampaign $campaign): array
    {
        return [
            'id' => (string) $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
            'segment' => $campaign->segment,
            'templateName' => $campaign->template_name,
            'languageCode' => $campaign->language_code,
            'posterUrl' => $campaign->poster_url,
            'caption' => $campaign->caption,
            'bodyParameters' => $campaign->body_parameters ?? [],
            'socialPostId' => $campaign->social_post_id ? (string) $campaign->social_post_id : null,
            'totalRecipients' => (int) $campaign->total_recipients,
            'sentCount' => (int) $campaign->sent_count,
            'failedCount' => (int) ($campaign->failed_count ?? 0),
            'pendingCount' => isset($campaign->pending_count) ? (int) $campaign->pending_count : null,
            'startedAt' => $campaign->started_at?->toIso8601String(),
            'completedAt' => $campaign->completed_at?->toIso8601String(),
            'errorSummary' => $campaign->error_summary,
            'createdAt' => $campaign->created_at?->toIso8601String(),
        ];
    }
}
