<?php

namespace Tests\Unit;

use App\Models\AttributionLink;
use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Models\SocialPost;
use App\Services\Growth\AttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_referral_from_whatsapp_message(): void
    {
        $svc = app(AttributionService::class);
        $slug = 'abc12345';
        $text = "Hi! I'm interested. (ref:{$slug})";

        $this->assertSame($slug, $svc->parseReferralFromMessage($text));
        $this->assertNull($svc->parseReferralFromMessage('hello without code'));
    }

    public function test_click_and_whatsapp_attribution_chain(): void
    {
        $company = Company::create(['name' => 'Growth Co', 'email' => 'growth@test.local']);
        $post = SocialPost::create([
            'company_id' => $company->id,
            'platform' => 'facebook',
            'content' => 'Test post',
            'status' => 'published',
        ]);

        $svc = app(AttributionService::class);
        $link = $svc->createLinkForPost($post, '254712345678');
        $this->assertNotEmpty($link->slug);

        $svc->recordClick($link);
        $this->assertSame(1, $link->fresh()->click_count);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Jane',
            'customer_phone' => '254700000001',
            'last_message' => 'hi',
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        $svc->attachReferralToChat($chat, $link->slug);
        $chat->refresh();
        $this->assertSame($link->id, $chat->attribution_link_id);
        $this->assertSame($post->id, $chat->social_post_id);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-TEST01',
            'customer_name' => 'Jane',
            'customer_phone' => '254700000001',
            'total' => 1500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $svc->recordOrder($order);
        $order->refresh();
        $this->assertSame($post->id, $order->social_post_id);
    }
}
