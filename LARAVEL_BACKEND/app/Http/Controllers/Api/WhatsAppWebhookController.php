<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\WhatsAppAccount;
use App\Services\Growth\AttributionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    /** Minutes to cache platform settings. */
    private const PLATFORM_SETTINGS_CACHE_TTL = 5;

    /**
     * Webhook verification (GET). Meta sends hub.mode, hub.verify_token, hub.challenge.
     */
    public function verify(Request $request): Response|string
    {
        $verifyToken = $this->getPlatformSettings()?->whatsapp_webhook_verify_token ?? config('whatsapp.webhook_verify_token');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $verifyToken !== '' && $token === $verifyToken) {
            return response($challenge ?? '', 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook receiver (POST). Always return 200 so Meta doesn't retry; process in try/catch.
     */
    public function receive(Request $request): Response
    {
        $secret = $this->getPlatformSettings()?->meta_app_secret ?? config('whatsapp.app_secret');
        if ($secret !== null && $secret !== '') {
            $signature = $request->header('X-Hub-Signature-256');
            if (! $signature || ! $this->verifySignature($request->getContent(), $signature, $secret)) {
                Log::warning('WhatsApp webhook signature verification failed');
                return response('', 403);
            }
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

    protected function getPlatformSettings(): ?PlatformSetting
    {
        return Cache::remember('platform_settings_whatsapp', self::PLATFORM_SETTINGS_CACHE_TTL * 60, function () {
            return PlatformSetting::first();
        });
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

        if ($type === 'reaction') {
            return;
        }

        if ($type === 'text') {
            $text = $msg['text']['body'] ?? '';
            $this->saveIncomingAndDispatchReply($companyId, $phoneNumberId, $from, $customerName, $text, $waMessageId);
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
                $mediaMeta
            );
        }
    }

    protected function processStatusesValue(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $status) {
            $waId = $status['id'] ?? null;
            $statusValue = $status['status'] ?? null;
            if (! $waId || ! $statusValue) {
                continue;
            }
            Message::where('whatsapp_message_id', $waId)->update(['status' => $statusValue]);
        }
    }

    protected function saveIncomingAndDispatchReply(
        int $companyId,
        string $phoneNumberId,
        string $customerPhone,
        ?string $customerName,
        string $text,
        ?string $waMessageId,
        ?array $mediaMeta = null
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

        Message::create([
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
        ]);

        ProcessIncomingWhatsAppMessage::dispatch(
            $companyId,
            (int) $chat->id,
            $customerPhone,
            $phoneNumberId,
            $text,
            $customerName,
            $waMessageId
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

        $graphUrl = rtrim(config('whatsapp.graph_url', 'https://graph.facebook.com/v21.0'), '/');

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
                'message_type' => $type === 'image' ? 'image' : 'file',
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
