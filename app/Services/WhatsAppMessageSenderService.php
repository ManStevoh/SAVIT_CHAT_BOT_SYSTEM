<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageSenderService
{
    protected function graphUrl(): string
    {
        return config('whatsapp.graph_url', 'https://graph.facebook.com/v21.0');
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
}
