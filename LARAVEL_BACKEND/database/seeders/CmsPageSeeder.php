<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\LandingFaq;
use App\Models\Testimonial;
use App\Support\BrandSocial;
use App\Support\FeaturesPageCopy;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $heroImage = '/images/lando/lando-hero.png?v=brand2';
        $introImage = '/images/lando/lando-intro.png';
        $storefrontImage = '/images/lando/lando-storefront.png?v=brand2';
        $stepsImage = '/images/lando/lando-steps.png';
        $ctaImage = '/images/lando/lando-cta.png';
        $bookingsImage = '/images/lando/lando-bookings.png?v=brand2';
        $dineinImage = '/images/lando/lando-dinein.png?v=brand2';
        $aboutTeamImage = '/images/lando/lando-about-team.png';
        $contactImage = '/images/lando/lando-contact.png';

        $pages = [
            [
                'slug' => 'global',
                'title' => 'Global',
                'meta_title' => null,
                'meta_description' => null,
                'sections' => [
                    [
                        'section_key' => 'navbar',
                        'label' => 'Navigation bar',
                        'sort_order' => 1,
                        'content' => [
                            'links' => [
                                ['label' => 'Home', 'href' => '/'],
                                ['label' => 'Features', 'href' => '/features'],
                                ['label' => 'Solutions', 'href' => '/solutions'],
                                ['label' => 'Pricing', 'href' => '/pricing'],
                                ['label' => 'About us', 'href' => '/about'],
                                ['label' => 'Blog', 'href' => '/blog'],
                                ['label' => 'Contact', 'href' => '/contact'],
                            ],
                            'loginLabel' => 'Log in',
                            'loginHref' => '/login',
                            'signupLabel' => 'Sign up',
                            'signupHref' => '/register',
                        ],
                    ],
                    [
                        'section_key' => 'auth_shell',
                        'label' => 'Auth pages shell',
                        'sort_order' => 3,
                        'content' => [
                            'imageUrl' => '/images/lando/lando-auth.jpg',
                            'imageAlt' => 'Kenyan cafe owner checking WhatsApp orders on his phone',
                        ],
                    ],
                    [
                        'section_key' => 'footer',
                        'label' => 'Footer',
                        'sort_order' => 4,
                        'content' => [
                            'copyright' => '© ' . date('Y') . ' Essem Digital Innovation Limited. All rights reserved.',
                            'navLinks' => [
                                ['label' => 'Home', 'href' => '/'],
                                ['label' => 'Features', 'href' => '/features'],
                                ['label' => 'AI Sales Agent', 'href' => '/whatsapp-ai-sales-agent'],
                                ['label' => 'WhatsApp Automation', 'href' => '/whatsapp-sales-automation'],
                                ['label' => 'Solutions', 'href' => '/solutions'],
                                ['label' => 'Pricing', 'href' => '/pricing'],
                                ['label' => 'Blog', 'href' => '/blog'],
                                ['label' => 'Contact', 'href' => '/contact'],
                            ],
                            'socialLinks' => BrandSocial::links(),
                            'legalLinks' => [
                                ['label' => 'Privacy Policy', 'href' => '/privacy'],
                                ['label' => 'Terms Of Service', 'href' => '/terms'],
                            ],
                            'showMobileApp' => true,
                            'mobileAppTitle' => 'iOS & Android apps',
                            'mobileAppDescription' => 'Launching soon — manage chats, orders, and growth on the go.',
                            'playStoreUrl' => '',
                            'appStoreUrl' => '',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'home',
                'title' => 'Home',
                'meta_title' => 'Free WhatsApp Storefront, Bookings & Dine-in QR | RelayIQ.app',
                'meta_description' => 'Start on RelayIQ’s free Starter plan: a WhatsApp-connected storefront, appointment bookings, and dine-in table QR. Sell in English and Kiswahili with M-Pesa on the number customers already message.',
                'og_title' => 'Free WhatsApp Storefront, Bookings & Dine-in QR | RelayIQ.app',
                'og_description' => 'Free forever Starter: WhatsApp storefront, bookings, and dine-in tables. Upgrade to Growth only when you need more.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'kicker' => 'FREE STARTER PLAN — FOREVER',
                            'title' => 'WhatsApp storefront, bookings, and dine-in QR — start free',
                            'description' => 'Every business gets Starter free forever: a storefront connected to WhatsApp, appointment bookings, and table QR ordering. 20 products and 5 tables included. No credit card. Upgrade to Growth only when you outgrow it.',
                            'primaryCtaText' => 'Get started free',
                            'primaryCtaHref' => '/register',
                            'secondaryCtaText' => 'See plans',
                            'secondaryCtaHref' => '/pricing',
                            'showFlowSimulation' => true,
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'RelayIQ WhatsApp storefront converting chats into orders',
                        ],
                    ],
                    [
                        'section_key' => 'capabilities',
                        'label' => 'Capabilities grid',
                        'sort_order' => 2,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Your AI sales agent for WhatsApp',
                            'description' => 'Automate WhatsApp sales without losing the human touch — catalog-aware replies, payments, follow-ups, and team handoff.',
                            'items' => [
                                ['icon' => 'bot', 'title' => 'Answer customers instantly', 'description' => 'AI replies grounded in your catalog, FAQs, and order history — not rigid numbered menus.'],
                                ['icon' => 'package', 'title' => 'Recommend products automatically', 'description' => 'Suggest physical goods, digital products, and bookable services from one live catalog.'],
                                ['icon' => 'payment', 'title' => 'Convert conversations into sales', 'description' => 'Collect M-Pesa, Paystack, or Stripe in the same chat thread where the sale started.'],
                                ['icon' => 'booking', 'title' => 'Capture and qualify leads', 'description' => 'Qualify needs, share availability, and turn interest into confirmed bookings or orders.'],
                                ['icon' => 'inbox', 'title' => 'Follow up with customers', 'description' => 'Automate reminders and campaigns while your team takes over any thread instantly.'],
                                ['icon' => 'store', 'title' => 'Sell beyond the chat', 'description' => 'Same catalog on web storefront and dine-in QR — WhatsApp stays your sales conversation channel.'],
                                ['icon' => 'growth', 'title' => 'Measure what drives sales', 'description' => 'Growth Engine posts, WhatsApp campaigns, and referral attribution tied to real orders.'],
                                ['icon' => 'dinein', 'title' => 'Built for modern businesses', 'description' => 'Retail, ecommerce, restaurants, services, and schools — one WhatsApp commerce platform.'],
                                ['icon' => 'delivery', 'title' => 'Delivery & taxes included', 'description' => 'Zones, fees, and tax rules so quotes and checkout stay accurate.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'intro_card',
                        'label' => 'Intro card',
                        'sort_order' => 3,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Automate your WhatsApp sales',
                            'description' => 'RelayIQ runs the full journey: discover → recommend → order or book → pay → fulfill → follow up. It learns from conversations so every reply gets sharper — while humans stay in control.',
                            'ctaText' => 'Explore WhatsApp AI sales agent',
                            'ctaHref' => '/whatsapp-ai-sales-agent',
                            'imageUrl' => $introImage,
                            'imageAlt' => 'Automate WhatsApp sales with RelayIQ AI agent',
                        ],
                    ],
                    [
                        'section_key' => 'feature_1',
                        'label' => 'Feature: WhatsApp storefront',
                        'sort_order' => 4,
                        'content' => [
                            'label' => 'WHATSAPP STOREFRONT',
                            'title' => 'A storefront connected to WhatsApp — so every chat can become a sale',
                            'description' => 'Put your catalog where Kenyan buyers already message you. Customers browse products on WhatsApp, ask in English or Kiswahili, and pay with M-Pesa in the same thread. Share the same shop as a web storefront for Google, Instagram, and link-in-bio.',
                            'points' => [
                                'Live WhatsApp catalog — photos, prices, variants, and stock',
                                'Matching web storefront for search, cart, and coupons',
                                'Checkout in chat with M-Pesa, Paystack, or Stripe',
                                'Included on the free Starter plan (20 products)',
                            ],
                            'ctaText' => 'Open your free storefront',
                            'ctaHref' => '/register',
                            'imageUrl' => $storefrontImage,
                            'imageAlt' => 'WhatsApp chat showing product catalog next to a matching web storefront',
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'feature_2',
                        'label' => 'Feature: Bookings',
                        'sort_order' => 5,
                        'content' => [
                            'label' => 'BOOKINGS & APPOINTMENTS',
                            'title' => 'Fill your calendar from WhatsApp — no more missed DMs',
                            'description' => 'Salons, clinics, tutors, and studios lose bookings to unread chats. RelayIQ qualifies the request, shares open slots, and confirms the appointment in the same conversation — plus a public booking page for customers who prefer the web.',
                            'points' => [
                                'Service catalog with duration, price, and availability',
                                'Confirm appointments inside WhatsApp',
                                'Public booking page you can share anywhere',
                                '30 bookings per month on the free Starter plan',
                            ],
                            'ctaText' => 'Start booking for free',
                            'ctaHref' => '/register',
                            'imageUrl' => $bookingsImage,
                            'imageAlt' => 'WhatsApp appointment booking calendar for salons clinics and services',
                            'imagePosition' => 'right',
                        ],
                    ],
                    [
                        'section_key' => 'feature_3',
                        'label' => 'Feature: Dine-in tables',
                        'sort_order' => 6,
                        'content' => [
                            'label' => 'DINE-IN TABLE QR',
                            'title' => 'Guests scan, order, and pay — without waiting for a waiter',
                            'description' => 'Give every table a QR code. Diners browse your live menu, order, and pay while kitchen tickets land with the table name. Pair with WhatsApp for takeaway and delivery so one menu runs the floor and the inbox.',
                            'points' => [
                                'Per-table QR codes that never mix up orders',
                                'Same menu as WhatsApp takeaway and your web storefront',
                                'Pay at the table with M-Pesa or card',
                                '5 dine-in tables included on the free Starter plan',
                            ],
                            'ctaText' => 'Add tables for free',
                            'ctaHref' => '/register',
                            'imageUrl' => $dineinImage,
                            'imageAlt' => 'Restaurant dine-in table QR code menu ordering and payment',
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'feature_4',
                        'label' => 'Feature: Storefront + dine-in',
                        'sort_order' => 7,
                        'is_enabled' => false,
                        'content' => [
                            'label' => 'STOREFRONT & DINE-IN',
                            'title' => 'Same catalog on the web — and at the table',
                            'description' => 'Publish a branded storefront for browsers (cart, checkout, coupons, order tracking). For restaurants, generate QR codes per table so guests order and pay without waiting for a waiter.',
                            'ctaText' => 'Explore solutions',
                            'ctaHref' => '/solutions#storefront',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Web storefront and dine-in QR ordering',
                            'imagePosition' => 'right',
                        ],
                    ],
                    [
                        'section_key' => 'growth_engine',
                        'label' => 'Growth Engine',
                        'sort_order' => 8,
                        'is_enabled' => false,
                        'content' => [
                            'label' => 'GROWTH ENGINE',
                            'title' => 'Turn chats into campaigns you can measure',
                            'description' => 'Create AI-assisted posts, publish to social platforms, and track WhatsApp referral links so you know which content drives orders.',
                            'points' => [
                                'AI post generation with image support',
                                'Multi-platform publishing by plan',
                                'Attribution via WhatsApp referral links',
                                'Follow-ups that bring customers back',
                            ],
                            'ctaText' => 'See Growth plan',
                            'ctaHref' => '/pricing',
                            'imageUrl' => $ctaImage,
                            'imageAlt' => 'Growth Engine campaigns and attribution',
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'How to join',
                        'sort_order' => 9,
                        'content' => [
                            'title' => 'Go live on the free plan',
                            'description' => 'Create a free Starter account, add your catalog, and start selling on WhatsApp, the web, and at the table — no credit card.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Three steps to go live on RelayIQ free Starter',
                            'imagePosition' => 'right',
                            'steps' => [
                                ['title' => 'Create your free Starter account', 'description' => 'Sign up in minutes. Starter is free forever — storefront, bookings, and 5 dine-in tables included.'],
                                ['title' => 'Add catalog, storefront & payments', 'description' => 'Products (physical, digital, bookings), dine-in tables if needed, FAQs, and M-Pesa / Paystack / Stripe.'],
                                ['title' => 'Sell on chat, web, and at the table', 'description' => 'AI handles replies and orders; storefront and QR tables use the same catalog; your team takes over any chat when needed.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'trusted_companies',
                        'label' => 'Trusted companies',
                        'sort_order' => 10,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Built for WhatsApp-first sellers across Africa and beyond',
                            'companies' => [],
                        ],
                    ],
                    [
                        'section_key' => 'testimonials',
                        'label' => 'Testimonials',
                        'sort_order' => 11,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'What sellers say',
                            'description' => 'Real stories from businesses running RelayIQ.',
                        ],
                    ],
                    [
                        'section_key' => 'payment_logos',
                        'label' => 'Payment gateways',
                        'sort_order' => 11,
                        'is_enabled' => true,
                        'content' => [
                            'title' => 'PAYMENTS THAT WORK IN CHAT AND ON THE WEB',
                            'description' => 'Collect with the rails your customers already use',
                            'items' => [
                                ['name' => 'M-Pesa', 'logoUrl' => '/images/payments/mpesa.svg?v=2'],
                                ['name' => 'Paystack', 'logoUrl' => '/images/payments/paystack.svg?v=2'],
                                ['name' => 'Stripe', 'logoUrl' => '/images/payments/stripe.svg?v=2'],
                                ['name' => 'Flutterwave', 'logoUrl' => '/images/payments/flutterwave.png?v=2'],
                                ['name' => 'Pesapal', 'logoUrl' => '/images/payments/pesapal.png?v=2'],
                                ['name' => 'PayPal', 'logoUrl' => '/images/payments/paypal.svg?v=2'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 12,
                        'is_enabled' => true,
                        'content' => [
                            'title' => 'Frequently asked questions',
                            'description' => 'Straight answers about the free Starter plan, WhatsApp storefront, bookings, dine-in, and payments.',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 13,
                        'content' => [
                            'title' => 'Start free this week — shop, bookings, tables, and WhatsApp AI',
                            'description' => 'One free Starter account: a web storefront, appointment bookings, dine-in QR, and WhatsApp selling when you connect your number. No credit card. Upgrade only when you need more.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                            'secondaryCtaText' => 'See pricing',
                            'secondaryCtaHref' => '/pricing',
                            'imageUrl' => '/images/lando/lando-cta.jpg',
                            'imageAlt' => 'Kenyan shop owner managing WhatsApp orders from her phone',
                            'showImage' => true,
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'solutions',
                'title' => 'Solutions',
                'meta_title' => 'WhatsApp Storefront, Bookings & Dine-in Solutions | RelayIQ',
                'meta_description' => 'RelayIQ solutions: WhatsApp AI that sells, a storefront connected to WhatsApp, appointment bookings, and dine-in table QR. One catalog. Free Starter plan.',
                'og_title' => 'WhatsApp storefront, bookings, and dine-in — RelayIQ solutions',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'kicker' => 'ONE CATALOG · FOUR WAYS TO SELL',
                            'title' => 'Sell on WhatsApp, the web, in bookings, and at the table',
                            'description' => 'Customers already message you. RelayIQ turns that chat into a shop, a booking calendar, and a dine-in menu — with AI that answers, takes payment, and hands off to your team.',
                            'primaryCtaText' => 'Get started free',
                            'primaryCtaHref' => '/register',
                            'secondaryCtaText' => 'See all features',
                            'secondaryCtaHref' => '/features',
                        ],
                    ],
                    [
                        'section_key' => 'outcomes',
                        'label' => 'Four solutions',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Four ways customers buy from you',
                            'description' => 'Pick the door that matches how you already sell. One catalog powers all of them — included on the free Starter plan.',
                            'items' => [
                                ['value' => 'AI', 'label' => 'WhatsApp AI', 'detail' => 'Answers, recommends, and takes payment in chat'],
                                ['value' => 'Shop', 'label' => 'Storefront', 'detail' => 'A web shop connected to the same WhatsApp catalog'],
                                ['value' => 'Book', 'label' => 'Bookings', 'detail' => 'Appointments from WhatsApp and a public booking page'],
                                ['value' => 'QR', 'label' => 'Dine-in', 'detail' => 'Table QR menus, orders, and pay at the table'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'solution_pillars',
                        'label' => 'Solution pillars',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'What RelayIQ does for you',
                            'description' => 'Plain language. Each solution below is something you can turn on this week — not a separate product to stitch together.',
                            'items' => [
                                [
                                    'id' => 'whatsapp-ai',
                                    'icon' => 'bot',
                                    'label' => '01 · WHATSAPP AI',
                                    'title' => 'An AI sales agent on the number customers already message',
                                    'description' => 'Customers ask in English or Kiswahili. RelayIQ answers from your real catalog, recommends the right item, and can send M-Pesa in the same thread. Your team jumps in when a human should close.',
                                    'points' => [
                                        'Replies grounded in stock, prices, and FAQs — not a numbered menu',
                                        'M-Pesa, Paystack, or Stripe checkout inside WhatsApp',
                                        'Shared inbox so a person can take over any chat',
                                        'Official WhatsApp Business Cloud API',
                                    ],
                                    'sampleTitle' => 'What the customer sees',
                                    'sampleLines' => [
                                        'Do you have Air Runner in size 42?',
                                        'Yes — Air Runner (42) is in stock at KSh 4,500. Should I send M-Pesa?',
                                        'Yes please',
                                        'STK sent. Order #4821 is ready when you enter your PIN.',
                                    ],
                                    'ctaText' => 'Start free on WhatsApp',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'storefront',
                                    'icon' => 'store',
                                    'label' => '02 · STOREFRONT',
                                    'title' => 'A web shop connected to WhatsApp — one catalog, two doors',
                                    'description' => 'Some buyers want to browse. Publish a storefront for Google, Instagram, and link-in-bio. Prices and stock stay in sync with WhatsApp, so you never update two shops.',
                                    'points' => [
                                        'Cart, checkout, coupons, and order tracking',
                                        'Same products the AI sells in chat',
                                        'Share one link instead of sending photos one by one',
                                        'Included on the free Starter plan (20 products)',
                                    ],
                                    'sampleTitle' => 'What the shopper does',
                                    'sampleLines' => [
                                        'Opens your storefront from Instagram',
                                        'Adds Classic Tee, applies WELCOME10',
                                        'Pays with M-Pesa or card',
                                        'Order confirmed — WhatsApp can follow up later',
                                    ],
                                    'ctaText' => 'Open your free storefront',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'bookings',
                                    'icon' => 'booking',
                                    'label' => '03 · BOOKINGS',
                                    'title' => 'Fill the calendar from WhatsApp — no more missed DMs',
                                    'description' => 'Salons, clinics, tutors, and studios lose bookings to unread chats. RelayIQ shares open slots, confirms the appointment, and gives you a public booking page to share anywhere.',
                                    'points' => [
                                        'Services with duration and price',
                                        'Confirm the slot inside WhatsApp',
                                        'Public booking page for links and bios',
                                        '30 bookings per month on free Starter',
                                    ],
                                    'sampleTitle' => 'What the client sees',
                                    'sampleLines' => [
                                        'Can I book braids on Saturday?',
                                        'Saturday: 10:00, 13:00, 16:00. Braids = 90 min · KSh 3,500.',
                                        '13:00',
                                        'Booked for Sat 13:00. Reply here to reschedule.',
                                    ],
                                    'ctaText' => 'Start booking for free',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'dine-in',
                                    'icon' => 'dinein',
                                    'label' => '04 · DINE-IN',
                                    'title' => 'Guests scan, order, and pay — without waiting for a waiter',
                                    'description' => 'Give every table a QR code. Diners browse your live menu, order, and pay. Kitchen tickets show the table name. Use WhatsApp for takeaway so one menu runs the floor and the inbox.',
                                    'points' => [
                                        'Per-table QR codes so orders never mix',
                                        'Same menu as WhatsApp takeaway and the storefront',
                                        'Pay at the table with M-Pesa or card',
                                        '5 tables included on free Starter',
                                    ],
                                    'sampleTitle' => 'What happens at Table 7',
                                    'sampleLines' => [
                                        'Guest scans the Table 7 QR',
                                        'Welcome — tap to order from today’s menu.',
                                        '1× fish + 2× passion juice',
                                        'Order placed for Table 7 · KSh 1,850. Pay now.',
                                    ],
                                    'ctaText' => 'Add tables for free',
                                    'ctaHref' => '/register',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'demos',
                        'label' => 'Demo gallery',
                        'sort_order' => 4,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Ready-to-show demos',
                            'description' => 'Hidden — the four solutions above replace this gallery.',
                            'items' => [],
                        ],
                    ],
                    [
                        'section_key' => 'industries',
                        'label' => 'Industries',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Who this is for',
                            'description' => 'Same product. Different starting door. Choose the path that matches how you already sell.',
                            'items' => [
                                [
                                    'icon' => 'dinein',
                                    'title' => 'Restaurants & cafés',
                                    'description' => 'Menus on WhatsApp for delivery/takeaway, QR dine-in for the floor, M-Pesa at the table or in chat.',
                                    'outcomes' => [
                                        'Fewer missed orders during rush',
                                        'One menu for chat + tables + storefront',
                                        'Payment confirmation without screenshots',
                                    ],
                                    'ctaText' => 'Restaurant playbook',
                                    'ctaHref' => '/solutions/restaurants',
                                ],
                                [
                                    'icon' => 'package',
                                    'title' => 'Retail & e-commerce',
                                    'description' => 'Answer stock questions, take orders in chat, and keep a full web storefront for browser shoppers.',
                                    'outcomes' => [
                                        'Variants and stock answered accurately',
                                        'WhatsApp + /s/{slug} share inventory',
                                        'Campaigns attributed back to chats',
                                    ],
                                    'ctaText' => 'Retail playbook',
                                    'ctaHref' => '/solutions/retail',
                                ],
                                [
                                    'icon' => 'booking',
                                    'title' => 'Services & appointments',
                                    'description' => 'Salons, clinics, tutors, consultants — qualify in chat, book a slot, collect deposits.',
                                    'outcomes' => [
                                        'Less back-and-forth on availability',
                                        'Public booking page for direct links',
                                        'Team inbox for complex cases',
                                    ],
                                    'ctaText' => 'Get started free',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'icon' => 'sparkles',
                                    'title' => 'Digital creators & educators',
                                    'description' => 'Sell downloads, courses, and license keys with instant fulfillment after payment.',
                                    'outcomes' => [
                                        'Automatic digital delivery',
                                        'License keys tracked on the order',
                                        'Support handoff when buyers need help',
                                    ],
                                    'ctaText' => 'Get started free',
                                    'ctaHref' => '/register',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'How to join',
                        'sort_order' => 6,
                        'content' => [
                            'title' => 'Go live in three steps',
                            'description' => 'Start on the free plan. Turn on only WhatsApp, storefront, bookings, or dine-in — whatever you need first.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Three steps to go live on RelayIQ',
                            'steps' => [
                                ['title' => 'Create your free Starter account', 'description' => 'No credit card. Storefront, bookings, and 5 dine-in tables are included from day one.'],
                                ['title' => 'Add products and connect WhatsApp', 'description' => 'Load your catalog, connect M-Pesa or cards, and link your WhatsApp Business number.'],
                                ['title' => 'Share your chat, shop, booking page, or table QR', 'description' => 'Customers buy where they already are. Your team can take over any conversation.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 7,
                        'content' => [
                            'title' => 'Questions buyers ask before they convert',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 8,
                        'content' => [
                            'title' => 'Ready to sell the way your customers already buy?',
                            'description' => 'WhatsApp AI, storefront, bookings, and dine-in — one free Starter account. No credit card.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pricing',
                'title' => 'Pricing',
                'meta_title' => 'WhatsApp AI Sales Pricing — Free Starter, Growth KSh 2,000 | RelayIQ',
                'meta_description' => 'RelayIQ pricing: Starter is free forever (storefront, bookings, dine-in). Growth is KSh 2,000/month. Custom plans via sales.',
                'og_title' => 'RelayIQ pricing — start free, grow at KSh 2,000/month',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Start free. Grow when you are ready.',
                            'description' => 'Starter is free forever for businesses — storefront, bookings, and dine-in included. Growth is KSh 2,000 per month when you need more. Need custom limits? Talk to sales.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Pricing SEO intro',
                        'sort_order' => 2,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'What every plan includes',
                            'html' => '<p>RelayIQ pricing starts with a <strong>free forever Starter</strong> plan so every business can sell on WhatsApp, the web, and at the table.</p>
<ul>
<li><strong>Starter</strong> — Free forever. Storefront, bookings, dine-in (5 tables), 20 products (physical or digital), and 30 bookings per month.</li>
<li><strong>Growth</strong> — KSh 2,000/month. Up to 50 products, 20 tables, 150 bookings/month, and more AI conversations.</li>
<li><strong>Custom</strong> — Limits and pricing set with the sales team for high-volume operations.</li>
</ul>
<p>Compare features below, then <a href="/register">get started free</a> or explore the <a href="/whatsapp-ai-sales-agent">AI sales agent for WhatsApp</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'pricing_plans',
                        'label' => 'Pricing plans',
                        'sort_order' => 3,
                        'content' => [
                            'usePlansApi' => true,
                            'popularBadge' => 'Most Popular',
                        ],
                    ],
                    [
                        'section_key' => 'compare_features',
                        'label' => 'Compare features',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'Compare WhatsApp automation features',
                            'columns' => [
                                [
                                    'name' => 'Starter',
                                    'features' => [
                                        'Free forever',
                                        'Storefront + link-in-bio',
                                        'Physical & digital catalog (20)',
                                        'Bookings (30 / month)',
                                        'Dine-in tables (5)',
                                        '1 WhatsApp number',
                                        '50 AI conversations / month',
                                        'M-Pesa',
                                        'RelayIQ branding',
                                    ],
                                ],
                                [
                                    'name' => 'Growth',
                                    'features' => [
                                        'Everything in Starter',
                                        'KSh 2,000 / month',
                                        '50 products',
                                        'Bookings (150 / month)',
                                        'Dine-in tables (20)',
                                        '1,000 AI conversations / month',
                                        '3 team seats',
                                        'Paystack & Stripe',
                                        'Upgrade anytime from free Starter',
                                    ],
                                ],
                                [
                                    'name' => 'Custom',
                                    'features' => [
                                        'Everything in Growth',
                                        'Custom product & table limits',
                                        'Custom booking volume',
                                        'Custom team & WhatsApp numbers',
                                        'Priority support & onboarding',
                                        'Talk to sales',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Frequently asked questions',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 6,
                        'content' => [
                            'title' => 'Ready to start free?',
                            'description' => 'Create your free Starter account now. Upgrade to Growth (KSh 2,000/month) when you need more products, tables, or AI conversations.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'about',
                'title' => 'About us',
                'meta_title' => 'About us — RelayIQ by Essem Digital',
                'meta_description' => 'RelayIQ is the AI commerce OS from Essem Digital Innovation Limited — WhatsApp sales, storefront, bookings, dine-in, payments, and growth in one platform.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'About RelayIQ',
                            'description' => 'RelayIQ is the AI commerce operating system from Essem Digital Innovation Limited — an Official WhatsApp Business Partner. We help businesses sell on WhatsApp — and run a storefront, bookings, and dine-in — without stitching five tools together.',
                            'imageUrl' => '/images/lando/lando-about-team.jpg',
                            'imageAlt' => 'RelayIQ teammates in Nairobi reviewing the product together',
                        ],
                    ],
                    [
                        'section_key' => 'mission',
                        'label' => 'Mission',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Our mission',
                            'description' => 'Every business deserves an intelligent front line: accurate catalog answers, payments that work for Africa and the world, digital delivery, service bookings, table QR ordering, and humans in the loop when it matters. Intelligence at the center — not hard-coded menus.',
                        ],
                    ],
                    [
                        'section_key' => 'outcomes',
                        'label' => 'What we stand for',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Built for operators, not science projects',
                            'description' => 'We ship the commerce surfaces merchants actually use — then put AI on top so the same catalog sells in chat, on the web, and at the table.',
                            'items' => [
                                ['value' => '1', 'label' => 'Catalog', 'detail' => 'Physical, digital, services & bundles'],
                                ['value' => '3', 'label' => 'Front doors', 'detail' => 'WhatsApp · Storefront · Table QR'],
                                ['value' => 'AI+', 'label' => 'Human inbox', 'detail' => 'Agent first line, team takeover anytime'],
                                ['value' => 'Pay', 'label' => 'In context', 'detail' => 'M-Pesa · Paystack · Stripe'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'capabilities',
                        'label' => 'What RelayIQ includes',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'What we build under one roof',
                            'description' => 'About pages should tell you who we are. Solutions shows how each pillar works — here is the short map.',
                            'items' => [
                                ['icon' => 'bot', 'title' => 'AI commerce agent', 'description' => 'Fluent sales & support on WhatsApp with memory and catalog grounding.'],
                                ['icon' => 'package', 'title' => 'Unified catalog', 'description' => 'Physical goods, digital files/licenses, and bookable services.'],
                                ['icon' => 'store', 'title' => 'Web storefront', 'description' => 'Browser checkout with the same inventory as chat.'],
                                ['icon' => 'booking', 'title' => 'Bookings', 'description' => 'Included on free Starter — confirm appointments from WhatsApp.'],
                                ['icon' => 'dinein', 'title' => 'Dine-in tables', 'description' => 'Included on free Starter — QR ordering per table for restaurants.'],
                                ['icon' => 'growth', 'title' => 'Growth & campaigns', 'description' => 'Posts, attribution links, and WhatsApp broadcasts.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'industries',
                        'label' => 'Who we serve',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Who RelayIQ is for',
                            'description' => 'If your customers already message you — or should be able to buy without downloading another app — you are our buyer.',
                            'items' => [
                                [
                                    'icon' => 'dinein',
                                    'title' => 'Restaurants & cafés',
                                    'description' => 'Takeaway and delivery on WhatsApp; dine-in QR on the floor; one menu everywhere.',
                                    'outcomes' => ['Fewer missed tickets', 'M-Pesa in the flow', 'Same catalog for chat + tables'],
                                    'ctaText' => 'See restaurant solutions',
                                    'ctaHref' => '/solutions#industries',
                                ],
                                [
                                    'icon' => 'package',
                                    'title' => 'Retail & e-commerce',
                                    'description' => 'Answer stock questions in chat and keep a full storefront for browser shoppers.',
                                    'outcomes' => ['Variants answered accurately', 'Storefront + WhatsApp share SKUs', 'Campaign attribution'],
                                    'ctaText' => 'See retail solutions',
                                    'ctaHref' => '/solutions#storefront',
                                ],
                                [
                                    'icon' => 'booking',
                                    'title' => 'Services & appointments',
                                    'description' => 'Salons, clinics, tutors — qualify need, book a slot, collect deposits.',
                                    'outcomes' => ['Less back-and-forth', 'Public booking page', 'Team inbox for exceptions'],
                                    'ctaText' => 'See bookings',
                                    'ctaHref' => '/solutions#bookings',
                                ],
                                [
                                    'icon' => 'sparkles',
                                    'title' => 'Digital sellers',
                                    'description' => 'Creators and educators selling downloads and license keys with instant fulfillment.',
                                    'outcomes' => ['Pay then deliver', 'License keys on the order', 'Human handoff when needed'],
                                    'ctaText' => 'See catalog types',
                                    'ctaHref' => '/solutions#whatsapp-ai',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'efficiency',
                        'label' => 'How we work',
                        'sort_order' => 6,
                        'content' => [
                            'title' => 'Company behind the product',
                            'description' => 'RelayIQ is built and operated by Essem Digital Innovation Limited. We design for African payment realities first (M-Pesa, Paystack, local currencies) while supporting global card rails — and we keep humans in control of every conversation.',
                            'ctaText' => 'Explore all solutions',
                            'ctaHref' => '/solutions',
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'Next steps',
                        'sort_order' => 7,
                        'content' => [
                            'title' => 'What to do next',
                            'description' => 'Skim Solutions, then start on the free Starter plan — storefront, bookings, and dine-in included.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Next steps with RelayIQ',
                            'steps' => [
                                ['title' => 'See the full solution map', 'description' => 'Visit Solutions for pillars, sample chats, and industry playbooks.'],
                                ['title' => 'Start on free Starter', 'description' => 'Storefront, bookings, and 5 dine-in tables are included. No credit card.'],
                                ['title' => 'Upgrade only when you need more', 'description' => 'Growth is KSh 2,000/month for more products, tables, and AI conversations.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'team',
                        'label' => 'Team',
                        'sort_order' => 8,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Team',
                            'description' => 'Meet the people behind RelayIQ',
                            'members' => [],
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 9,
                        'content' => [
                            'title' => 'Ready to run commerce under one roof?',
                            'description' => 'Start on the free Starter plan — or talk to us about Custom.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'features',
                'title' => 'Features',
                'meta_title' => FeaturesPageCopy::title(),
                'meta_description' => FeaturesPageCopy::description(),
                'og_title' => FeaturesPageCopy::title(),
                'sections' => array_map(static function (array $section): array {
                    return [
                        'section_key' => $section['key'],
                        'label' => $section['label'],
                        'sort_order' => $section['sortOrder'],
                        'is_enabled' => $section['isEnabled'],
                        'content' => $section['content'],
                    ];
                }, FeaturesPageCopy::sections()),
            ],
            [
                'slug' => 'whatsapp-ai-sales-agent',
                'title' => 'WhatsApp AI Sales Agent',
                'meta_title' => 'AI Sales Agent for WhatsApp — RelayIQ',
                'meta_description' => 'What is a WhatsApp AI sales agent? RelayIQ engages customers, answers questions, recommends products, qualifies leads, follows up, and helps close sales on WhatsApp.',
                'og_title' => 'AI Sales Agent for WhatsApp — RelayIQ',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'AI sales agent for WhatsApp',
                            'description' => 'Turn chats into revenue. RelayIQ’s AI sales agent answers instantly, recommends products, captures leads, follows up, and hands off to your team when a human should close.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'What is a WhatsApp AI sales agent?',
                            'html' => '<p>A WhatsApp AI sales agent is software that holds real sales conversations on WhatsApp — not a menu bot. It understands your catalog, answers product questions, recommends items, collects order details, and can trigger payment — while your team stays one click away.</p>
<h3>How RelayIQ’s AI agent sells</h3>
<ul>
<li><strong>Engage</strong> — Instant replies to inbound WhatsApp messages</li>
<li><strong>Recommend</strong> — Catalog-aware product and service suggestions</li>
<li><strong>Qualify</strong> — Capture needs, budget signals, and lead details</li>
<li><strong>Follow up</strong> — Reminders and campaigns that bring buyers back</li>
<li><strong>Convert</strong> — In-chat checkout with M-Pesa, Paystack, or Stripe</li>
<li><strong>Handoff</strong> — Humans take over VIP or complex deals from the team inbox</li>
</ul>
<p>Compared with a basic chatbot, an AI sales agent is measured on <em>sales outcomes</em>: replies that move buyers forward. Learn more about <a href="/whatsapp-sales-automation">WhatsApp sales automation</a>, see <a href="/features">all features</a>, or <a href="/pricing">compare plans</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'capabilities',
                        'label' => 'Agent capabilities',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Built to close sales — not just reply',
                            'description' => 'Everything an AI sales agent needs on WhatsApp.',
                            'items' => [
                                ['icon' => 'bot', 'title' => 'Conversational AI', 'description' => 'Natural language with memory across the thread.'],
                                ['icon' => 'package', 'title' => 'Live catalog', 'description' => 'Stock, variants, prices, and digital goods.'],
                                ['icon' => 'payment', 'title' => 'Payments', 'description' => 'Checkout without leaving WhatsApp.'],
                                ['icon' => 'inbox', 'title' => 'Human handoff', 'description' => 'Team inbox with full conversation history.'],
                                ['icon' => 'growth', 'title' => 'Follow-ups', 'description' => 'Campaigns and win-backs that reopen deals.'],
                                ['icon' => 'store', 'title' => 'Analytics', 'description' => 'Track chats that become orders.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 4,
                        'content' => ['title' => 'Frequently asked questions'],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Put an AI sales agent on your WhatsApp',
                            'description' => 'Starter is free forever. Connect WhatsApp and start selling.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'whatsapp-sales-automation',
                'title' => 'WhatsApp Sales Automation',
                'meta_title' => 'WhatsApp Sales Automation — RelayIQ',
                'meta_description' => 'Automate WhatsApp sales with AI: instant replies, product recommendations, lead capture, follow-ups, and in-chat payments. See how RelayIQ works.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp sales automation',
                            'description' => 'Automate the sales work that burns out teams — first replies, FAQs, recommendations, follow-ups — while keeping humans for high-value closes.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'How to automate WhatsApp sales with AI',
                            'html' => '<p>WhatsApp sales automation means your buyers get fast, accurate answers 24/7 — and your team spends time closing, not copy-pasting FAQs.</p>
<ol>
<li>Connect WhatsApp Business (Embedded Signup)</li>
<li>Add catalog, FAQs, and payment methods</li>
<li>Let the <a href="/whatsapp-ai-sales-agent">AI sales agent</a> handle routine conversations</li>
<li>Escalate complex deals to the team inbox</li>
<li>Measure which chats become paid orders</li>
</ol>
<p>RelayIQ also powers <a href="/whatsapp-commerce">WhatsApp commerce</a> and a web storefront from the same catalog. <a href="/pricing">See pricing</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'How it works',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'How RelayIQ works',
                            'description' => 'From signup to your first AI-assisted sale.',
                            'ctaText' => 'Create your account',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Three steps to automate WhatsApp sales with RelayIQ',
                            'steps' => [
                                ['title' => 'Connect WhatsApp', 'description' => 'Sign up and connect your WhatsApp Business number.'],
                                ['title' => 'Train your AI', 'description' => 'Add products, FAQs, and business basics.'],
                                ['title' => 'Start selling', 'description' => 'AI handles replies and orders; your team takes over when needed.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'Automate WhatsApp sales this week',
                            'description' => 'Start free — no credit card required on eligible plans.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'whatsapp-chatbot',
                'title' => 'WhatsApp Chatbot',
                'meta_title' => 'WhatsApp Chatbot for Sales — RelayIQ',
                'meta_description' => 'Looking for a WhatsApp chatbot for sales? RelayIQ goes beyond menus — an AI sales agent that recommends products, takes orders, and collects payment on WhatsApp.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp chatbot for sales',
                            'description' => 'Skip rigid 1-2-3 menus. RelayIQ is a WhatsApp chatbot built to sell — with catalog awareness, payments, and human handoff.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'AI chatbot vs AI sales agent',
                            'html' => '<p>Many “WhatsApp chatbots” only deflect tickets. An <strong>AI sales agent</strong> is different: it is measured on recommendations, lead quality, and closed orders.</p>
<p>RelayIQ combines chatbot convenience with sales outcomes — see <a href="/whatsapp-ai-sales-agent">AI sales agent for WhatsApp</a> and <a href="/features">features</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Replace menu bots with a sales agent',
                            'description' => 'Create a free Starter account and connect WhatsApp.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'whatsapp-commerce',
                'title' => 'WhatsApp Commerce',
                'meta_title' => 'WhatsApp Commerce Platform — RelayIQ',
                'meta_description' => 'Run WhatsApp commerce with RelayIQ: catalog, in-chat payments, orders, storefront, and AI that helps customers buy without leaving WhatsApp.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp commerce platform',
                            'description' => 'Sell physical, digital, and bookable products on WhatsApp — with the same catalog on your web storefront.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'WhatsApp commerce that actually checks out',
                            'html' => '<p>WhatsApp commerce works when customers can discover, decide, and pay in one thread. RelayIQ connects catalog, AI recommendations, M-Pesa/Paystack/Stripe, and order tracking.</p>
<p>Also explore <a href="/whatsapp-for-ecommerce">WhatsApp for ecommerce</a> and <a href="/whatsapp-sales-automation">sales automation</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Launch WhatsApp commerce',
                            'description' => 'One catalog for chat and web. Start free.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'whatsapp-lead-generation',
                'title' => 'WhatsApp Lead Generation',
                'meta_title' => 'WhatsApp Lead Generation with AI — RelayIQ',
                'meta_description' => 'Generate and qualify WhatsApp leads with AI. RelayIQ captures intent, asks the right questions, and routes hot leads to your team.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp lead generation',
                            'description' => 'Turn inbound WhatsApp interest into qualified leads — automatically — then close with humans when it counts.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Capture and qualify leads on WhatsApp',
                            'html' => '<p>Every unread WhatsApp message is a lost lead. RelayIQ’s AI engages instantly, asks qualifying questions, and syncs context into your team inbox.</p>
<p>Pair with <a href="/whatsapp-ai-sales-agent">AI sales agent</a> workflows and <a href="/pricing">Growth campaigns</a> for follow-up.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Stop losing WhatsApp leads',
                            'description' => 'Activate AI lead capture on your number.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'ai-customer-service',
                'title' => 'AI Customer Service',
                'meta_title' => 'WhatsApp Customer Service Automation — RelayIQ',
                'meta_description' => 'Automate WhatsApp customer service with AI that knows your catalog and FAQs — and hands off to humans when needed.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp customer service automation',
                            'description' => 'Resolve FAQs instantly, keep order status accurate, and escalate to your team with full context.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Support that still sells',
                            'html' => '<p>Great WhatsApp support protects revenue. RelayIQ answers with facts from FAQs, orders, and catalog — then can recommend the next purchase when the moment is right.</p>
<p>See <a href="/features">features</a> and <a href="/whatsapp-sales-automation">sales automation</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Automate WhatsApp support',
                            'description' => 'Start free and connect your number.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'whatsapp-for-ecommerce',
                'title' => 'WhatsApp for Ecommerce',
                'meta_title' => 'WhatsApp for Ecommerce Sales — RelayIQ',
                'meta_description' => 'Use WhatsApp for ecommerce: AI product advice, carts, M-Pesa checkout, and a matching web storefront from one RelayIQ catalog.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'usePageHero' => true,
                            'title' => 'WhatsApp for ecommerce',
                            'description' => 'Advise shoppers on WhatsApp, take payment in-chat, and keep the same catalog on your storefront for SEO and browsing.',
                        ],
                    ],
                    [
                        'section_key' => 'prose',
                        'label' => 'Article',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'How to use WhatsApp for ecommerce sales',
                            'html' => '<p>Ecommerce brands win on WhatsApp when advice and checkout happen together. RelayIQ connects inventory, AI recommendations, payments, and order tracking — plus a browser storefront for self-serve shoppers.</p>
<p>Related: <a href="/whatsapp-commerce">WhatsApp commerce</a>, <a href="/whatsapp-ai-sales-agent">AI sales agent</a>, <a href="/pricing">pricing</a>.</p>',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'CTA',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Sell on WhatsApp and the web',
                            'description' => 'One catalog. Two front doors. Start free.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'meta_title' => 'Contact — RelayIQ',
                'meta_description' => 'Get in touch with the RelayIQ team. We would love to hear from you.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero + form',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Contact us',
                            'description' => 'Questions about RelayIQ, partnerships, or Enterprise? Send a message — we respond as soon as we can.',
                            'imageUrl' => '/images/lando/lando-contact.jpg',
                            'imageAlt' => 'RelayIQ teammate ready to help from Nairobi',
                            'nameLabel' => 'Name',
                            'namePlaceholder' => 'Full Name',
                            'emailLabel' => 'Email',
                            'emailPlaceholder' => 'Email address',
                            'messageLabel' => 'Message',
                            'messagePlaceholder' => 'How can we help?',
                            'submitText' => 'Send message',
                            'successMessage' => 'Thank you! We will get back to you shortly.',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Prefer to try it yourself?',
                            'description' => 'Create a free Starter account and explore storefront, bookings, and dine-in on WhatsApp.',
                            'ctaText' => 'Get started free',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy — RelayIQ',
                'meta_description' => 'How RelayIQ collects, uses, and protects your data.',
                'sections' => [
                    [
                        'section_key' => 'legal_content',
                        'label' => 'Legal content',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Privacy Policy',
                            'lastUpdated' => 'June 2026',
                            'body' => '<h2>1. Who we are</h2>
<p>RelayIQ is a product of Essem Digital Innovation Limited. We provide a multi-tenant SaaS platform for WhatsApp business messaging, AI-assisted replies, order management, and related services. Learn more at <a href="https://relayiq.app" target="_blank" rel="noopener noreferrer">relayiq.app</a>.</p>
<h2>2. Information we collect</h2>
<p>We collect information you provide when you register and use the platform, including:</p>
<ul>
<li>Account details (name, email, company information)</li>
<li>WhatsApp business configuration and message content routed through the platform</li>
<li>Customer conversation data processed on your behalf</li>
<li>Payment and subscription records (processed by Stripe, Paystack, or M-Pesa providers)</li>
<li>Usage logs for billing, security, and product improvement</li>
</ul>
<h2>3. How we use your information</h2>
<p>We use collected data to provide, operate, and improve the RelayIQ platform, process AI-assisted replies, send service-related communications, and comply with legal obligations.</p>
<h2>4. Data sharing</h2>
<p>We do not sell your data. We share information only with service providers necessary to operate the platform (e.g. Meta/WhatsApp Cloud API, payment processors, AI providers you configure) and when required by law.</p>
<h2>5. Security</h2>
<p>We use industry-standard measures including encryption in transit and access controls. Each tenant\'s data is logically isolated in our multi-tenant architecture.</p>
<h2>6. Your rights</h2>
<p>Depending on your jurisdiction, you may have rights to access, correct, or delete personal data. Contact us at support@relayiq.app to submit a request.</p>
<h2>7. Changes</h2>
<p>We may update this policy from time to time. Continued use of the service after changes constitutes acceptance of the updated policy.</p>',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'meta_title' => 'Terms of Service — RelayIQ',
                'meta_description' => 'The terms governing your use of RelayIQ.',
                'sections' => [
                    [
                        'section_key' => 'legal_content',
                        'label' => 'Legal content',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Terms of Service',
                            'lastUpdated' => 'June 2026',
                            'body' => '<h2>1. Acceptance</h2>
<p>By creating an account or using RelayIQ, you agree to these Terms of Service. If you are using the service on behalf of a company, you represent that you have authority to bind that company.</p>
<h2>2. Service description</h2>
<p>RelayIQ provides WhatsApp business messaging, AI-assisted automation, order management, payment integrations, digital fulfillment, bookings, and related tools. Features vary by subscription plan.</p>
<h2>3. Your responsibilities</h2>
<p>You agree to:</p>
<ul>
<li>Comply with Meta\'s WhatsApp Business and Commerce policies</li>
<li>Obtain necessary consents from your customers for messaging and data processing</li>
<li>Keep your account credentials secure</li>
<li>Use the service only for lawful business purposes</li>
</ul>
<h2>4. Subscriptions and billing</h2>
<p>Paid plans are billed according to the pricing shown at checkout. Free trials on eligible plans convert to paid subscriptions unless cancelled before the trial ends. WhatsApp conversation fees charged by Meta may apply separately.</p>
<h2>5. AI-generated content</h2>
<p>AI replies are generated based on your configuration and content. You are responsible for reviewing automated responses and ensuring they meet your business and legal requirements.</p>
<h2>6. Limitation of liability</h2>
<p>The service is provided "as is" to the maximum extent permitted by law. RelayIQ is not liable for indirect, incidental, or consequential damages arising from use of the platform.</p>
<h2>7. Termination</h2>
<p>You may cancel your subscription at any time. We may suspend or terminate accounts that violate these terms or applicable law.</p>
<h2>8. Contact</h2>
<p>For questions about these terms, contact support@relayiq.app.</p>',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($pages as $pageData) {
            $sections = $pageData['sections'];
            unset($pageData['sections']);

            $page = CmsPage::updateOrCreate(
                ['slug' => $pageData['slug']],
                $pageData
            );

            foreach ($sections as $sectionData) {
                CmsSection::updateOrCreate(
                    [
                        'cms_page_id' => $page->id,
                        'section_key' => $sectionData['section_key'],
                    ],
                    [
                        'label' => $sectionData['label'],
                        'is_enabled' => (bool) ($sectionData['is_enabled'] ?? true),
                        'sort_order' => $sectionData['sort_order'],
                        'content' => $sectionData['content'],
                    ]
                );
            }
        }

        if (Testimonial::count() === 0) {
            $samples = [
                ['name' => 'Jack Sibire', 'role' => 'Lead Manager, Growio', 'content' => 'Since implementing RelayIQ our business has seen significant growth on WhatsApp.'],
                ['name' => 'Adele Mouse', 'role' => 'Product Manager, Mousio', 'content' => 'I recommend RelayIQ to any business looking to improve WhatsApp sales.'],
                ['name' => 'Ben Clock', 'role' => 'CTO, Clockwork', 'content' => "I can't imagine running our company without it."],
            ];
            foreach ($samples as $i => $sample) {
                Testimonial::create([
                    ...$sample,
                    'rating' => 5,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }

        // Keep landing FAQs aligned with the product story (overwrite seeded defaults).
        $faqs = [
            ['question' => 'What is a WhatsApp AI sales agent?', 'answer' => 'It is AI that holds real sales conversations on WhatsApp — answering questions, recommending products, capturing leads, following up, and helping close orders — with human handoff when needed. RelayIQ is built for that sales outcome, not just ticket deflection.'],
            ['question' => 'How does RelayIQ automate WhatsApp sales?', 'answer' => 'Connect your WhatsApp Business number, add your catalog and FAQs, enable payments, and the AI agent handles first replies, recommendations, and order capture while your team inbox stays ready for complex deals.'],
            ['question' => 'What can I sell on WhatsApp?', 'answer' => 'Physical products, digital goods (downloads and license keys), bookable services, and bundles — from one catalog that also powers your web storefront and dine-in QR menus.'],
            ['question' => 'Is RelayIQ just a WhatsApp chatbot?', 'answer' => 'No. Chatbots often stop at FAQs. RelayIQ is an AI sales agent plus commerce: catalog, in-chat payments, storefront, bookings, campaigns, and team handoff.'],
            ['question' => 'What payment methods are supported?', 'answer' => 'M-Pesa (including your own Till/PayBill), Paystack, and Stripe. Customers can pay in the conversation or on the storefront checkout.'],
            ['question' => 'Is there a free plan?', 'answer' => 'Yes. Starter is free forever: WhatsApp-connected storefront, bookings, dine-in (5 tables), and 20 products. Every new business starts here. No credit card. Growth is KSh 2,000/month if you need more capacity. Custom is priced with the sales team.'],
            ['question' => 'How does the WhatsApp storefront work?', 'answer' => 'Your catalog lives in RelayIQ. Customers browse and buy in WhatsApp, and the same products appear on your public web storefront for Google, Instagram, and link-in-bio. Payments (M-Pesa, Paystack, Stripe) work in chat and on the web.'],
            ['question' => 'Are bookings included on the free plan?', 'answer' => 'Yes. Free Starter includes bookable services and 30 bookings per month, plus a public booking page. Growth raises the monthly booking cap when you are busier.'],
            ['question' => 'Are dine-in tables included on the free plan?', 'answer' => 'Yes. Free Starter includes 5 dine-in tables with QR ordering from your live menu. Growth includes 20 tables. Pair with WhatsApp for takeaway and delivery.'],
            ['question' => 'Can my team take over conversations?', 'answer' => 'Yes. Agents jump into any thread from the shared inbox. The AI pauses until you hand the chat back.'],
            ['question' => 'Do you only work on WhatsApp?', 'answer' => 'WhatsApp is the primary sales conversation channel. RelayIQ also includes a web storefront, booking pages, and dine-in table QR ordering from the same catalog.'],
            ['question' => 'Where do I sign up?', 'answer' => 'Click Sign up or visit /register. Prefer to learn first? Read /whatsapp-ai-sales-agent or /features.'],
        ];
        LandingFaq::query()->delete();
        foreach ($faqs as $i => $faq) {
            LandingFaq::create([
                ...$faq,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
