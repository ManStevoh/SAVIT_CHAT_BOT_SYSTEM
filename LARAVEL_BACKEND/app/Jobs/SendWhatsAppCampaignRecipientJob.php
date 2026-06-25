<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaignRecipient;
use App\Services\WhatsApp\WhatsAppCampaignDispatchService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppCampaignRecipientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $recipientId,
    ) {}

    public function handle(
        WhatsAppMessageSenderService $sender,
        WhatsAppCampaignDispatchService $dispatch,
    ): void {
        $recipient = WhatsAppCampaignRecipient::with(['campaign.company.whatsappAccount'])->find($this->recipientId);
        if (! $recipient || $recipient->status !== WhatsAppCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $campaign = $recipient->campaign;
        $company = $campaign?->company;
        $account = $company?->whatsappAccount;

        if (! $campaign || ! $account || ! $account->isActive()) {
            $recipient->update([
                'status' => WhatsAppCampaignRecipient::STATUS_FAILED,
                'error_message' => 'WhatsApp account unavailable',
            ]);
            if ($campaign) {
                $dispatch->finalizeIfComplete($campaign);
            }

            return;
        }

        $posterUrl = $dispatch->absolutePublicUrl($campaign->poster_url);
        $bodyParams = is_array($campaign->body_parameters) ? $campaign->body_parameters : [];
        if ($bodyParams === [] && $campaign->caption) {
            $bodyParams = [mb_substr($campaign->caption, 0, 1024)];
        }

        $result = $sender->sendTemplate(
            $account,
            $recipient->customer_phone,
            (string) $campaign->template_name,
            $campaign->language_code ?? 'en',
            $bodyParams,
            $posterUrl,
        );

        if ($result['success']) {
            $recipient->update([
                'status' => WhatsAppCampaignRecipient::STATUS_SENT,
                'whatsapp_message_id' => $result['message_id'] ?? null,
                'sent_at' => now(),
            ]);
        } else {
            $recipient->update([
                'status' => WhatsAppCampaignRecipient::STATUS_FAILED,
                'error_message' => mb_substr($result['error'] ?? 'Send failed', 0, 500),
            ]);
        }

        $dispatch->finalizeIfComplete($campaign);
    }
}
