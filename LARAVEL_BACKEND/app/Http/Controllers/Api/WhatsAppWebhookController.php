<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Growth\AttributionService;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    /**
     * Webhook verification (GET). Meta sends hub.mode, hub.verify_token, hub.challenge.
     * Accepts the platform verify token, known legacy tokens, or any company's custom verify token.
     */
    public function verify(Request $request): Response|string
    {
        $mode = $request->query('hub_mode')
             ?? $request->query('hub.mode')
             ?? $request->input('hub_mode')
             ?? $request->input('hub.mode');

        $token = (string) (
            $request->query('hub_verify_token')
            ?? $request->query('hub.verify_token')
            ?? $request->input('hub_verify_token')
            ?? $request->input('hub.verify_token')
            ?? ''
        );

        $challenge = $request->query('hub_challenge')
                  ?? $request->query('hub.challenge')
                  ?? $request->input('hub_challenge')
                  ?? $request->input('hub.challenge');

        if ($mode === 'subscribe' && $token !== '' && $this->isValidWebhookVerifyToken($token)) {
            return response($challenge ?? '', 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    protected function isValidWebhookVerifyToken(string $token): bool
    {
        $platformToken = WhatsAppPlatformConfig::webhookVerifyToken();
        if ($platformToken !== '' && hash_equals($platformToken, $token)) {
            return true;
        }

        // Temporary compatibility while Meta apps still use these known tokens.
        if (in_array($token, [
            'relayiq_webhook_verify_token',
            'essemchat_whatsapp_verify_token',
        ], true)) {
            return true;
        }

        // When platform token is unset, keep previous permissive behavior for Meta handshake.
        if ($platformToken === '') {
            return true;
        }

        return WhatsAppAccount::query()
            ->whereNotNull('verify_token')
            ->where('verify_token', '!=', '')
            ->where('verify_token', $token)
            ->exists();
    }

    /**
     * Webhook receiver (POST). Always return 200 so Meta doesn't retry; process in try/catch.
     */
    public function receive(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $this->isValidWebhookSignature($payload, $signature)) {
            return response('', 403);
        }

        try {
            $this->rateLimitWebhook($request);
            $body = $request->all();
            $object = $body['object'] ?? null;
            if ($object !== 'whatsapp_business_account') {
                return response('', 200);
            }

            foreach ($body['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $field = $change['field'] ?? '';
                    $value = $change['value'] ?? [];
                    if ($field === 'messages') {
                        $this->processMessagesValue($value);
                        $this->processStatusesValue($value);
                    } elseif ($field === 'phone_number_name_update') {
                        $this->processPhoneNumberNameUpdate($value);
                    } elseif ($field === 'message_template_status_update') {
                        $this->processTemplateStatusUpdate($value);
                    } elseif ($field === 'account_update') {
                        $this->processAccountUpdate($value);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response('', 200);
    }

    /**
     * Accept platform App Secret (Embedded Signup / tech-provider app) OR the company
     * Meta App Secret stored on the WhatsApp account for manual/BYO Meta apps.
     */
    protected function isValidWebhookSignature(string $payload, ?string $signature): bool
    {
        $candidates = $this->candidateWebhookAppSecrets($payload);
        $hasAnySecret = $candidates !== [];

        if (! $hasAnySecret) {
            if (app()->environment('production')) {
                Log::critical('WhatsApp webhook rejected: no meta_app_secret configured (platform or company)');

                return false;
            }

            // Local/dev convenience when nothing is configured yet.
            return true;
        }

        if (! $signature) {
            Log::warning('WhatsApp webhook signature verification failed', ['reason' => 'missing_header']);

            return false;
        }

        foreach ($candidates as $secret) {
            if ($this->verifySignature($payload, $signature, $secret)) {
                return true;
            }
        }

        Log::warning('WhatsApp webhook signature verification failed', [
            'reason' => 'no_matching_secret',
            'candidate_count' => count($candidates),
        ]);

        return false;
    }

    /**
     * @return list<string>
     */
    protected function candidateWebhookAppSecrets(string $payload): array
    {
        $secrets = [];
        $platformSecret = trim((string) WhatsAppPlatformConfig::metaAppSecret());
        if ($platformSecret !== '') {
            $secrets[] = $platformSecret;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return $secrets;
        }

        $phoneNumberIds = [];
        $wabaIds = [];
        foreach ($decoded['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entryId = trim((string) ($entry['id'] ?? ''));
            if ($entryId !== '') {
                $wabaIds[] = $entryId;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }
                $phoneId = trim((string) (
                    $value['metadata']['phone_number_id']
                    ?? $value['phone_number_id']
                    ?? $value['display_phone_number_id']
                    ?? ''
                ));
                if ($phoneId !== '') {
                    $phoneNumberIds[] = $phoneId;
                }
            }
        }

        $phoneNumberIds = array_values(array_unique($phoneNumberIds));
        $wabaIds = array_values(array_unique($wabaIds));

        if ($phoneNumberIds === [] && $wabaIds === []) {
            return $secrets;
        }

        $accounts = WhatsAppAccount::query()
            ->where(function ($query) use ($phoneNumberIds, $wabaIds) {
                if ($phoneNumberIds !== []) {
                    $query->orWhereIn('phone_number_id', $phoneNumberIds);
                }
                if ($wabaIds !== []) {
                    $query->orWhereIn('whatsapp_business_account_id', $wabaIds);
                }
            })
            ->whereNotNull('meta_app_secret')
            ->get(['id', 'meta_app_secret']);

        foreach ($accounts as $account) {
            $companySecret = trim((string) ($account->meta_app_secret ?? ''));
            if ($companySecret !== '') {
                $secrets[] = $companySecret;
            }
        }

        return array_values(array_unique($secrets));
    }

    protected function getPlatformSettings(): ?PlatformSetting
    {
        return WhatsAppPlatformConfig::settings();
    }

    protected function processPhoneNumberNameUpdate(array $value): void
    {
        $phoneNumberId = (string) ($value['phone_number_id'] ?? $value['display_phone_number_id'] ?? '');
        if ($phoneNumberId === '') {
            return;
        }

        $account = WhatsAppAccount::where('phone_number_id', $phoneNumberId)->first();
        if (! $account) {
            return;
        }

        $decision = $value['decision'] ?? $value['event'] ?? $value['status'] ?? null;
        if ($decision !== null) {
            $account->update(['display_name_status' => (string) $decision]);
        }
    }

    protected function processTemplateStatusUpdate(array $value): void
    {
        $templateName = (string) ($value['message_template_name'] ?? $value['name'] ?? '');
        $language = (string) ($value['message_template_language'] ?? $value['language'] ?? 'en');
        $status = strtolower((string) ($value['event'] ?? $value['status'] ?? ''));
        $wabaId = (string) ($value['whatsapp_business_account_id'] ?? '');

        if ($templateName === '' || $status === '') {
            return;
        }

        $query = WhatsAppMessageTemplate::where('name', $templateName)->where('language', $language);
        if ($wabaId !== '') {
            $companyIds = WhatsAppAccount::where('whatsapp_business_account_id', $wabaId)->pluck('company_id');
            $query->whereIn('company_id', $companyIds);
        }

        $template = $query->first();
        if (! $template) {
            return;
        }

        $template->update([
            'status' => $status,
            'rejection_reason' => $value['reason'] ?? $value['rejected_reason'] ?? $template->rejection_reason,
        ]);
    }

    protected function processAccountUpdate(array $value): void
    {
        $phoneNumberId = (string) ($value['phone_number'] ?? $value['phone_number_id'] ?? '');
        if ($phoneNumberId === '') {
            return;
        }

        $account = WhatsAppAccount::where('phone_number_id', $phoneNumberId)->first();
        if (! $account) {
            return;
        }

        if (isset($value['event']) && str_contains(strtolower((string) $value['event']), 'disable')) {
            $account->update([
                'status' => 'inactive',
                'onboarding_status' => 'suspended_by_meta',
                'onboarding_error' => 'Account update from Meta: ' . $value['event'],
            ]);
        }
    }

    protected function rateLimitWebhook(Request $request): void
    {
        $key = 'wa_webhook_' . $request->ip();
        $count = (int) Cache::get($key, 0);
        if ($count >= 120) {
            Log::warning('WhatsApp webhook rate limit exceeded', ['ip' => $request->ip()]);
            throw new \RuntimeException('Rate limit');
        }
        Cache::put($key, $count + 1, 60);
    }

    protected function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $hash = hash_hmac('sha256', $payload, $secret);
        return hash_equals('sha256=' . $hash, $signature);
    }

    protected function processMessagesValue(array $value): void
    {
        $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
        if ($phoneNumberId === '') {
            return;
        }

        $account = WhatsAppAccount::where('phone_number_id', $phoneNumberId)->where('status', 'active')->first();
        if (! $account) {
            Log::warning('WhatsApp webhook: unknown phone_number_id', ['phone_number_id' => $phoneNumberId]);
            return;
        }

        $companyId = $account->company_id;
        $contacts = $value['contacts'] ?? [];
        $customerName = isset($contacts[0]['profile']['name']) ? $contacts[0]['profile']['name'] : null;

        foreach ($value['messages'] ?? [] as $msg) {
            $this->processMessage($msg, $account, $customerName);
        }
    }

    protected function processMessage(array $msg, WhatsAppAccount $account, ?string $customerName): void
    {
        $type = $msg['type'] ?? '';
        $from = (string) ($msg['from'] ?? '');
        $waMessageId = $msg['id'] ?? null;
        $companyId = (int) $account->company_id;
        $phoneNumberId = (string) $account->phone_number_id;
        $contextWaId = trim((string) ($msg['context']['id'] ?? ''));

        if ($type === 'reaction') {
            return;
        }

        if ($type === 'text') {
            $text = $msg['text']['body'] ?? '';
            $this->saveIncomingAndDispatchReply(
                $companyId,
                $phoneNumberId,
                $from,
                $customerName,
                $text,
                $waMessageId,
                null,
                $contextWaId !== '' ? $contextWaId : null,
            );
            return;
        }

        if (in_array($type, ['image', 'audio', 'document', 'video'])) {
            $caption = $msg[$type]['caption'] ?? '';
            $text = $caption !== '' ? $caption : "[{$type} received]";
            $mediaMeta = $this->downloadIncomingMedia(
                $account,
                $type,
                $msg[$type]['id'] ?? null,
                $msg[$type]['mime_type'] ?? null,
                $msg[$type]['filename'] ?? null
            );
            $this->saveIncomingAndDispatchReply(
                $companyId,
                $phoneNumberId,
                $from,
                $customerName,
                $text,
                $waMessageId,
                $mediaMeta,
                $contextWaId !== '' ? $contextWaId : null,
            );
        }
    }

    protected function processStatusesValue(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $waId = $status['id'] ?? null;
            $statusValue = strtolower((string) ($status['status'] ?? ''));
            if (! $waId || $statusValue === '') {
                continue;
            }

            $message = Message::where('whatsapp_message_id', $waId)->first();
            if (! $message) {
                continue;
            }

            if (! Message::shouldAdvanceStatus($message->status, $statusValue)) {
                continue;
            }

            $message->update(['status' => $statusValue]);
        }
    }

    protected function saveIncomingAndDispatchReply(
        int $companyId,
        string $phoneNumberId,
        string $customerPhone,
        ?string $customerName,
        string $text,
        ?string $waMessageId,
        ?array $mediaMeta = null,
        ?string $contextWaId = null,
    ): void {
        $chat = Chat::firstOrCreate(
            [
                'company_id' => $companyId,
                'customer_phone' => $customerPhone,
            ],
            [
                'customer_name' => $customerName ?? 'Customer',
                'customer_avatar' => null,
                'last_message' => $text,
                'last_message_at' => now(),
                'unread_count' => 0,
                'status' => 'active',
                'ai_handled' => false,
            ]
        );

        if (! $chat->wasRecentlyCreated) {
            if ($customerName !== null && $customerName !== '') {
                $chat->update(['customer_name' => $customerName]);
            }
            $chat->increment('unread_count');
            $chat->update([
                'last_message' => $text,
                'last_message_at' => now(),
            ]);
        }

        $this->attachAttributionFromMessage($chat, $text);

        $existing = $waMessageId
            ? Message::where('chat_id', $chat->id)->where('whatsapp_message_id', $waMessageId)->exists()
            : false;
        if ($existing) {
            return;
        }

        $replyToId = null;
        $contextWaId = trim((string) $contextWaId);
        if ($contextWaId !== '') {
            $replyToId = Message::query()
                ->where('chat_id', $chat->id)
                ->where('whatsapp_message_id', $contextWaId)
                ->value('id');
        }

        $message = Message::create([
            'chat_id' => $chat->id,
            'content' => $text,
            'message_type' => $mediaMeta['message_type'] ?? 'text',
            'attachment_url' => $mediaMeta['attachment_url'] ?? null,
            'attachment_name' => $mediaMeta['attachment_name'] ?? null,
            'attachment_mime' => $mediaMeta['attachment_mime'] ?? null,
            'attachment_size' => $mediaMeta['attachment_size'] ?? null,
            'sender' => 'customer',
            'status' => 'received',
            'whatsapp_message_id' => $waMessageId,
            'reply_to_message_id' => $replyToId,
        ]);

        $dispatchMode = config('whatsapp.auto_reply_via_queue', false) ? 'queue' : (config('whatsapp.auto_reply_after_response', false) ? 'after_response' : 'sync');
        \App\Services\WhatsApp\WhatsAppDebugLogger::info('WEBHOOK_DISPATCHING_AUTO_REPLY', [
            'company_id' => $companyId,
            'chat_id' => $chat->id,
            'customer_phone' => $customerPhone,
            'whatsapp_message_id' => $waMessageId,
            'incoming_message_id' => $message->id,
            'dispatch_mode' => $dispatchMode,
        ]);

        ProcessIncomingWhatsAppMessage::dispatchIncoming(
            $companyId,
            (int) $chat->id,
            $customerPhone,
            $phoneNumberId,
            $text,
            $customerName,
            $waMessageId,
            (int) $message->id,
        );
    }

    /**
     * Download media from WhatsApp by media ID and store locally.
     *
     * @return array{message_type: string, attachment_url: string, attachment_name: string, attachment_mime: string, attachment_size: int}|null
     */
    protected function attachAttributionFromMessage(Chat $chat, string $text): void
    {
        if ($chat->attribution_link_id) {
            return;
        }

        $slug = app(AttributionService::class)->parseReferralFromMessage($text);
        if (! $slug) {
            return;
        }

        $link = app(AttributionService::class)->attachReferralToChat($chat, $slug);
        if ($link) {
            app(AttributionService::class)->recordLead($chat->fresh());
        }
    }

    protected function downloadIncomingMedia(
        WhatsAppAccount $account,
        string $type,
        ?string $mediaId,
        ?string $mimeType,
        ?string $filename
    ): ?array {
        if (! $mediaId) {
            return null;
        }

        $graphUrl = WhatsAppPlatformConfig::graphUrl();

        try {
            $metaResponse = Http::withToken($account->access_token)
                ->timeout(20)
                ->get("{$graphUrl}/{$mediaId}");

            if (! $metaResponse->successful()) {
                Log::warning('WhatsApp media meta fetch failed', [
                    'media_id' => $mediaId,
                    'status' => $metaResponse->status(),
                    'body' => $metaResponse->body(),
                ]);
                return null;
            }

            $mediaUrl = $metaResponse->json('url');
            if (! $mediaUrl) {
                return null;
            }

            $binaryResponse = Http::withToken($account->access_token)
                ->timeout(40)
                ->get($mediaUrl);

            if (! $binaryResponse->successful()) {
                Log::warning('WhatsApp media binary fetch failed', [
                    'media_id' => $mediaId,
                    'status' => $binaryResponse->status(),
                ]);
                return null;
            }

            $effectiveMime = $mimeType ?: ($binaryResponse->header('Content-Type') ?: 'application/octet-stream');
            $ext = explode('/', $effectiveMime)[1] ?? 'bin';
            $safeExt = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'bin';
            $safeName = $filename ?: ("wa-{$mediaId}." . $safeExt);
            $path = 'chat-attachments/incoming/' . date('Y/m') . '/' . Str::uuid() . '-' . $safeName;

            Storage::disk('public')->put($path, $binaryResponse->body());

            return [
                'message_type' => match ($type) {
                    'image' => 'image',
                    'audio' => 'audio',
                    default => 'file',
                },
                'attachment_url' => Storage::disk('public')->url($path),
                'attachment_name' => $safeName,
                'attachment_mime' => $effectiveMime,
                'attachment_size' => strlen($binaryResponse->body()),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media download failed', [
                'media_id' => $mediaId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
