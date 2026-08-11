<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $whatsappCover = '/images/blog/whatsapp-ai-sales.jpg';
        $catalogCover = '/images/blog/catalog-storefront.jpg';

        BlogPost::updateOrCreate(
            ['slug' => 'sell-more-on-whatsapp-with-ai'],
            [
                'title' => 'How to sell more on WhatsApp with AI',
                'excerpt' => 'A practical playbook for automating replies, capturing orders, and keeping humans in control.',
                'body' => <<<'HTML'
<p>WhatsApp is where your customers already are. The challenge is responding fast enough without burning out your team.</p>
<h2>Start with clear FAQs</h2>
<p>Load the questions you answer every day. AI can handle those instantly while your team focuses on high-value conversations.</p>
<h2>Make checkout happen in-chat</h2>
<p>When customers can pay with M-Pesa or card inside the thread, drop-off falls and conversion rises.</p>
<h2>Keep humans in the loop</h2>
<p>The best systems are hybrid: AI for speed, people for trust. RelayIQ is built for that balance.</p>
<p><a href="/pricing">See RelayIQ pricing</a> to start automating WhatsApp sales.</p>
HTML,
                'cover_image' => $whatsappCover,
                'meta_title' => 'How to sell more on WhatsApp with AI — RelayIQ',
                'meta_description' => 'Practical tips to automate WhatsApp sales with AI while keeping human agents in control. Orders, payments, and team inbox in one flow.',
                'og_image' => $whatsappCover,
                'is_published' => true,
                'published_at' => now()->subDays(17),
            ]
        );

        BlogPost::updateOrCreate(
            ['slug' => 'whatsapp-storefront-bookings-dine-in'],
            [
                'title' => 'One catalog for WhatsApp, storefront, bookings & dine-in',
                'excerpt' => 'Why running chat, web shop, appointments, and table QR from one inventory beats stitching five tools together.',
                'body' => <<<'HTML'
<p>Most teams bolt a chatbot onto WhatsApp, then buy a separate shop, booking tool, and QR menu. Customers get inconsistent answers — and you maintain three catalogs.</p>
<h2>One catalog, three front doors</h2>
<p>RelayIQ keeps physical products, digital goods, and services in one place. The same SKUs power WhatsApp, your storefront, and dine-in QR tables.</p>
<h2>Match the channel to the moment</h2>
<ul>
<li>WhatsApp for conversation and M-Pesa checkout</li>
<li>Storefront for browser shoppers and coupons</li>
<li>Bookings for services that need a calendar slot</li>
<li>Dine-in QR when guests are already at the table</li>
</ul>
<h2>Start simple, unlock more on Growth</h2>
<p>Starter covers AI chat, physical &amp; digital catalog, and storefront. Growth adds bookings and dine-in when you are ready. <a href="/pricing">Compare plans</a>.</p>
HTML,
                'cover_image' => $catalogCover,
                'meta_title' => 'WhatsApp + storefront + bookings + dine-in — RelayIQ',
                'meta_description' => 'Run WhatsApp sales, a web storefront, service bookings, and dine-in QR from one RelayIQ catalog.',
                'og_image' => $catalogCover,
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ]
        );

        BlogPost::updateOrCreate(
            ['slug' => 'mpesa-checkout-on-whatsapp'],
            [
                'title' => 'Accept M-Pesa payments on WhatsApp without leaving the chat',
                'excerpt' => 'How in-chat M-Pesa checkout reduces cart abandonment for East African WhatsApp sellers.',
                'body' => <<<'HTML'
<p>Customers who have to leave WhatsApp to pay often never come back. In-chat M-Pesa keeps momentum.</p>
<h2>Why M-Pesa inside WhatsApp converts</h2>
<p>Shoppers already trust STK push. When your AI agent can send a payment request in the same thread as the product recommendation, friction drops.</p>
<h2>What to set up</h2>
<ol>
<li>Connect your M-Pesa business credentials in RelayIQ settings</li>
<li>Enable order payments for WhatsApp and storefront</li>
<li>Let the agent confirm stock, then trigger checkout</li>
</ol>
<h2>Measure recovery, not just sales</h2>
<p>Combine M-Pesa checkout with abandoned-cart reminders to recover nearly-converted buyers. <a href="/solutions">Explore RelayIQ solutions</a>.</p>
HTML,
                'cover_image' => $whatsappCover,
                'meta_title' => 'M-Pesa checkout on WhatsApp — RelayIQ',
                'meta_description' => 'Accept M-Pesa payments inside WhatsApp chats. Reduce drop-off with STK push checkout and automated order confirmation.',
                'og_image' => $whatsappCover,
                'is_published' => true,
                'published_at' => now()->subDays(12),
            ]
        );

        BlogPost::updateOrCreate(
            ['slug' => 'ai-whatsapp-order-automation'],
            [
                'title' => 'AI WhatsApp order automation for growing teams',
                'excerpt' => 'Turn product questions into paid orders with AI that knows your catalog, stock, and payment options.',
                'body' => <<<'HTML'
<p>Manual WhatsApp selling does not scale. AI order automation does — when it is grounded in your real inventory.</p>
<h2>Catalog-aware replies</h2>
<p>RelayIQ’s agent searches your live products, variants, and prices so customers get accurate answers instead of generic chatbot filler.</p>
<h2>From intent to order</h2>
<p>When a buyer is ready, the flow captures name, delivery details, and payment preference without forcing a separate app.</p>
<h2>Human takeover when it matters</h2>
<p>Escalate VIP or complex deals to your inbox while AI handles FAQs and routine reorders. <a href="/register">Start free on RelayIQ</a>.</p>
HTML,
                'cover_image' => $catalogCover,
                'meta_title' => 'AI WhatsApp order automation — RelayIQ',
                'meta_description' => 'Automate WhatsApp product Q&A, carts, and checkout with an AI agent connected to your real catalog and payments.',
                'og_image' => $catalogCover,
                'is_published' => true,
                'published_at' => now()->subDays(3),
            ]
        );

        BlogPost::updateOrCreate(
            ['slug' => 'whatsapp-commerce-vs-online-store'],
            [
                'title' => 'WhatsApp commerce vs online store: when to use both',
                'excerpt' => 'Conversation commerce and a web storefront win different moments — here’s how to run both from one system.',
                'body' => <<<'HTML'
<p>WhatsApp wins for trust and speed. A storefront wins for browsing, SEO, and sharing product links.</p>
<h2>Use WhatsApp when</h2>
<ul>
<li>Buyers need advice before they decide</li>
<li>M-Pesa or COD is the preferred payment</li>
<li>You sell via Instagram/Facebook DMs that move to chat</li>
</ul>
<h2>Use a storefront when</h2>
<ul>
<li>You want Google and social traffic to land on product pages</li>
<li>Shoppers prefer self-serve browsing</li>
<li>You run coupons, collections, and SEO landing pages</li>
</ul>
<p>RelayIQ keeps one catalog for both channels so prices and stock never drift. <a href="/pricing">Pick a plan</a>.</p>
HTML,
                'cover_image' => $whatsappCover,
                'meta_title' => 'WhatsApp commerce vs online store — RelayIQ',
                'meta_description' => 'Learn when WhatsApp commerce outperforms a web store — and how to run both from one RelayIQ catalog.',
                'og_image' => $whatsappCover,
                'is_published' => true,
                'published_at' => now()->subDay(),
            ]
        );
    }
}
