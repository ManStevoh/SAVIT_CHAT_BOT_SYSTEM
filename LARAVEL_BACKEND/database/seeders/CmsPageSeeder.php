<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\LandingFaq;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $heroImage = '/images/lando/lando-hero.png';
        $introImage = '/images/lando/lando-intro.png';
        $inboxImage = '/images/lando/lando-inbox.png';
        $paymentsImage = '/images/lando/lando-payments.png';
        $stepsImage = '/images/lando/lando-steps.png';
        $ctaImage = '/images/lando/lando-cta.png';
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Platform illustration',
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
                                ['label' => 'Solutions', 'href' => '/solutions'],
                                ['label' => 'Pricing', 'href' => '/pricing'],
                                ['label' => 'About us', 'href' => '/about'],
                                ['label' => 'Blog', 'href' => '/blog'],
                                ['label' => 'Contact', 'href' => '/contact'],
                            ],
                            'socialLinks' => [
                                ['label' => 'Facebook', 'href' => '#'],
                                ['label' => 'Instagram', 'href' => '#'],
                                ['label' => 'Twitter', 'href' => '#'],
                                ['label' => 'Linkedin', 'href' => '#'],
                            ],
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
                'meta_title' => 'RelayIQ — AI Commerce OS for WhatsApp & the Web',
                'meta_description' => 'Sell physical & digital products, take bookings, run dine-in tables, collect M-Pesa/Paystack/Stripe, and grow with a storefront + WhatsApp AI agent — one platform.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'kicker' => 'FREE 14-DAY TRIAL',
                            'title' => 'Your AI commerce OS for WhatsApp — and beyond.',
                            'description' => 'Not a menu bot. A fluent agent that sells physical & digital products, takes bookings, runs dine-in tables, powers your storefront, collects payment, and hands off to your team when needed.',
                            'primaryCtaText' => 'Start free trial',
                            'primaryCtaHref' => '/register',
                            'secondaryCtaText' => 'Explore solutions',
                            'secondaryCtaHref' => '/solutions',
                            'showFlowSimulation' => true,
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'RelayIQ WhatsApp commerce agent illustration',
                        ],
                    ],
                    [
                        'section_key' => 'capabilities',
                        'label' => 'Capabilities grid',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Many solutions. One roof.',
                            'description' => 'Conversation, catalog, payments, bookings, dine-in, storefront, and growth — wired together so every channel sells the same way.',
                            'items' => [
                                ['icon' => 'bot', 'title' => 'AI commerce agent', 'description' => 'Fluent sales & support with memory — not rigid numbered menus.'],
                                ['icon' => 'package', 'title' => 'Sell anything', 'description' => 'Physical goods, digital files & licenses, and bookable services in one catalog.'],
                                ['icon' => 'payment', 'title' => 'Pay in the chat', 'description' => 'M-Pesa, Paystack, and Stripe — customers pay without leaving WhatsApp.'],
                                ['icon' => 'booking', 'title' => 'Bookings & services', 'description' => 'Qualify needs, share availability, and convert requests into confirmed bookings.'],
                                ['icon' => 'dinein', 'title' => 'Dine-in tables', 'description' => 'QR table ordering for restaurants — guest scans, orders, pays, kitchen notified.'],
                                ['icon' => 'store', 'title' => 'Web storefront', 'description' => 'A full shop for browsers: cart, checkout, coupons, tracking — same catalog as WhatsApp.'],
                                ['icon' => 'growth', 'title' => 'Growth Engine', 'description' => 'AI posts, social publishing, campaigns, and WhatsApp referral attribution.'],
                                ['icon' => 'inbox', 'title' => 'Team inbox', 'description' => 'AI handles the front line; humans take over any thread instantly.'],
                                ['icon' => 'delivery', 'title' => 'Delivery & taxes', 'description' => 'Zones, fees, and tax rules so quotes and checkout stay accurate.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'intro_card',
                        'label' => 'Intro card',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Not a chatbot. A commerce operating system.',
                            'description' => 'RelayIQ runs the full customer journey across WhatsApp and the web: discover → recommend → order or book → pay → fulfill (ship, download, or dine-in) → follow up. It learns from conversations so every reply gets sharper over time.',
                            'ctaText' => 'See all solutions',
                            'ctaHref' => '/solutions',
                            'imageUrl' => $introImage,
                            'imageAlt' => 'RelayIQ commerce OS overview',
                        ],
                    ],
                    [
                        'section_key' => 'feature_1',
                        'label' => 'Feature: AI + inbox',
                        'sort_order' => 4,
                        'content' => [
                            'label' => 'AI AGENT + TEAM INBOX',
                            'title' => 'Conversations that sell — with humans in control',
                            'description' => 'Your AI employee answers with facts from catalog, FAQ, and order history. It remembers preferences. When a customer needs a person, your team jumps in from one shared inbox — encryption and full history included.',
                            'ctaText' => 'Try free',
                            'ctaHref' => '/register',
                            'imageUrl' => $inboxImage,
                            'imageAlt' => 'Team inbox and AI conversation view',
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'feature_2',
                        'label' => 'Feature: Payments',
                        'sort_order' => 5,
                        'content' => [
                            'label' => 'PAYMENTS',
                            'title' => 'Get paid where the conversation happens',
                            'description' => 'Collect with M-Pesa STK, Paystack, or Stripe from the same chat thread. Merchants can use their own M-Pesa Till/PayBill. Orders mark paid automatically when payment succeeds.',
                            'ctaText' => 'See plans',
                            'ctaHref' => '/pricing',
                            'imageUrl' => $paymentsImage,
                            'imageAlt' => 'In-chat payment collection',
                            'imagePosition' => 'right',
                        ],
                    ],
                    [
                        'section_key' => 'feature_3',
                        'label' => 'Feature: Catalog types',
                        'sort_order' => 6,
                        'content' => [
                            'label' => 'CATALOG',
                            'title' => 'Physical, digital, and bookable — in one catalog',
                            'description' => 'Ship products, deliver download links and license keys after payment, or take service bookings. Your AI understands stock, variants, and fulfillment so customers get accurate answers every time.',
                            'ctaText' => 'Start selling',
                            'ctaHref' => '/register',
                            'imageUrl' => $introImage,
                            'imageAlt' => 'Product catalog covering physical digital and bookings',
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'feature_4',
                        'label' => 'Feature: Storefront + dine-in',
                        'sort_order' => 7,
                        'content' => [
                            'label' => 'STOREFRONT & DINE-IN',
                            'title' => 'Same catalog on the web — and at the table',
                            'description' => 'Publish a branded storefront for browsers (cart, checkout, coupons, order tracking). For restaurants, generate QR codes per table so guests order and pay without waiting for a waiter. WhatsApp stays the conversation channel; the store and tables are extra front doors.',
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
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'How to join',
                        'sort_order' => 9,
                        'content' => [
                            'title' => 'Go live in three steps',
                            'description' => 'From signup to your first AI-assisted sale — without a technical team.',
                            'ctaText' => 'Create your account',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Three steps to go live',
                            'steps' => [
                                ['title' => 'Connect WhatsApp', 'description' => 'Sign up, connect your WhatsApp Business number (Embedded Signup or Cloud API).'],
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
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 12,
                        'is_enabled' => true,
                        'content' => [
                            'title' => 'Frequently asked questions',
                            'description' => 'Straight answers about WhatsApp AI sales, payments, storefront, and plans.',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 13,
                        'content' => [
                            'title' => 'Put an AI employee on your WhatsApp today',
                            'description' => '14-day free trial on Starter and Growth. No credit card required to start. Explore solutions first if you want the full picture.',
                            'ctaText' => 'Start free trial',
                            'ctaHref' => '/register',
                            'imageUrl' => $ctaImage,
                            'imageAlt' => 'Start RelayIQ free trial',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'solutions',
                'title' => 'Solutions',
                'meta_title' => 'Solutions — RelayIQ AI Commerce OS',
                'meta_description' => 'Detailed solutions: AI chats, physical & digital catalog, payments, bookings, dine-in tables, web storefront, delivery, and growth — with sample flows built for conversion.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Every way you sell — under one roof',
                            'description' => 'WhatsApp AI, catalog, payments, bookings, dine-in, storefront, and growth are not add-ons. They share one brain, one inventory, and one team inbox so customers get a consistent experience wherever they start.',
                        ],
                    ],
                    [
                        'section_key' => 'outcomes',
                        'label' => 'Outcomes strip',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Built for operators who need results this week',
                            'description' => 'Start on WhatsApp. Turn on storefront, bookings, or dine-in when you are ready — without migrating catalogs again.',
                            'items' => [
                                ['value' => '9+', 'label' => 'Solution pillars', 'detail' => 'Chat, catalog, pay, book, dine-in, store, grow…'],
                                ['value' => '4', 'label' => 'Product types', 'detail' => 'Physical · Digital · Service · Bundle'],
                                ['value' => '3', 'label' => 'Front doors', 'detail' => 'WhatsApp · Web store · Table QR'],
                                ['value' => '14', 'label' => 'Day free trial', 'detail' => 'No card required to start'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'solution_pillars',
                        'label' => 'Solution pillars',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Solutions explained — with sample conversations',
                            'description' => 'Each pillar below shows what customers experience and what your team controls. Use these as demos when you pitch RelayIQ internally.',
                            'items' => [
                                [
                                    'id' => 'ai-chats',
                                    'icon' => 'bot',
                                    'label' => '01 · AI + TEAM INBOX',
                                    'title' => 'AI chats that sell — with humans in control',
                                    'description' => 'A fluent commerce agent answers from your catalog, FAQs, policies, and order history. When a VIP or exception appears, your team takes over from one shared inbox — full history, no copy-paste.',
                                    'points' => [
                                        'Memory of preferences and past purchases',
                                        'Human takeover / hand-back without losing context',
                                        'FAQ + catalog grounded answers (not generic chat)',
                                        'Works on official WhatsApp Business Cloud API',
                                    ],
                                    'sampleTitle' => 'Sample · After-hours retail',
                                    'sampleLines' => [
                                        'Do you have Air Runner in size 42?',
                                        'Yes — Air Runner (42) is in stock at KES 4,500. Want me to reserve and send an M-Pesa prompt?',
                                        'Yes please',
                                        'Order #4821 ready. STK push sent. Reply HELP anytime for a human.',
                                    ],
                                    'ctaText' => 'Start free trial',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'catalog',
                                    'icon' => 'package',
                                    'label' => '02 · CATALOG',
                                    'title' => 'Physical, digital, services & bundles',
                                    'description' => 'One catalog the AI understands: shippable goods with variants, digital downloads/license keys after payment, bookable services with duration, and bundles that mix them.',
                                    'points' => [
                                        'Variants, stock, and fulfillment instructions',
                                        'Digital file / license delivery after paid',
                                        'Service booking duration + booking page',
                                        'Same SKUs on WhatsApp and web storefront',
                                    ],
                                    'sampleTitle' => 'Sample · Digital product',
                                    'sampleLines' => [
                                        'I want the Photography Preset Pack',
                                        'Preset Pack — KES 1,999. Pay with M-Pesa or card and I’ll send your download link instantly.',
                                        'Paid',
                                        'Payment confirmed. Here’s your secure download + license key. Valid on 2 devices.',
                                    ],
                                    'ctaText' => 'See pricing',
                                    'ctaHref' => '/pricing',
                                ],
                                [
                                    'id' => 'payments',
                                    'icon' => 'payment',
                                    'label' => '03 · PAYMENTS',
                                    'title' => 'Get paid where the conversation happens',
                                    'description' => 'Collect with M-Pesa STK (including your Till/PayBill), Paystack, or Stripe. Orders flip to paid automatically when the gateway confirms — no chasing screenshots.',
                                    'points' => [
                                        'In-chat payment prompts',
                                        'Merchant-owned M-Pesa credentials supported',
                                        'Card payments via Stripe / Paystack',
                                        'Receipts and order status in one place',
                                    ],
                                    'sampleTitle' => 'Sample · M-Pesa checkout',
                                    'sampleLines' => [
                                        'Confirm my pizza order',
                                        'Total KES 2,798. I’ll send M-Pesa STK now — enter your PIN on your phone.',
                                        'Done',
                                        'Payment received. Order #1234 is confirmed. ETA 35 minutes.',
                                    ],
                                    'ctaText' => 'Start selling',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'bookings',
                                    'icon' => 'booking',
                                    'label' => '04 · BOOKINGS',
                                    'title' => 'Services that convert into calendar bookings',
                                    'description' => 'Qualify the need in chat, share availability, and land a confirmed booking — plus a public booking page for customers who prefer the web.',
                                    'points' => [
                                        'Service products with duration',
                                        'Public /book/{slug} booking experience',
                                        'Dashboard to manage appointments',
                                        'AI can route and confirm in WhatsApp',
                                    ],
                                    'sampleTitle' => 'Sample · Salon booking',
                                    'sampleLines' => [
                                        'Can I book a braids appointment Saturday?',
                                        'Saturday openings: 10:00, 13:00, 16:00. Braids = 90 min · KES 3,500. Which time?',
                                        '13:00',
                                        'Booked for Sat 13:00. Confirmation sent. Need to reschedule? Just reply here.',
                                    ],
                                    'ctaText' => 'Try free',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'dine-in',
                                    'icon' => 'dinein',
                                    'label' => '05 · DINE-IN',
                                    'title' => 'Table QR ordering for restaurants',
                                    'description' => 'Generate QR codes per table. Guests scan, browse the menu, order, and pay — kitchen and staff see the table name without a separate POS project.',
                                    'points' => [
                                        'Per-table QR tokens',
                                        'Fulfillment type: dine-in on orders',
                                        'Works with the same menu/catalog',
                                        'Pair with WhatsApp for takeaway & delivery',
                                    ],
                                    'sampleTitle' => 'Sample · Table 7',
                                    'sampleLines' => [
                                        '(Guest scans Table 7 QR)',
                                        'Welcome to Coastal Kitchen — Table 7. Tap to order from today’s menu.',
                                        '1× Fish + 2× passion juice',
                                        'Order placed for Table 7 · KES 1,850. Pay now or call a waiter from the page.',
                                    ],
                                    'ctaText' => 'Explore dine-in',
                                    'ctaHref' => '/register',
                                ],
                                [
                                    'id' => 'storefront',
                                    'icon' => 'store',
                                    'label' => '06 · STOREFRONT',
                                    'title' => 'A real web shop — same catalog as WhatsApp',
                                    'description' => 'Customers who prefer browsers get a classic storefront: search, cart, checkout, coupons, order tracking. You do not maintain two product lists.',
                                    'points' => [
                                        'Public /s/{slug} storefront',
                                        'Cart, checkout, coupons, tracking',
                                        'Link-in-bio companion page',
                                        'Attribution-ready for Growth campaigns',
                                    ],
                                    'sampleTitle' => 'Sample · Browser shopper',
                                    'sampleLines' => [
                                        'Opens store.yourbrand.com → adds Classic Tee',
                                        'Applies code WELCOME10 at checkout',
                                        'Pays with card / M-Pesa',
                                        'Order confirmed + tracking link emailed; WhatsApp can follow up later',
                                    ],
                                    'ctaText' => 'See all solutions',
                                    'ctaHref' => '#demos',
                                ],
                                [
                                    'id' => 'growth',
                                    'icon' => 'growth',
                                    'label' => '07 · GROWTH',
                                    'title' => 'Campaigns you can measure back to WhatsApp',
                                    'description' => 'Draft AI-assisted posts, publish to social, run WhatsApp campaigns, and track referral links so you know which content starts conversations and orders.',
                                    'points' => [
                                        'AI post generation with image support',
                                        'WhatsApp referral attribution links',
                                        'Broadcast campaigns from the dashboard',
                                        'Follow-ups that bring customers back',
                                    ],
                                    'sampleTitle' => 'Sample · Attribution',
                                    'sampleLines' => [
                                        'Customer taps Instagram story → wa.me link with /g/summer-sale',
                                        'Lands in WhatsApp already attributed to Summer Sale',
                                        'AI greets with the promo catalog',
                                        'Dashboard: Summer Sale → 42 chats → 11 paid orders',
                                    ],
                                    'ctaText' => 'Compare plans',
                                    'ctaHref' => '/pricing',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'demos',
                        'label' => 'Demo gallery',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'Ready-to-show demos',
                            'description' => 'Use these short scripts in sales calls or onboarding. They map 1:1 to product surfaces your team already has in the dashboard.',
                            'items' => [
                                [
                                    'badge' => 'Demo A',
                                    'channel' => 'WhatsApp',
                                    'title' => 'Order + M-Pesa in one thread',
                                    'description' => 'Retail or food — browse, confirm, pay, get ETA.',
                                    'steps' => [
                                        'Customer asks for a product or menu item',
                                        'AI shows options with prices',
                                        'Customer confirms qty / size',
                                        'STK push → paid → order number + ETA',
                                    ],
                                    'result' => 'Outcome: paid order without leaving WhatsApp.',
                                ],
                                [
                                    'badge' => 'Demo B',
                                    'channel' => 'WhatsApp + /book',
                                    'title' => 'Service booking end-to-end',
                                    'description' => 'Salons, clinics, consultants — qualify then lock a slot.',
                                    'steps' => [
                                        'Customer states service + preferred day',
                                        'AI lists open slots and price',
                                        'Customer picks a time',
                                        'Booking confirmed; optional deposit via payment link',
                                    ],
                                    'result' => 'Outcome: calendar booking + optional payment.',
                                ],
                                [
                                    'badge' => 'Demo C',
                                    'channel' => 'Table QR',
                                    'title' => 'Dine-in without waiting',
                                    'description' => 'Restaurant table ordering for busy lunch rushes.',
                                    'steps' => [
                                        'Print QR for each table from Dine-in',
                                        'Guest scans → menu for that table',
                                        'Adds items → places order',
                                        'Staff sees Table name + items in Orders',
                                    ],
                                    'result' => 'Outcome: faster turns, fewer missed tickets.',
                                ],
                                [
                                    'badge' => 'Demo D',
                                    'channel' => 'Web store',
                                    'title' => 'Storefront for browsers',
                                    'description' => 'Customers who will not open WhatsApp still buy.',
                                    'steps' => [
                                        'Share /s/your-slug or custom domain',
                                        'Shopper uses cart + coupon',
                                        'Checkout with supported gateways',
                                        'Track order on the public tracking page',
                                    ],
                                    'result' => 'Outcome: same catalog, second revenue channel.',
                                ],
                                [
                                    'badge' => 'Demo E',
                                    'channel' => 'WhatsApp',
                                    'title' => 'Digital delivery after pay',
                                    'description' => 'Creators and educators selling files or licenses.',
                                    'steps' => [
                                        'Customer selects a digital product',
                                        'Pays in chat',
                                        'System marks order paid',
                                        'Download / license delivered automatically',
                                    ],
                                    'result' => 'Outcome: instant fulfillment, zero shipping.',
                                ],
                                [
                                    'badge' => 'Demo F',
                                    'channel' => 'Growth',
                                    'title' => 'Campaign → chat → sale',
                                    'description' => 'Prove which social post started the conversation.',
                                    'steps' => [
                                        'Create Growth post + WhatsApp referral link',
                                        'Publish to Instagram / Facebook',
                                        'Customer taps link into WhatsApp',
                                        'Attribution shows on the conversation / order',
                                    ],
                                    'result' => 'Outcome: measurable ads-to-WhatsApp ROI.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'industries',
                        'label' => 'Industries',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Pick your industry playbook',
                            'description' => 'Same platform — different default pillars. Start with the path that matches how you already sell.',
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
                                    'ctaText' => 'Start restaurant trial',
                                    'ctaHref' => '/register',
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
                                    'ctaText' => 'Start retail trial',
                                    'ctaHref' => '/register',
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
                                    'ctaText' => 'Start services trial',
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
                                    'ctaText' => 'Start creator trial',
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
                            'title' => 'From blank account to first sale',
                            'description' => 'Most teams go live the same week. Turn on only the pillars you need on day one.',
                            'ctaText' => 'Create your account',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Go live steps',
                            'steps' => [
                                ['title' => 'Create account & connect WhatsApp', 'description' => '14-day trial. Connect with Embedded Signup or Cloud API credentials.'],
                                ['title' => 'Load catalog + turn on channels', 'description' => 'Add products (physical/digital/service). Enable storefront slug, bookings, or dine-in tables as needed.'],
                                ['title' => 'Invite your team & go live', 'description' => 'Set payment gateways, invite inbox agents, send a test order — then share your WhatsApp and store links.'],
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
                            'title' => 'Ready to run commerce under one roof?',
                            'description' => 'Start your free trial, connect WhatsApp, and turn on storefront, bookings, or dine-in when you need them.',
                            'ctaText' => 'Start free trial',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pricing',
                'title' => 'Pricing',
                'meta_title' => 'Pricing — RelayIQ',
                'meta_description' => 'Starter, Growth, and Enterprise plans for WhatsApp commerce. 14-day free trial on Starter and Growth.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Pricing that scales with your sales',
                            'description' => 'Start with a free trial. Upgrade when volume, bookings, storefront traffic, or Growth Engine needs grow.',
                        ],
                    ],
                    [
                        'section_key' => 'pricing_plans',
                        'label' => 'Pricing plans',
                        'sort_order' => 2,
                        'content' => [
                            'usePlansApi' => true,
                            'popularBadge' => 'Most Popular',
                        ],
                    ],
                    [
                        'section_key' => 'compare_features',
                        'label' => 'Compare features',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Compare features',
                            'columns' => [
                                [
                                    'name' => 'Starter',
                                    'features' => [
                                        'AI commerce agent + memory',
                                        'Physical & digital catalog',
                                        'Web storefront + link-in-bio',
                                        'M-Pesa / Paystack / Stripe',
                                        '5,000 messages / month',
                                        'Growth Engine (20 AI posts)',
                                        'WhatsApp campaigns (2 / mo)',
                                        'Up to 3 team seats',
                                        '14-day free trial',
                                    ],
                                ],
                                [
                                    'name' => 'Growth',
                                    'features' => [
                                        'Everything in Starter',
                                        'Bookings & services (50 / mo)',
                                        'Dine-in table QR',
                                        '50,000 messages / month',
                                        'Advanced AI + BYOK preferred',
                                        'Growth Engine (100 AI posts)',
                                        'WhatsApp campaigns (10 / mo)',
                                        'Analytics + API access',
                                        'Up to 10 team seats',
                                    ],
                                ],
                                [
                                    'name' => 'Enterprise',
                                    'features' => [
                                        'Everything in Growth',
                                        'Unlimited messages & bookings',
                                        'Custom AI models + company keys',
                                        'Growth Engine (500 AI posts)',
                                        'WhatsApp campaigns (50 / mo)',
                                        'Up to 50 team seats',
                                        'Onboarding & SLAs',
                                        'Contact sales (no self-serve trial)',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'faq',
                        'label' => 'FAQ',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'Frequently asked questions',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Ready to put AI on your WhatsApp?',
                            'description' => 'Start your trial and connect WhatsApp in minutes.',
                            'ctaText' => 'Start free trial',
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
                            'imageUrl' => $aboutTeamImage,
                            'imageAlt' => 'RelayIQ by Essem Digital Innovation Limited',
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
                                ['icon' => 'booking', 'title' => 'Bookings', 'description' => 'Qualify in chat and confirm appointments on Growth+.'],
                                ['icon' => 'dinein', 'title' => 'Dine-in tables', 'description' => 'QR ordering per table for restaurants on Growth+.'],
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
                                    'ctaHref' => '/solutions#catalog',
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
                            'description' => 'Skim Solutions for demos, check Pricing for what each plan unlocks, then start a trial.',
                            'ctaText' => 'Start free trial',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Next steps with RelayIQ',
                            'steps' => [
                                ['title' => 'See the full solution map', 'description' => 'Visit Solutions for pillars, sample chats, and industry playbooks.'],
                                ['title' => 'Match features to a plan', 'description' => 'Starter covers storefront + physical/digital. Growth adds bookings and dine-in.'],
                                ['title' => 'Start the 14-day trial', 'description' => 'Connect WhatsApp, load your catalog, and run a test order the same week.'],
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
                            'description' => 'Start free on Starter or Growth — or talk to us about Enterprise.',
                            'ctaText' => 'Get started',
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
                            'imageUrl' => $contactImage,
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
                            'description' => 'Start a free trial and explore the AI commerce OS on WhatsApp.',
                            'ctaText' => 'Start free trial',
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
<p>RelayIQ is a product of Essem Digital Innovation Limited. We provide a multi-tenant SaaS platform for WhatsApp business messaging, AI-assisted replies, order management, and related services. Learn more at <a href="https://essemdigital.com" target="_blank" rel="noopener noreferrer">essemdigital.com</a>.</p>
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
<p>Depending on your jurisdiction, you may have rights to access, correct, or delete personal data. Contact us at support@essemdigital.com to submit a request.</p>
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
<p>For questions about these terms, contact support@essemdigital.com.</p>',
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
            ['question' => 'How does RelayIQ work on WhatsApp?', 'answer' => 'Connect your WhatsApp Business number, add your catalog and FAQs, and our AI commerce agent handles replies, orders, payments, and handoff to your team — with memory that improves over time.'],
            ['question' => 'What can I sell?', 'answer' => 'Physical products, digital goods (download links and license keys), bookable services, and bundles — all from one catalog the AI understands. The same catalog powers WhatsApp, your web storefront, and dine-in QR menus.'],
            ['question' => 'Do you only work on WhatsApp?', 'answer' => 'WhatsApp is the primary conversation channel, but RelayIQ also includes a full web storefront, public booking pages, and dine-in table QR ordering — so customers can buy wherever they prefer.'],
            ['question' => 'What is dine-in / table QR?', 'answer' => 'For restaurants, you create tables and print QR codes. Guests scan, order from your menu, and staff see the table name on the order. Pair it with WhatsApp for delivery and takeaway.'],
            ['question' => 'What payment methods are supported?', 'answer' => 'M-Pesa (including your own Till/PayBill), Paystack, and Stripe. Customers can pay in the conversation flow or on the storefront checkout.'],
            ['question' => 'Is there a free trial?', 'answer' => 'Yes. Starter and Growth include a 14-day free trial. Enterprise is custom — contact sales.'],
            ['question' => 'Can my team take over conversations?', 'answer' => 'Yes. Agents can jump into any thread from the team inbox. The AI pauses until you hand the chat back.'],
            ['question' => 'What is the Growth Engine?', 'answer' => 'AI-assisted posts, social publishing, WhatsApp campaigns, and referral attribution so you can measure which content drives chats and orders.'],
            ['question' => 'Where do I sign up?', 'answer' => 'Click Sign up in the navigation or visit /register to create your account and start your free trial. Prefer the full map first? Visit /solutions.'],
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
