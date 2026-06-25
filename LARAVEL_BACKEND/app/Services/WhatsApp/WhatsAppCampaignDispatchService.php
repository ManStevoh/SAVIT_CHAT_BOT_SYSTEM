<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppCampaignRecipientJob;
use App\Models\Company;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Support\Facades\URL;

final class WhatsAppCampaignDispatchService
{
    public function __construct(
        private WhatsAppCampaignSegmentService $segments,
    ) {}

    public function absolutePublicUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return URL::to($url);
    }

    /**
     * @return array{success: bool, message?: string, campaign?: WhatsAppCampaign}
     */
    public function dispatch(WhatsAppCampaign $campaign, Company $company): array
    {
        if (! $campaign->isEditable()) {
            return ['success' => false, 'message' => 'Campaign cannot be sent in its current state.'];
        }

        if (! WhatsAppCampaignLimitService::canCreateCampaign($company)) {
            return ['success' => false, 'message' => 'Monthly WhatsApp campaign limit reached. Upgrade your plan.'];
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return ['success' => false, 'message' => 'No active WhatsApp connection.'];
        }

        if (! $campaign->template_name) {
            return ['success' => false, 'message' => 'Select an approved Meta template.'];
        }

        $template = WhatsAppMessageTemplate::query()
            ->where('company_id', $company->id)
            ->where('name', $campaign->template_name)
            ->where('status', 'approved')
            ->first();

        if (! $template) {
            return ['success' => false, 'message' => 'Template not found or not approved by Meta.'];
        }

        $posterUrl = $this->absolutePublicUrl($campaign->poster_url);
        if ($posterUrl && ! $this->templateSupportsImageHeader($template)) {
            return [
                'success' => false,
                'message' => 'Selected template must have an IMAGE header for poster campaigns. Create one in Meta with an image header.',
            ];
        }

        $recipients = $this->segments->recipients($company, $campaign->segment);
        if ($recipients->isEmpty()) {
            return ['success' => false, 'message' => 'No customers match this segment.'];
        }

        $recipientLimit = WhatsAppCampaignLimitService::getRecipientsLimit($company);
        if ($recipients->count() > $recipientLimit) {
            return [
                'success' => false,
                'message' => "Segment has {$recipients->count()} customers but your plan allows {$recipientLimit} per campaign.",
            ];
        }

        $bodyParams = $campaign->body_parameters;
        if (! is_array($bodyParams) || $bodyParams === []) {
            $bodyParams = $campaign->caption ? [mb_substr($campaign->caption, 0, 1024)] : [];
        }

        $campaign->update([
            'body_parameters' => $bodyParams,
            'poster_url' => $posterUrl ?? $campaign->poster_url,
            'status' => WhatsAppCampaign::STATUS_SENDING,
            'total_recipients' => $recipients->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'completed_at' => null,
            'error_summary' => null,
        ]);

        $delayMs = max(0, (int) config('whatsapp.campaign.send_delay_ms', 1000));

        foreach ($recipients->values() as $index => $row) {
            $recipient = WhatsAppCampaignRecipient::create([
                'whatsapp_campaign_id' => $campaign->id,
                'customer_phone' => $row['phone'],
                'customer_name' => $row['name'],
                'status' => WhatsAppCampaignRecipient::STATUS_PENDING,
            ]);

            $job = new SendWhatsAppCampaignRecipientJob($recipient->id);
            if ($delayMs > 0 && $index > 0) {
                dispatch($job)->delay(now()->addMilliseconds($delayMs * $index));
            } else {
                dispatch($job);
            }
        }

        return ['success' => true, 'campaign' => $campaign->fresh(['recipients'])];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public function sendTest(WhatsAppCampaign $campaign, Company $company, string $testPhone): array
    {
        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return ['success' => false, 'message' => 'No active WhatsApp connection.'];
        }

        if (! $campaign->template_name) {
            return ['success' => false, 'message' => 'Select a template first.'];
        }

        $posterUrl = $this->absolutePublicUrl($campaign->poster_url);
        $bodyParams = $campaign->body_parameters;
        if (! is_array($bodyParams) || $bodyParams === []) {
            $bodyParams = $campaign->caption ? [mb_substr($campaign->caption, 0, 1024)] : [];
        }

        $result = app(\App\Services\WhatsAppMessageSenderService::class)->sendTemplate(
            $account,
            $testPhone,
            $campaign->template_name,
            $campaign->language_code ?? 'en',
            $bodyParams,
            $posterUrl,
        );

        if (! $result['success']) {
            return ['success' => false, 'message' => $result['error'] ?? 'Test send failed'];
        }

        return ['success' => true, 'message' => 'Test message sent.'];
    }

    public function templateSupportsImageHeader(WhatsAppMessageTemplate $template): bool
    {
        foreach ($template->components ?? [] as $component) {
            if (($component['type'] ?? '') === 'HEADER' && strtoupper($component['format'] ?? '') === 'IMAGE') {
                return true;
            }
            if (($component['type'] ?? '') === 'header' && strtoupper($component['format'] ?? '') === 'IMAGE') {
                return true;
            }
        }

        return false;
    }

    public function finalizeIfComplete(WhatsAppCampaign $campaign): void
    {
        $campaign->refresh();
        if ($campaign->status !== WhatsAppCampaign::STATUS_SENDING) {
            return;
        }

        $pending = $campaign->recipients()->where('status', WhatsAppCampaignRecipient::STATUS_PENDING)->count();
        if ($pending > 0) {
            return;
        }

        $failed = $campaign->recipients()->where('status', WhatsAppCampaignRecipient::STATUS_FAILED)->count();
        $sent = $campaign->recipients()->where('status', WhatsAppCampaignRecipient::STATUS_SENT)->count();

        $errors = $campaign->recipients()
            ->where('status', WhatsAppCampaignRecipient::STATUS_FAILED)
            ->whereNotNull('error_message')
            ->limit(3)
            ->pluck('error_message')
            ->all();

        $campaign->update([
            'status' => $sent > 0 ? WhatsAppCampaign::STATUS_COMPLETED : WhatsAppCampaign::STATUS_FAILED,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'completed_at' => now(),
            'error_summary' => $errors !== [] ? implode(' | ', $errors) : null,
        ]);
    }
}
