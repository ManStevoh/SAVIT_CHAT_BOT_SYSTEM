<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\AttributionLink;
use App\Models\Company;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\WhatsAppAccount;

class GrowthOnboardingService
{
    /**
     * @return array{
     *   steps: array<int, array{key: string, label: string, description: string, completed: bool, actionTab?: string}>,
     *   completedCount: int,
     *   totalCount: int,
     *   percentComplete: int,
     *   isComplete: bool,
     *   firstAttributedSaleAt: ?string
     * }
     */
    public function checklist(Company $company): array
    {
        $companyId = (int) $company->id;

        $whatsappConnected = WhatsAppAccount::where('company_id', $companyId)
            ->where('status', 'active')
            ->exists();

        $metaConnected = SocialAccount::where('company_id', $companyId)
            ->whereIn('platform', ['facebook', 'instagram'])
            ->where('status', 'connected')
            ->exists();

        $hasPost = SocialPost::where('company_id', $companyId)->exists();

        $hasClick = AttributionEvent::where('company_id', $companyId)
            ->where('event_type', 'click')
            ->exists()
            || AttributionLink::where('company_id', $companyId)->where('click_count', '>', 0)->exists();

        $hasLead = AttributionEvent::where('company_id', $companyId)
            ->whereIn('event_type', ['whatsapp_start', 'lead'])
            ->exists();

        $hasRevenue = $company->first_attributed_sale_at !== null
            || AttributionEvent::where('company_id', $companyId)->where('event_type', 'revenue')->exists();

        $steps = [
            [
                'key' => 'whatsapp',
                'label' => 'Connect WhatsApp',
                'description' => 'Link your business WhatsApp so customers can message you from social posts.',
                'completed' => $whatsappConnected,
                'actionTab' => 'platforms',
            ],
            [
                'key' => 'meta',
                'label' => 'Connect Meta (Facebook/Instagram)',
                'description' => 'Connect a Facebook Page to publish and track social content.',
                'completed' => $metaConnected,
                'actionTab' => 'platforms',
            ],
            [
                'key' => 'first_post',
                'label' => 'Generate your first post',
                'description' => 'Create AI content with a built-in tracking link.',
                'completed' => $hasPost,
                'actionTab' => 'content',
            ],
            [
                'key' => 'share_link',
                'label' => 'Share your tracking link',
                'description' => 'Post to social with the tracking URL — we count every click.',
                'completed' => $hasClick,
                'actionTab' => 'content',
            ],
            [
                'key' => 'first_lead',
                'label' => 'Get your first attributed lead',
                'description' => 'Someone clicks your link and messages you on WhatsApp.',
                'completed' => $hasLead,
                'actionTab' => 'overview',
            ],
            [
                'key' => 'first_sale',
                'label' => 'Close your first attributed sale',
                'description' => 'Revenue tied to a social post — your Growth loop is proven.',
                'completed' => $hasRevenue,
                'actionTab' => 'overview',
            ],
        ];

        $completedCount = collect($steps)->where('completed', true)->count();
        $totalCount = count($steps);

        return [
            'steps' => $steps,
            'completedCount' => $completedCount,
            'totalCount' => $totalCount,
            'percentComplete' => $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0,
            'isComplete' => $completedCount === $totalCount,
            'firstAttributedSaleAt' => $company->first_attributed_sale_at?->toIso8601String(),
        ];
    }
}
