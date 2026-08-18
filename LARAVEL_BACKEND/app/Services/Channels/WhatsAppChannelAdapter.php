<?php

namespace App\Services\Channels;

use App\Contracts\ChannelAdapterInterface;
use App\DTOs\InboundEnvelope;
use App\DTOs\OutboundMessage;
use App\Services\WhatsAppMessageSenderService;

final class WhatsAppChannelAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private WhatsAppMessageSenderService $senderService,
    ) {}

    public function channelName(): string
    {
        return 'whatsapp';
    }

    public function normalizeInbound(mixed $payload, int $companyId): InboundEnvelope
    {
        if (is_array($payload)) {
            $sender = (string) ($payload['customer_phone'] ?? $payload['from'] ?? '');
            $text = (string) ($payload['message_text'] ?? $payload['text'] ?? '');
            $name = isset($payload['customer_name']) ? (string) $payload['customer_name'] : null;
            $msgId = isset($payload['whatsapp_message_id']) ? (string) $payload['whatsapp_message_id'] : null;

            return new InboundEnvelope(
                channelType: 'whatsapp',
                externalSenderId: $sender,
                companyId: $companyId,
                messageText: $text,
                senderName: $name,
                metadata: $payload,
                whatsappMessageId: $msgId
            );
        }

        return new InboundEnvelope(
            channelType: 'whatsapp',
            externalSenderId: '',
            companyId: $companyId,
            messageText: (string) $payload
        );
    }

    public function sendOutbound(OutboundMessage $message): bool
    {
        $company = \App\Models\Company::find($message->companyId);
        if (! $company) {
            return false;
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            \App\Services\WhatsApp\WhatsAppDebugLogger::warning('CHANNEL_ADAPTER_INACTIVE_ACCOUNT', [
                'company_id' => $company->id,
                'recipient' => $message->recipientId,
            ]);
            return false;
        }

        $ctaUrl = $message->ctaUrl ?? $message->extra['cta_url'] ?? null;
        $ctaButtonText = $message->ctaButtonText ?? $message->extra['cta_button_text'] ?? null;
        $imageUrl = $message->extra['image_url'] ?? $message->extra['media_url'] ?? null;
        $messageText = trim(preg_replace('/\[IMAGE_URL:\s*([^\s\]]+)(?:\s+CAPTION:\s*([^\]]+))?\]/i', '', $message->content));
        $isImageValid = false;

        $extractedImages = [];
        if (preg_match_all('/\[IMAGE_URL:\s*([^\s\]]+)(?:\s+CAPTION:\s*([^\]]+))?\]/i', $message->content, $imgMatches, PREG_SET_ORDER)) {
            foreach ($imgMatches as $m) {
                $candidateUrl = trim($m[1]);
                $caption = isset($m[2]) ? trim($m[2]) : null;
                if ($this->isMediaUrlReachable($candidateUrl)) {
                    $extractedImages[] = [
                        'url' => $candidateUrl,
                        'caption' => $caption,
                    ];
                }
            }
        } elseif (! empty($imageUrl) && $this->isMediaUrlReachable($imageUrl)) {
            $extractedImages[] = [
                'url' => $imageUrl,
                'caption' => null,
            ];
        }

        $res = ['success' => false];

        if (empty($ctaUrl) && preg_match('~(https?://[^\s]+(?:/pay/|/invoice/|receipt|/orders/|/s/|pesapaliframe)[^\s]*)~i', $message->content, $m)) {
            $ctaUrl = trim($m[1], "().,;[]");
        }

        if (! empty($ctaUrl) && filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
            if (empty($ctaButtonText)) {
                $lowerUrl = strtolower($ctaUrl);
                if (str_contains($lowerUrl, 'pesapal')) {
                    $ctaButtonText = 'Pay via Pesapal';
                } elseif (str_contains($lowerUrl, 'paystack')) {
                    $ctaButtonText = 'Pay via Paystack';
                } elseif (str_contains($lowerUrl, 'stripe')) {
                    $ctaButtonText = 'Pay via Stripe';
                } elseif (str_contains($lowerUrl, 'flutterwave')) {
                    $ctaButtonText = 'Pay via Flutterwave';
                } elseif (str_contains($lowerUrl, '/pay/')) {
                    $ctaButtonText = 'Pay Online';
                } elseif (str_contains($lowerUrl, '/invoice/')) {
                    $ctaButtonText = 'View Invoice';
                } elseif (str_contains($lowerUrl, 'receipt')) {
                    $ctaButtonText = 'View Receipt';
                } elseif (str_contains($lowerUrl, '/cart')) {
                    $ctaButtonText = 'View Cart';
                } elseif (str_contains($lowerUrl, '/track')) {
                    $ctaButtonText = 'Track Order';
                } else {
                    $ctaButtonText = 'Shop Online';
                }
            }
        }

        if (! empty($extractedImages) && count($extractedImages) === 1 && empty($ctaUrl)) {
            $img = $extractedImages[0];
            $captionText = $img['caption'] ?? $messageText;
            if (empty($img['caption']) && strlen($messageText) > 1000) {
                $captionText = mb_substr($messageText, 0, 995) . '...';
            }

            $res = $this->senderService->sendImage(
                $account,
                $message->recipientId,
                $img['url'],
                $captionText
            );

            if (empty($res['success'])) {
                $res = $this->senderService->sendText(
                    $account,
                    $message->recipientId,
                    $messageText
                );
            }
        } else {
            if (! empty($ctaUrl) && filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
                $res = $this->senderService->sendInteractiveCtaUrl(
                    $account,
                    $message->recipientId,
                    $messageText,
                    $ctaButtonText,
                    $ctaUrl
                );
            } else {
                $res = $this->senderService->sendText(
                    $account,
                    $message->recipientId,
                    $messageText
                );
            }

            if (! empty($extractedImages) && (count($extractedImages) > 1 || ! empty($ctaUrl))) {
                foreach ($extractedImages as $img) {
                    $this->senderService->sendImage(
                        $account,
                        $message->recipientId,
                        $img['url'],
                        $img['caption'] ?? null
                    );
                }
            }
        }

        if (! empty($res['success'])) {
            $customerPhone = preg_replace('/\D/', '', $message->recipientId);
            $chat = \App\Models\Chat::where('company_id', $company->id)
                ->where('customer_phone', $customerPhone)
                ->first();

            if (! $chat) {
                $chat = \App\Models\Chat::where('company_id', $company->id)
                    ->orderByDesc('id')
                    ->first();
            }

            if ($chat) {
                \App\Models\Message::create([
                    'chat_id' => $chat->id,
                    'content' => $message->content,
                    'sender' => 'bot',
                    'reply_source' => $message->responseSpec ?? 'conversational_os',
                    'status' => 'sent',
                    'whatsapp_message_id' => $res['message_id'] ?? null,
                ]);

                $chat->update([
                    'last_message' => $message->content,
                    'last_message_at' => now(),
                    'ai_handled' => true,
                ]);
            }
        } else {
            \App\Services\WhatsApp\WhatsAppDebugLogger::error('CHANNEL_ADAPTER_SEND_FAILED', [
                'company_id' => $company->id,
                'recipient' => $message->recipientId,
                'error' => $res['error'] ?? 'Unknown send error',
            ]);
        }

        return (bool) ($res['success'] ?? false);
    }

    private function isMediaUrlReachable(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
            return false;
        }

        $cacheKey = 'media_url_status_' . md5($url);
        return (bool) \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($url) {
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'WhatsApp/2.0');
                curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                return $statusCode >= 200 && $statusCode < 400;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }
}
