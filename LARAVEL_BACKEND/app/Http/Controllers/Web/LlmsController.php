<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PublicMarketingPages;
use Illuminate\Http\Response;

class LlmsController extends Controller
{
    public function summary(): Response
    {
        $base = rtrim((string) config('app.url', 'https://relayiq.app'), '/');

        $resources = [
            "- Main Website: {$base}/",
        ];
        if (PublicMarketingPages::enabled('solutions')) {
            $resources[] = "- Solutions: {$base}/solutions";
        }
        $resources = array_merge($resources, [
            "- Kenya: {$base}/ai-sales-agent-kenya",
            "- Kenya WhatsApp + M-Pesa playbook: {$base}/case-study/kenya-whatsapp-mpesa",
            "- M-Pesa: {$base}/whatsapp-mpesa",
            "- AI sales agent: {$base}/whatsapp-ai-sales-agent",
            "- Pricing: {$base}/pricing",
        ]);
        if (PublicMarketingPages::enabled('blog')) {
            $resources[] = "- Blog & Guides: {$base}/blog";
        }
        $resources[] = "- Full Knowledge Standard: {$base}/llms-full.txt";

        $content = implode("\n", [
            '# RelayIQ',
            '',
            '> The AI WhatsApp Commerce, Conversational Sales & Omnichannel Order Platform.',
            '',
            '## Overview',
            'RelayIQ is an all-in-one conversational commerce OS that transforms WhatsApp into a primary sales and customer service channel for online merchants, restaurants, and service businesses.',
            '',
            '## Core Capabilities',
            '- **WhatsApp AI Sales Agents**: Natural language catalog browsing, intent recognition, upsells, and instant cart generation.',
            '- **Omnichannel Storefronts**: Fast mobile web catalogs with custom domain support, real-time inventory, and variant selections.',
            '- **Automated Payments**: In-chat payments with M-Pesa STK push, Stripe, Paystack, Flutterwave, and Pesapal.',
            '- **Appointment Bookings**: Online service scheduling with slot management and automated WhatsApp reminders.',
            '- **Dine-In QR Table Ordering**: Contactless restaurant ordering and automated kitchen tickets.',
            '- **Growth Pilot**: Automated abandoned cart recovery, customer winback, and Meta/Google ad attribution.',
            '',
            '## Key Resources',
            ...$resources,
            '',
        ]);

        return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function full(): Response
    {
        $base = rtrim((string) config('app.url', 'https://relayiq.app'), '/');

        $nav = [
            "- Home: {$base}/",
        ];
        if (PublicMarketingPages::enabled('solutions')) {
            $nav[] = "- Solutions: {$base}/solutions";
        }
        $nav = array_merge($nav, [
            "- Kenya: {$base}/ai-sales-agent-kenya",
            "- Kenya WhatsApp + M-Pesa playbook: {$base}/case-study/kenya-whatsapp-mpesa",
            "- M-Pesa: {$base}/whatsapp-mpesa",
            "- AI sales agent: {$base}/whatsapp-ai-sales-agent",
            "- Chatbot vs agent: {$base}/whatsapp-chatbot-vs-ai-sales-agent",
            "- Pricing: {$base}/pricing",
            "- About: {$base}/about",
            "- Contact: {$base}/contact",
        ]);
        if (PublicMarketingPages::enabled('blog')) {
            $nav[] = "- Blog: {$base}/blog";
        }
        $nav = array_merge($nav, [
            "- Privacy: {$base}/privacy",
            "- Terms: {$base}/terms",
        ]);

        $content = implode("\n", [
            '# RelayIQ Complete Knowledge Specification',
            '',
            '## Platform Summary',
            'RelayIQ is a comprehensive enterprise conversational commerce platform built on Laravel and React (Inertia.js), designed for high-throughput WhatsApp interactions, transactional checkouts, and AI agent orchestration.',
            '',
            '## Architecture & Capabilities',
            '### 1. Conversational Commerce Pipeline',
            'RelayIQ processes inbound WhatsApp messages via Meta Cloud API webhooks with HMAC-SHA256 signature verification. State machines transition conversations across intent classification, catalog queries, slot booking, cart building, and payment collection.',
            '',
            '### 2. Payment Integrations',
            '- M-Pesa Daraja STK Push & C2B confirmation',
            '- Stripe Checkout & Elements',
            '- Paystack Standard & Inline',
            '- Flutterwave Payments',
            '- Pesapal 3.0 IPN',
            '',
            '### 3. Multi-Tenant Architecture',
            'Every merchant tenant operates with isolated catalog management, delivery zones, tax rules, and custom domain hosting (`https://shop.merchant.com`).',
            '',
            '## Public Navigation',
            ...$nav,
            '',
        ]);

        return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
