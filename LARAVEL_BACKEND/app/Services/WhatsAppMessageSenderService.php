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
     * @param  string|null  $contextMessageId  Meta wamid to quote/reply to
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendText(
        WhatsAppAccount $account,
        string $to,
        string $text,
        ?string $contextMessageId = null,
    ): array {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $url = $this->graphUrl() . '/' . $account->phone_number_id . '/messages';

        $textPayload = [
            'body' => mb_substr($text, 0, 4096),
        ];
        if (preg_match('~https?://~i', $text)) {
            $textPayload['preview_url'] = true;
        }

        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => $textPayload,
        ];

        $contextMessageId = trim((string) $contextMessageId);
        if ($contextMessageId !== '') {
            $body['context'] = ['message_id' => $contextMessageId];
        }

        \App\Services\WhatsApp\WhatsAppDebugLogger::info('META_API_SEND_REQUEST', [
            'phone_number_id' => $account->phone_number_id,
            'to' => $to,
            'text_length' => strlen($text),
            'has_token' => filled($account->access_token),
            'context_message_id' => $contextMessageId,
        ]);

        $response = $this->postWithCPanelRetry($url, $account->access_token, $body, 15);

        if ($response->successful()) {
            $data = $response->json();
            $messageId = $data['messages'][0]['id'] ?? null;
            \App\Services\WhatsApp\WhatsAppDebugLogger::info('META_API_SEND_SUCCESS', [
                'phone_number_id' => $account->phone_number_id,
                'to' => $to,
                'whatsapp_message_id' => $messageId,
            ]);
            return ['success' => true, 'message_id' => $messageId];
        }

        $errorBody = $response->json();
        $errorMessage = $errorBody['error']['message'] ?? $response->body();
        \App\Services\WhatsApp\WhatsAppDebugLogger::error('META_API_SEND_FAILED', [
            'phone_number_id' => $account->phone_number_id,
            'to' => $to,
            'status' => $response->status(),
            'error' => $errorMessage,
            'response_body' => $errorBody,
        ]);
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
     * Send audio by uploading file to Meta and sending via media_id.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendAudioFile(
        WhatsAppAccount $account,
        string $to,
        string $absolutePath,
        ?string $mimeType = null,
    ): array {
        $upload = $this->uploadMediaFile($account, $absolutePath, $mimeType ?? 'audio/mpeg', basename($absolutePath));
        if (! $upload['success']) {
            return ['success' => false, 'error' => $upload['error'] ?? 'Audio upload failed'];
        }

        return $this->sendMediaById($account, $to, 'audio', $upload['media_id']);
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

    /**
     * Send an approved WhatsApp message template (marketing/utility).
     *
     * @param  array<int, string>  $bodyParameters
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendTemplate(
        WhatsAppAccount $account,
        string $to,
        string $templateName,
        string $languageCode = 'en',
        array $bodyParameters = [],
        ?string $headerImageUrl = null,
    ): array {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        $components = [];
        if ($headerImageUrl) {
            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'image',
                    'image' => ['link' => $headerImageUrl],
                ]],
            ];
        }
        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    fn (string $text) => ['type' => 'text', 'text' => mb_substr($text, 0, 1024)],
                    $bodyParameters,
                ),
            ];
        }
        if ($components !== []) {
            $template['components'] = $components;
        }

        $url = $this->graphUrl().'/'.$account->phone_number_id.'/messages';
        $response = Http::withToken($account->access_token)
            ->timeout(20)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => $template,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'message_id' => $response->json('messages.0.id')];
        }

        $errorMessage = $response->json('error.message') ?? $response->body();
        Log::warning('WhatsApp template send failed', [
            'template' => $templateName,
            'to' => $to,
            'error' => $errorMessage,
        ]);

        return ['success' => false, 'error' => $errorMessage];
    }

    /**
     * Send WhatsApp interactive CTA URL button message (e.g. for payments, invoices, storefront).
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendInteractiveCtaUrl(
        WhatsAppAccount $account,
        string $to,
        string $bodyText,
        string $buttonText,
        string $url,
        ?string $headerText = null,
        ?string $footerText = null,
    ): array {
        $to = preg_replace('/\D/', '', $to);
        if ($to === '') {
            return ['success' => false, 'error' => 'Invalid recipient phone number'];
        }

        $cleanBody = $this->cleanBodyTextForCta($bodyText, $url);

        $interactive = [
            'type' => 'cta_url',
            'body' => ['text' => mb_substr($cleanBody, 0, 1024)],
            'action' => [
                'name' => 'cta_url',
                'parameters' => [
                    'display_text' => mb_substr($buttonText, 0, 20),
                    'url' => $url,
                ],
            ],
        ];

        if ($headerText !== null && $headerText !== '') {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr($headerText, 0, 60)];
        }
        if ($footerText !== null && $footerText !== '') {
            $interactive['footer'] = ['text' => mb_substr($footerText, 0, 60)];
        }

        $endpointUrl = $this->graphUrl() . '/' . $account->phone_number_id . '/messages';
        $response = $this->postWithCPanelRetry($endpointUrl, $account->access_token, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ], 20);

        if ($response->successful()) {
            $messageId = $response->json('messages.0.id');
            return ['success' => true, 'message_id' => $messageId];
        }

        $errorMessage = $response->json('error.message') ?? $response->body();
        Log::warning('WhatsApp interactive CTA URL send failed, falling back to text', [
            'to' => $to,
            'url' => $url,
            'error' => $errorMessage,
        ]);

        // Fallback to sending formatted text with preview_url
        $fallbackText = rtrim($bodyText) . "\n\n" . ($buttonText ? "🔗 *{$buttonText}:*\n" : "") . $url;
        return $this->sendText($account, $to, $fallbackText);
    }

    /**
     * Intelligently send a WhatsApp message, using interactive CTA URL buttons when links are detected,
     * or standard text with preview_url as fallback.
     *
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendSmartReply(WhatsAppAccount $account, string $to, string $text): array
    {
        if (preg_match('~(https?://[^\s]+(?:/pay/|/invoice/|/receipt|/orders/receipt|/s/)[^\s]*)~i', $text, $match)) {
            $url = trim($match[1], "().,;[]");
            $lowerUrl = strtolower($url);
            $buttonText = 'Shop Online';
            if (str_contains($lowerUrl, '/pay/')) {
                $buttonText = 'Pay Online';
            } elseif (str_contains($lowerUrl, '/invoice/')) {
                $buttonText = 'View Invoice';
            } elseif (str_contains($lowerUrl, '/receipt')) {
                $buttonText = 'View Receipt';
            } elseif (str_contains($lowerUrl, '/cart')) {
                $buttonText = 'View Cart';
            } elseif (str_contains($lowerUrl, '/track')) {
                $buttonText = 'Track Order';
            } elseif (str_contains($lowerUrl, '/s/')) {
                $buttonText = 'Shop Online';
            }

            $ctaResult = $this->sendInteractiveCtaUrl(
                $account,
                $to,
                $text,
                $buttonText,
                $url
            );

            if (! empty($ctaResult['success'])) {
                return $ctaResult;
            }
        }

        return $this->sendText($account, $to, $text);
    }

    /**
     * Outbound HTTP POST with native single-threaded PHP DNS fallback for cPanel libcurl limits.
     */
    protected function postWithCPanelRetry(string $url, string $token, array $body, int $timeoutSeconds = 20): \Illuminate\Http\Client\Response
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $port = ($parsedUrl['scheme'] ?? 'https') === 'https' ? 443 : 80;

        $curlOpts = [
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];

        try {
            return Http::withToken($token)
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'curl' => $curlOpts,
                ])
                ->timeout($timeoutSeconds)
                ->post($url, $body);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            if (! str_contains($e->getMessage(), 'getaddrinfo') && ! str_contains($e->getMessage(), 'cURL error 6')) {
                throw $e;
            }

            // Perform single-threaded PHP DNS resolution to bypass cURL getaddrinfo worker thread spawning
            $ip = gethostbyname($host);
            if ($ip && $ip !== $host) {
                $curlOpts[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$ip}"];
            }

            return Http::withToken($token)
                ->withOptions([
                    'force_ip_resolve' => 'v4',
                    'curl' => $curlOpts,
                ])
                ->timeout($timeoutSeconds)
                ->post($url, $body);
        }
    }

    /**
     * Remove redundant raw links and URL label headers from message body text when native CTA URL button is present.
     */
    protected function cleanBodyTextForCta(string $text, string $targetUrl): string
    {
        $lines = explode("\n", $text);
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_contains($line, $targetUrl) || preg_match('~https?://~i', $line)) {
                continue;
            }
            if (preg_match('/^(?:📄\s*\*?(?:Invoice|Receipt|View Invoice|View Receipt)\*?:?|💳\s*\*?Pay Online\*?:?|Pay online:?|Invoice:?|View invoice \/ receipt:?|Instructions:\s*Pay online.*|Please complete payment here:?)$/iu', $trimmed)) {
                continue;
            }
            $filtered[] = $line;
        }

        $clean = implode("\n", $filtered);
        $clean = trim(preg_replace('/\n{3,}/', "\n\n", $clean));

        return $clean !== '' ? $clean : $text;
    }
}
