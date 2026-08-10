<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $cover = '/images/lando/lando-inbox.png';

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
HTML,
                'cover_image' => $cover,
                'meta_title' => 'How to sell more on WhatsApp with AI — RelayIQ',
                'meta_description' => 'Practical tips to automate WhatsApp sales with AI while keeping human agents in control. Orders, payments, and team inbox in one flow.',
                'og_image' => $cover,
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
<p>RelayIQ keeps physical products, digital goods, and services in one place. The same SKUs power WhatsApp, your <code>/s/{slug}</code> storefront, and dine-in QR tables.</p>
<h2>Match the channel to the moment</h2>
<ul>
<li>WhatsApp for conversation and M-Pesa checkout</li>
<li>Storefront for browser shoppers and coupons</li>
<li>Bookings for services that need a calendar slot</li>
<li>Dine-in QR when guests are already at the table</li>
</ul>
<h2>Start simple, unlock more on Growth</h2>
<p>Starter covers AI chat, physical &amp; digital catalog, and storefront. Growth adds bookings and dine-in when you are ready.</p>
HTML,
                'cover_image' => '/images/lando/lando-intro.png',
                'meta_title' => 'WhatsApp + storefront + bookings + dine-in — RelayIQ',
                'meta_description' => 'Run WhatsApp sales, a web storefront, service bookings, and dine-in QR from one RelayIQ catalog.',
                'og_image' => '/images/lando/lando-intro.png',
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ]
        );
    }
}
