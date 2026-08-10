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
        $ctaButtonText = $message->ctaButtonText ?? $message->extra['cta_button_text'] ?? 'Shop Online';
        $imageUrl = $message->extra['image_url'] ?? $message->extra['media_url'] ?? null;
        $res = ['success' => false];

        if (! empty($ctaUrl) && filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
            $res = $this->senderService->sendInteractiveCtaUrl(
                $account,
                $message->recipientId,
                $message->content,
                $ctaButtonText,
                $ctaUrl
            );
        } elseif (! empty($imageUrl) && filter_var($imageUrl, FILTER_VALIDATE_URL) && ! str_contains($imageUrl, 'localhost') && ! str_contains($imageUrl, '127.0.0.1')) {
            $res = $this->senderService->sendImage(
                $account,
                $message->recipientId,
                $imageUrl,
                $message->content
            );
        }

        // Automatic Fallback: If interactive/image send failed or wasn't applicable, send as text!
        if (empty($res['success'])) {
            $res = $this->senderService->sendText(
                $account,
                $message->recipientId,
                $message->content
            );
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
}
