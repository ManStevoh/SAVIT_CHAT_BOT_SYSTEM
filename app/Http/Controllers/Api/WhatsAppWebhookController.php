<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Chat;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Webhook verification (GET). Meta sends hub.mode, hub.verify_token, hub.challenge.
     */
    public function verify(Request $request): Response|string
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = PlatformSetting::first()?->whatsapp_webhook_verify_token ?? config('whatsapp.webhook_verify_token');
        if ($mode === 'subscribe' && $verifyToken !== '' && $token === $verifyToken) {
            return response($challenge ?? '', 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook receiver (POST). Meta sends incoming messages and status updates.
     * Respond 200 quickly and process in a job.
     */
    public function receive(Request $request): Response
    {
        $secret = PlatformSetting::first()?->meta_app_secret ?? config('whatsapp.app_secret');
        if ($secret !== null && $secret !== '') {
            $signature = $request->header('X-Hub-Signature-256');
            if (! $signature || ! $this->verifySignature($request->getContent(), $signature, $secret)) {
                Log::warning('WhatsApp webhook signature verification failed');
                return response('', 403);
            }
        }

        $body = $request->all();
        $object = $body['object'] ?? null;
        if ($object !== 'whatsapp_business_account') {
            return response('', 200);
        }

        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                $this->processValue($value);
            }
        }

        return response('', 200);
    }

    protected function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }
        $hash = hash_hmac('sha256', $payload, $secret);
        return hash_equals('sha256=' . $hash, $signature);
    }

    protected function processValue(array $value): void
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
            $this->processMessage($msg, $companyId, $phoneNumberId, $customerName);
        }
    }

    protected function processMessage(array $msg, int $companyId, string $phoneNumberId, ?string $customerName): void
    {
        $type = $msg['type'] ?? '';
        $from = (string) ($msg['from'] ?? '');
        $waMessageId = $msg['id'] ?? null;

        if ($type === 'text') {
            $text = $msg['text']['body'] ?? '';
            $this->saveIncomingAndDispatchReply($companyId, $phoneNumberId, $from, $customerName, $text, $waMessageId);
            return;
        }

        if (in_array($type, ['image', 'audio', 'document', 'video'])) {
            $caption = $msg[$type]['caption'] ?? '';
            $text = $caption !== '' ? $caption : "[{$type} received]";
            $this->saveIncomingAndDispatchReply($companyId, $phoneNumberId, $from, $customerName, $text, $waMessageId);
        }
    }

    protected function saveIncomingAndDispatchReply(
        int $companyId,
        string $phoneNumberId,
        string $customerPhone,
        ?string $customerName,
        string $text,
        ?string $waMessageId
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

        if ($chat->wasRecentlyCreated === false) {
            $chat->increment('unread_count');
            $chat->update([
                'last_message' => $text,
                'last_message_at' => now(),
            ]);
        }

        $existing = $waMessageId
            ? Message::where('chat_id', $chat->id)->where('whatsapp_message_id', $waMessageId)->exists()
            : false;
        if ($existing) {
            return;
        }

        Message::create([
            'chat_id' => $chat->id,
            'content' => $text,
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
            $customerName
        );
    }
}
