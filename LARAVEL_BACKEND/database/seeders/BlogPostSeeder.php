<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        if (BlogPost::where('slug', 'sell-more-on-whatsapp-with-ai')->exists()) {
            return;
        }

        BlogPost::create([
            'title' => 'How to sell more on WhatsApp with AI',
            'slug' => 'sell-more-on-whatsapp-with-ai',
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
            'meta_title' => 'How to sell more on WhatsApp with AI — RelayIQ',
            'meta_description' => 'Practical tips to automate WhatsApp sales with AI while keeping human agents in control.',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}
