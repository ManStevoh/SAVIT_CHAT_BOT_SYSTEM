<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\AttributionLink;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\SocialPost;
use App\Services\CompanyInAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributionService
{
    public function parseReferralFromMessage(string $text): ?string
    {
        $prefix = config('growth.referral_prefix', 'ref:');
        if (preg_match('/'.preg_quote($prefix, '/').'([a-zA-Z0-9]{6,16})\b/i', $text, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    public function recordClick(AttributionLink $link, ?Request $request = null): AttributionEvent
    {
        $link->increment('click_count');
        $post = $link->socialPost;

        return AttributionEvent::create([
            'company_id' => $link->company_id,
            'social_post_id' => $link->social_post_id,
            'attribution_link_id' => $link->id,
            'event_type' => 'click',
            'platform' => $post?->platform,
            'utm_source' => $post?->utm_source,
            'utm_medium' => $post?->utm_medium,
            'utm_campaign' => $post?->utm_campaign,
            'referrer' => $request?->header('Referer'),
            'ip_hash' => $this->hashValue($request?->ip()),
            'user_agent_hash' => $this->hashValue($request?->userAgent()),
        ]);
    }

    public function attachReferralToChat(Chat $chat, string $slug): ?AttributionLink
    {
        $link = AttributionLink::where('slug', $slug)
            ->where('company_id', $chat->company_id)
            ->first();

        if (! $link) {
            return null;
        }

        $updates = ['attribution_link_id' => $link->id];
        if ($link->social_post_id) {
            $updates['social_post_id'] = $link->social_post_id;
        }
        $chat->update($updates);

        AttributionEvent::create([
            'company_id' => $chat->company_id,
            'social_post_id' => $link->social_post_id,
            'attribution_link_id' => $link->id,
            'chat_id' => $chat->id,
            'event_type' => 'whatsapp_start',
            'platform' => $link->socialPost?->platform ?? 'whatsapp',
            'utm_source' => $link->socialPost?->utm_source,
            'utm_medium' => $link->socialPost?->utm_medium,
            'utm_campaign' => $link->socialPost?->utm_campaign,
        ]);

        return $link;
    }

    public function recordLead(Chat $chat): void
    {
        if (! $chat->social_post_id && ! $chat->attribution_link_id) {
            return;
        }

        $exists = AttributionEvent::where('chat_id', $chat->id)
            ->where('event_type', 'lead')
            ->exists();
        if ($exists) {
            return;
        }

        AttributionEvent::create([
            'company_id' => $chat->company_id,
            'social_post_id' => $chat->social_post_id,
            'attribution_link_id' => $chat->attribution_link_id,
            'chat_id' => $chat->id,
            'event_type' => 'lead',
            'platform' => $chat->socialPost?->platform,
        ]);
    }

    public function recordOrder(Order $order): void
    {
        $chat = $order->chat;
        $socialPostId = $order->social_post_id ?? $chat?->social_post_id;

        if (! $socialPostId && ! $chat?->attribution_link_id) {
            return;
        }

        if (! $order->social_post_id && $socialPostId) {
            $order->update(['social_post_id' => $socialPostId]);
        }

        AttributionEvent::create([
            'company_id' => $order->company_id,
            'social_post_id' => $socialPostId,
            'attribution_link_id' => $chat?->attribution_link_id,
            'chat_id' => $order->chat_id,
            'order_id' => $order->id,
            'event_type' => 'order',
            'platform' => $chat?->socialPost?->platform,
            'revenue' => $order->total,
        ]);

        AttributionEvent::create([
            'company_id' => $order->company_id,
            'social_post_id' => $socialPostId,
            'attribution_link_id' => $chat?->attribution_link_id,
            'chat_id' => $order->chat_id,
            'order_id' => $order->id,
            'event_type' => 'revenue',
            'platform' => $chat?->socialPost?->platform,
            'revenue' => $order->total,
        ]);

        $this->celebrateFirstAttributedSale($order->company, $order, $socialPostId);
    }

    protected function celebrateFirstAttributedSale(?Company $company, Order $order, ?int $socialPostId): void
    {
        if (! $company || ! $socialPostId) {
            return;
        }

        $priorRevenue = AttributionEvent::where('company_id', $company->id)
            ->where('event_type', 'revenue')
            ->where('order_id', '!=', $order->id)
            ->exists();

        if ($priorRevenue || $company->first_attributed_sale_at) {
            return;
        }

        $company->update(['first_attributed_sale_at' => now()]);

        $post = SocialPost::find($socialPostId);
        app(CompanyInAppNotificationService::class)->recordFirstAttributedSale(
            $company,
            $order,
            $post?->title ?? 'your social post'
        );
    }

    public function createLinkForPost(SocialPost $post, ?string $whatsappNumber = null): AttributionLink
    {
        $slug = $this->generateUniqueSlug();
        $refText = config('growth.referral_prefix', 'ref:').$slug;
        $prefill = "Hi! I'm interested. ({$refText})";

        $destination = $whatsappNumber
            ? 'https://wa.me/'.preg_replace('/\D/', '', $whatsappNumber).'?text='.rawurlencode($prefill)
            : rtrim(config('app.frontend_url', config('app.url')), '/');

        return AttributionLink::create([
            'company_id' => $post->company_id,
            'social_post_id' => $post->id,
            'slug' => $slug,
            'destination_url' => $destination,
            'whatsapp_prefill' => $prefill,
        ]);
    }

    public function trackingUrl(AttributionLink $link): string
    {
        return rtrim(config('app.url'), '/').'/g/'.$link->slug;
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower(Str::random(8));
        } while (AttributionLink::where('slug', $slug)->exists());

        return $slug;
    }

    private function hashValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash('sha256', $value.config('app.key'));
    }
}
