<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppPlatformConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageSenderService
{
    protected function graphUrl(): string
    {
        return WhatsAppPlatformConfig::graphUrl();
    }

    /**
     * Send a text message to a WhatsApp recipient via Meta Cloud API.
     *
     * @param  WhatsAppAccount  $account  Company's WhatsApp account (phone_number_id + access_token)
     * @param  string  $to  Recipient phone number with country code, no + (e.g. 201234567890)
     * @param  string  $text  Message body (max 4096 chars for text)
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendText(WhatsAppAccount $account, string $to, string $text): array
    {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $url = $this->graphUrl() . '/' . $account->phone_number_id . '/messages';
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => mb_substr($text, 0, 4096),
            ],
        ];

        $response = Http::withToken($account->access_token)
            ->timeout(15)
            ->post($url, $body);

        if ($response->successful()) {
            $data = $response->json();
            $messageId = $data['messages'][0]['id'] ?? null;
            return ['success' => true, 'message_id' => $messageId];
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        Log::warning('WhatsApp send message failed', [
            'phone_number_id' => $account->phone_number_id,
            'to' => $to,
            'status' => $response->status(),
            'error' => $errorMessage,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Send text using company's WhatsApp account by phone_number_id (e.g. from webhook).
     */
    public function sendTextByPhoneNumberId(string $phoneNumberId, string $to, string $text): array
    {
        $account = \App\Models\WhatsAppAccount::where('phone_number_id', $phoneNumberId)->where('status', 'active')->first();
        if (! $account) {
            return ['success' => false, 'error' => 'WhatsApp account not found or inactive'];
        }

        return $this->sendText($account, $to, $text);
    }

    /**
     * Send image by public URL.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendImage(WhatsAppAccount $account, string $to, string $imageUrl, ?string $caption = null): array
    {
        return $this->sendMedia($account, $to, 'image', $imageUrl, $caption);
    }

    /**
     * Send image by uploading file to Meta and sending via media_id.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendImageFile(
        WhatsAppAccount $account,
        string $to,
        string $absolutePath,
        ?string $mimeType = null,
        ?string $caption = null
    ): array {
        $upload = $this->uploadMediaFile($account, $absolutePath, $mimeType, basename($absolutePath));
        if (! $upload['success']) {
            return ['success' => false, 'error' => $upload['error'] ?? 'Media upload failed'];
        }

        return $this->sendMediaById($account, $to, 'image', $upload['media_id'], $caption);
    }

    /**
     * Send document by public URL.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendDocument(WhatsAppAccount $account, string $to, string $documentUrl, ?string $filename = null, ?string $caption = null): array
    {
        return $this->sendMedia($account, $to, 'document', $documentUrl, $caption, $filename);
    }

    /**
     * Send document by uploading file to Meta and sending via media_id.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendDocumentFile(
        WhatsAppAccount $account,
        string $to,
        string $absolutePath,
        ?string $mimeType = null,
        ?string $filename = null,
        ?string $caption = null
    ): array {
        $upload = $this->uploadMediaFile($account, $absolutePath, $mimeType, $filename ?? basename($absolutePath));
        if (! $upload['success']) {
            return ['success' => false, 'error' => $upload['error'] ?? 'Media upload failed'];
        }

        return $this->sendMediaById($account, $to, 'document', $upload['media_id'], $caption, $filename);
    }

    /**
     * @return array{success: bool, message_id?: string, error?: string}
     */
    protected function sendMedia(
        WhatsAppAccount $account,
        string $to,
        string $type,
        string $link,
        ?string $caption = null,
        ?string $filename = null
    ): array {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $url = $this->graphUrl() . '/' . $account->phone_number_id . '/messages';

        $mediaPayload = ['link' => $link];
        if ($caption !== null && $caption !== '') {
            $mediaPayload['caption'] = mb_substr($caption, 0, 1024);
        }
        if ($type === 'document' && $filename !== null && $filename !== '') {
            $mediaPayload['filename'] = $filename;
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $mediaPayload,
        ];

        $response = Http::withToken($account->access_token)
            ->timeout(25)
            ->post($url, $body);

        if ($response->successful()) {
            $data = $response->json();
            $messageId = $data['messages'][0]['id'] ?? null;
            return ['success' => true, 'message_id' => $messageId];
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        Log::warning('WhatsApp send media failed', [
            'phone_number_id' => $account->phone_number_id,
            'to' => $to,
            'type' => $type,
            'status' => $response->status(),
            'error' => $errorMessage,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Upload media file to Meta Cloud API and return media_id.
     *
     * @return array{success: bool, media_id?: string, error?: string}
     */
    protected function uploadMediaFile(
        WhatsAppAccount $account,
        string $absolutePath,
        ?string $mimeType = null,
        ?string $filename = null
    ): array {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['success' => false, 'error' => 'Attachment file not readable'];
        }

        $url = $this->graphUrl() . '/' . $account->phone_number_id . '/media';
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            return ['success' => false, 'error' => 'Unable to open attachment file'];
        }

        $name = $filename ?: basename($absolutePath);
        $type = $mimeType ?: 'application/octet-stream';

        $response = Http::withToken($account->access_token)
            ->timeout(40)
            ->attach('file', $stream, $name, ['Content-Type' => $type])
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $type,
            ]);

        fclose($stream);

        if ($response->successful()) {
            $mediaId = $response->json('id');
            if ($mediaId) {
                return ['success' => true, 'media_id' => $mediaId];
            }
            return ['success' => false, 'error' => 'Media upload response missing id'];
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        Log::warning('WhatsApp media upload failed', [
            'phone_number_id' => $account->phone_number_id,
            'filename' => $name,
            'status' => $response->status(),
            'error' => $errorMessage,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Send media message by media_id.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    protected function sendMediaById(
        WhatsAppAccount $account,
        string $to,
        string $type,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null
    ): array {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $url = $this->graphUrl() . '/' . $account->phone_number_id . '/messages';

        $mediaPayload = ['id' => $mediaId];
        if ($caption !== null && $caption !== '') {
            $mediaPayload['caption'] = mb_substr($caption, 0, 1024);
        }
        if ($type === 'document' && $filename !== null && $filename !== '') {
            $mediaPayload['filename'] = $filename;
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $mediaPayload,
        ];

        $response = Http::withToken($account->access_token)
            ->timeout(25)
            ->post($url, $body);

        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');
            return ['success' => true, 'message_id' => $messageId];
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        Log::warning('WhatsApp send media by id failed', [
            'phone_number_id' => $account->phone_number_id,
            'to' => $to,
            'type' => $type,
            'status' => $response->status(),
            'error' => $errorMessage,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }
}
