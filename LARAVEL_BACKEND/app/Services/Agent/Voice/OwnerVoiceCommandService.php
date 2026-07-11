<?php

namespace App\Services\Agent\Voice;

use App\Models\AgentActionRequest;
use App\Models\Chat;
use App\Models\Company;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\Agent\Owner\OwnerAnalyticsAgentService;
use App\Services\Agent\Platform\AgentApprovalService;

/**
 * Voice/text commands from company owner via WhatsApp → intent → draft actions / analytics.
 */
final class OwnerVoiceCommandService
{
    public function __construct(
        protected OwnerAnalyticsAgentService $analytics,
        protected AgentApprovalService $approval,
    ) {}

    public function isOwnerPhone(Company $company, string $phone): bool
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return false;
        }

        return User::query()
            ->where('company_id', $company->id)
            ->where('role', 'company_owner')
            ->whereNotNull('phone')
            ->get()
            ->contains(fn (User $u) => $this->normalizePhone((string) $u->phone) === $normalized);
    }

    /**
     * @return array{handled: bool, reply: ?string, approval_id?: int}
     */
    public function handle(Company $company, Chat $chat, string $transcript): array
    {
        $text = mb_strtolower(trim($transcript));
        if ($text === '') {
            return ['handled' => false, 'reply' => null];
        }

        if ($this->matchesAnalytics($text)) {
            $question = $this->extractAnalyticsQuestion($transcript);
            $inv = $this->analytics->investigate($company, $question, '30d');
            $summary = '';
            foreach ($inv->findings ?? [] as $f) {
                if (is_array($f) && ! empty($f['claim'])) {
                    $summary .= '• '.$f['claim']."\n";
                }
            }
            $recs = implode("\n", array_map(fn ($r) => '→ '.$r, $inv->recommendations ?? []));

            return [
                'handled' => true,
                'reply' => "Owner analytics (confidence ".round((float) $inv->confidence * 100)."%):\n\n"
                    .($summary !== '' ? $summary : 'No major issues detected.')
                    .($recs !== '' ? "\n\nRecommendations:\n".$recs : '')
                    ."\n\nFull report in Executive AI dashboard.",
            ];
        }

        if (preg_match('/refund\s+(?:order\s+)?([A-Z0-9\-]+)/i', $transcript, $m)) {
            $orderNumber = strtoupper($m[1]);
            $req = $this->approval->queue(
                (int) $company->id,
                (int) $chat->id,
                'issue_order_refund',
                'high',
                ['arguments' => ['order_number' => $orderNumber, 'reason' => 'Owner voice command']],
                'Owner requested refund via WhatsApp voice/text.',
            );

            return [
                'handled' => true,
                'reply' => "Refund draft for order {$orderNumber} is queued (approval #{$req->id}). "
                    .'Open Executive AI → Approvals and tap Approve to execute.',
                'approval_id' => $req->id,
            ];
        }

        if (preg_match('/send\s+campaign\s+(\d+)/i', $transcript, $m)
            || preg_match('/campaign\s+(\d+)/i', $transcript, $m)) {
            $campaignId = (int) $m[1];
            $campaign = WhatsAppCampaign::where('company_id', $company->id)->find($campaignId);
            if (! $campaign) {
                return ['handled' => true, 'reply' => "Campaign #{$campaignId} not found."];
            }

            $req = $this->approval->queue(
                (int) $company->id,
                (int) $chat->id,
                'send_whatsapp_campaign',
                'high',
                ['arguments' => ['campaign_id' => $campaignId]],
                "Owner requested send campaign: {$campaign->name}",
            );

            return [
                'handled' => true,
                'reply' => "Campaign \"{$campaign->name}\" queued for send (approval #{$req->id}). "
                    .'Approve in Executive AI dashboard to dispatch.',
                'approval_id' => $req->id,
            ];
        }

        if (str_contains($text, 'brief') || str_contains($text, 'morning')) {
            return [
                'handled' => true,
                'reply' => 'Your morning commerce brief is in Executive AI dashboard. '
                    .'Ask "why are sales down?" for a full investigation.',
            ];
        }

        return [
            'handled' => true,
            'reply' => "Owner command received. Try:\n"
                ."• \"Why are sales down?\"\n"
                ."• \"Refund order ORD-123\"\n"
                ."• \"Send campaign 5\"\n"
                .'Or open Executive AI in your dashboard.',
        ];
    }

    private function matchesAnalytics(string $text): bool
    {
        return str_contains($text, 'sales')
            || str_contains($text, 'revenue')
            || str_contains($text, 'why')
            || str_contains($text, 'down')
            || str_contains($text, 'analytics');
    }

    private function extractAnalyticsQuestion(string $transcript): string
    {
        return mb_substr(trim($transcript), 0, 500) ?: 'How is the business performing?';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '254'.$digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        return $digits;
    }
}
