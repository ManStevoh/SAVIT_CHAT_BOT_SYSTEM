<?php

namespace App\Support;

/**
 * Indexable SEO landings that render even when CMS rows are missing on production.
 *
 * @phpstan-type Faq array{id: string, question: string, answer: string}
 * @phpstan-type Landing array{
 *     path: string,
 *     title: string,
 *     description: string,
 *     h1: string,
 *     lede: string,
 *     html: string,
 *     ctaTitle: string,
 *     ctaDescription: string,
 *     faqs: list<Faq>
 * }
 */
class SeoLandingCatalog
{
    /**
     * @return array<string, Landing>
     */
    public static function all(): array
    {
        static $landings = null;

        return $landings ??= self::define();
    }

    /**
     * @return Landing|null
     */
    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function path(string $slug): ?string
    {
        $landing = self::get($slug);

        return is_array($landing) ? $landing['path'] : null;
    }

    /**
     * @return list<Faq>
     */
    public static function faqs(string $slug): array
    {
        return self::get($slug)['faqs'] ?? [];
    }

    /**
     * Inertia/CMS payload shape used by LandoCmsPage.
     *
     * @return array<string, mixed>|null
     */
    public static function payload(string $slug): ?array
    {
        if ($slug === 'features') {
            return FeaturesPageCopy::payload();
        }

        $landing = self::get($slug);
        if ($landing === null) {
            return null;
        }

        return [
            'page' => [
                'slug' => $slug,
                'title' => $landing['h1'],
                'metaTitle' => $landing['title'],
                'metaDescription' => $landing['description'],
                'ogImage' => null,
                'ogTitle' => $landing['title'],
                'ogDescription' => $landing['description'],
                'canonicalUrl' => null,
                'robots' => 'index, follow',
            ],
            'sections' => [
                [
                    'key' => 'hero',
                    'label' => 'Hero',
                    'isEnabled' => true,
                    'sortOrder' => 1,
                    'content' => [
                        'usePageHero' => true,
                        'title' => $landing['h1'],
                        'description' => $landing['lede'],
                    ],
                ],
                [
                    'key' => 'prose',
                    'label' => 'Article',
                    'isEnabled' => true,
                    'sortOrder' => 2,
                    'content' => [
                        'title' => '',
                        'html' => $landing['html'],
                    ],
                ],
                [
                    'key' => 'faq',
                    'label' => 'FAQ',
                    'isEnabled' => true,
                    'sortOrder' => 3,
                    'content' => ['title' => 'Frequently asked questions'],
                ],
                [
                    'key' => 'cta',
                    'label' => 'CTA',
                    'isEnabled' => true,
                    'sortOrder' => 4,
                    'content' => [
                        'title' => $landing['ctaTitle'],
                        'description' => $landing['ctaDescription'],
                        'ctaText' => 'Get started free',
                        'ctaHref' => '/register',
                    ],
                ],
            ],
            'faqs' => $landing['faqs'],
        ];
    }

    /**
     * Extra footer links merged when CMS nav is missing them.
     *
     * @return list<array{label: string, href: string}>
     */
    public static function footerLinks(): array
    {
        return [
            ['label' => 'AI Sales Agent Kenya', 'href' => '/ai-sales-agent-kenya'],
            ['label' => 'M-Pesa on WhatsApp', 'href' => '/whatsapp-mpesa'],
            ['label' => 'Kenya WhatsApp playbook', 'href' => '/case-study/kenya-whatsapp-mpesa'],
            ['label' => 'Restaurants', 'href' => '/solutions/restaurants'],
            ['label' => 'Retail', 'href' => '/solutions/retail'],
        ];
    }

    /**
     * @return array<string, Landing>
     */
    private static function define(): array
    {
        return [
            'features' => self::page(
                '/features',
                FeaturesPageCopy::title(),
                FeaturesPageCopy::description(),
                FeaturesPageCopy::h1(),
                FeaturesPageCopy::lede(),
                <<<'HTML'
<p>RelayIQ is one catalog with four doors: a <a href="/whatsapp-ai-sales-agent">WhatsApp AI sales agent</a>, a web storefront, bookings, and dine-in QR. Payments, inbox, and follow-up sit behind all of them.</p>
<p>Start on the <a href="/pricing">free Starter plan</a>. See <a href="/solutions">solutions</a> or the <a href="/ai-sales-agent-kenya">Kenya playbook</a>.</p>
HTML
            ),
            'whatsapp-ai-sales-agent' => self::page(
                '/whatsapp-ai-sales-agent',
                'AI Sales Agent for WhatsApp — RelayIQ',
                'RelayIQ’s AI sales agent answers customers, recommends products, qualifies leads, follows up, and takes payment on WhatsApp.',
                'AI sales agent for WhatsApp',
                'Turn chats into revenue. RelayIQ’s AI sales agent answers instantly, recommends from your real catalog, and hands off to your team when a human should close.',
                <<<'HTML'
<p>A WhatsApp AI sales agent holds real sales conversations — not a numbered menu. It understands your catalog, answers product questions, recommends items, collects order details, and can trigger payment, while your team stays one click away.</p>
<h2>How RelayIQ’s agent sells</h2>
<ul>
<li><strong>Engage</strong> — Instant replies to inbound WhatsApp messages</li>
<li><strong>Recommend</strong> — Catalog-aware product and service suggestions</li>
<li><strong>Qualify</strong> — Capture needs, budget signals, and lead details</li>
<li><strong>Follow up</strong> — Reminders that bring buyers back</li>
<li><strong>Convert</strong> — In-chat checkout with <a href="/whatsapp-mpesa">M-Pesa</a>, Paystack, or Stripe</li>
<li><strong>Handoff</strong> — Humans take over VIP deals from the team inbox</li>
</ul>
<p>Compared with a basic chatbot, an AI sales agent is measured on sales outcomes. See <a href="/whatsapp-chatbot-vs-ai-sales-agent">chatbot vs AI sales agent</a>, the <a href="/ai-sales-agent-kenya">Kenya playbook</a>, or <a href="/pricing">plans</a>.</p>
HTML
            ),
            'whatsapp-sales-automation' => self::page(
                '/whatsapp-sales-automation',
                'WhatsApp Sales Automation — RelayIQ',
                'Automate WhatsApp sales with AI replies, product recommendations, follow-ups, and in-chat M-Pesa or card payments.',
                'WhatsApp sales automation',
                'Automate the repetitive parts of WhatsApp selling — answers, stock checks, orders, and payment — without losing human control.',
                <<<'HTML'
<p>WhatsApp sales automation means the thread does not stall when your team is packing orders. RelayIQ automates FAQs, catalog lookup, order capture, and checkout, then routes hot buyers to a person.</p>
<h2>What to automate first</h2>
<ol>
<li>Price, stock, and delivery questions</li>
<li>Order taking — size, quantity, location</li>
<li>Payment prompts (<a href="/whatsapp-mpesa">M-Pesa STK</a>)</li>
<li>Follow-ups for quiet chats — <a href="/use-cases/follow-ups">follow-up playbook</a></li>
</ol>
<p>Kenyan shops running ads into WhatsApp also need attribution and a shared inbox. Start with the <a href="/whatsapp-ai-sales-agent">AI sales agent</a> or the <a href="/use-cases/order-taking">order-taking use case</a>.</p>
HTML
            ),
            'whatsapp-chatbot' => self::page(
                '/whatsapp-chatbot',
                'WhatsApp Chatbot for Sales — RelayIQ',
                'A WhatsApp chatbot built for sales: catalog-aware AI, orders, M-Pesa, and human handoff — not a dead-end menu.',
                'WhatsApp chatbot for sales',
                'If you searched for a WhatsApp chatbot, you probably need something that can sell — not only deflect tickets.',
                <<<'HTML'
<p>Classic WhatsApp chatbots push buttons and scripts. Buyers type “nataka hii” with a photo. RelayIQ’s chatbot layer is an <a href="/whatsapp-ai-sales-agent">AI sales agent</a> grounded in your products, prices, and policies.</p>
<h2>Sales chatbot vs menu bot</h2>
<ul>
<li>Natural language (English and everyday Kenyan WhatsApp mix)</li>
<li>Live catalog instead of hardcoded lists</li>
<li>Checkout in the same chat</li>
<li>Human takeover without losing history</li>
</ul>
<p>Read the full comparison: <a href="/whatsapp-chatbot-vs-ai-sales-agent">WhatsApp chatbot vs AI sales agent</a>. For shops in Kenya see <a href="/ai-sales-agent-kenya">AI sales agent Kenya</a>.</p>
HTML
            ),
            'whatsapp-commerce' => self::page(
                '/whatsapp-commerce',
                'WhatsApp Commerce Platform — RelayIQ',
                'Run WhatsApp commerce with catalog, in-chat payments, orders, and a matching web storefront from one inventory.',
                'WhatsApp commerce platform',
                'Conversation commerce plus a real storefront — one catalog, two front doors, payments that do not leave the buyer hanging.',
                <<<'HTML'
<p>WhatsApp commerce is selling where the customer already talks to you. RelayIQ adds a storefront, bookings, and dine-in QR so browser shoppers and table guests use the same SKUs.</p>
<h2>One inventory, three doors</h2>
<ul>
<li>WhatsApp for advice and <a href="/whatsapp-mpesa">M-Pesa checkout</a></li>
<li>Web storefront for search, cart, and coupons</li>
<li>Table QR for restaurants — <a href="/solutions/restaurants">restaurant playbook</a></li>
</ul>
<p>See <a href="/whatsapp-for-ecommerce">WhatsApp for ecommerce</a> and <a href="/solutions/retail">retail</a>.</p>
HTML
            ),
            'whatsapp-lead-generation' => self::page(
                '/whatsapp-lead-generation',
                'WhatsApp Lead Generation — RelayIQ',
                'Generate and qualify WhatsApp leads with AI, then route ready buyers to your team inbox.',
                'WhatsApp lead generation',
                'Every chat becomes a contact with intent — not a screenshot buried in someone’s phone.',
                <<<'HTML'
<p>WhatsApp lead generation only works if you capture name, need, and readiness before the buyer goes quiet. RelayIQ logs every conversation, qualifies budget and timing, and hands hot leads to a human.</p>
<p>Pair with <a href="/use-cases/lead-generation">the lead-generation use case</a>, <a href="/use-cases/follow-ups">follow-ups</a>, and <a href="/whatsapp-ai-sales-agent">the sales agent</a>.</p>
HTML
            ),
            'ai-customer-service' => self::page(
                '/ai-customer-service',
                'WhatsApp Customer Service Automation — RelayIQ',
                'Automate WhatsApp customer service with AI grounded in FAQs, orders, and catalog — then escalate to your team.',
                'WhatsApp customer service automation',
                'Answer “where is my order?” from real data. Escalate refunds and exceptions to a person with full context.',
                <<<'HTML'
<p>Customer-service automation on WhatsApp fails when the bot invents tracking numbers. RelayIQ answers from FAQs, order history, and catalog, then opens the team inbox for anything sensitive.</p>
<p>Related: <a href="/whatsapp-ai-sales-agent">sales agent</a>, <a href="/solutions/services">services</a>, <a href="/ai-sales-agent-kenya">Kenya</a>.</p>
HTML
            ),
            'whatsapp-for-ecommerce' => self::page(
                '/whatsapp-for-ecommerce',
                'WhatsApp for Ecommerce — RelayIQ',
                'Use WhatsApp for ecommerce sales with AI advice, checkout, and a matching web storefront from one catalog.',
                'WhatsApp for ecommerce',
                'Advice on WhatsApp. Browse on the web. Same prices, stock, and payments.',
                <<<'HTML'
<p>Ecommerce brands lose sales when Instagram DMs, WhatsApp, and the website disagree on price. RelayIQ keeps one catalog for chat and <a href="/whatsapp-commerce">WhatsApp commerce</a> storefronts.</p>
<p>Retail playbook: <a href="/solutions/retail">/solutions/retail</a>. Payments: <a href="/whatsapp-mpesa">M-Pesa</a>.</p>
HTML
            ),
            'ai-sales-assistant' => self::page(
                '/ai-sales-assistant',
                'AI Sales Assistant on WhatsApp — RelayIQ',
                'An AI sales assistant for WhatsApp that answers, recommends, takes orders, and collects payment — for businesses that sell in chat.',
                'AI sales assistant on WhatsApp',
                'RelayIQ is the AI sales assistant that sits on your WhatsApp Business number: catalog, orders, M-Pesa, and a human inbox.',
                <<<'HTML'
<p>An AI sales assistant is a teammate on the line your customers already message. RelayIQ answers product questions, builds the order, sends payment, and steps aside when you pick up the chat.</p>
<p>If you are in Kenya, start at <a href="/ai-sales-agent-kenya">AI sales agent for Kenyan businesses</a>. Compare bots vs agents: <a href="/whatsapp-chatbot-vs-ai-sales-agent">chatbot vs AI sales agent</a>.</p>
HTML
            ),
            'ai-sales-agent-kenya' => self::page(
                '/ai-sales-agent-kenya',
                'AI Sales Agent for Kenyan Businesses — RelayIQ',
                'AI sales agent for Kenyan businesses on WhatsApp: English and Swahili chats, live catalog, M-Pesa STK, storefront, and human handoff.',
                'An AI sales agent for Kenyan businesses',
                'RelayIQ is an AI WhatsApp sales platform for African businesses. It answers customers, takes orders, and sends M-Pesa prompts on the number they already message.',
                <<<'HTML'
<p>Kenyan buyers do not leave WhatsApp to fill a web form. They ask “uko na size 42?”, mix English and Kiswahili, and pay with M-Pesa. RelayIQ is built for that loop — not a generic global chatbot with Kenya mentioned in a footer.</p>
<h2>What an AI sales agent does on a Kenyan line</h2>
<ul>
<li>Reads intent even with typos and mixed language</li>
<li>Quotes live prices and stock from your catalog (KES)</li>
<li>Sends <a href="/whatsapp-mpesa">Lipa Na M-Pesa STK</a> in the same thread — Till or PayBill you own</li>
<li>Hands off to you for deposits, disputes, and VIP wholesale</li>
<li>Keeps a web storefront and, for restaurants, <a href="/solutions/restaurants">table QR ordering</a></li>
</ul>
<h2>Why this is not just another Kenya chatbot page</h2>
<p>Many tools only auto-reply. RelayIQ is commerce infrastructure: catalog, payments, bookings, dine-in, and inbox. That is how a boutique, a salon, and a café can share one system without stitching five apps.</p>
<h2>How Kenyan teams go live</h2>
<ol>
<li>Connect WhatsApp Business (Embedded Signup / Cloud API)</li>
<li>Load products, services, or a menu</li>
<li>Connect M-Pesa credentials so money hits your Till/PayBill</li>
<li>Invite inbox agents; AI handles the night shift</li>
</ol>
<p>Walk through a typical Nairobi retail day (anonymized playbook, not a named customer): <a href="/case-study/kenya-whatsapp-mpesa">WhatsApp + M-Pesa playbook for Kenyan retailers</a>.</p>
<p>Starter is free forever. See <a href="/pricing">pricing</a>, <a href="/whatsapp-ai-sales-agent">how the agent sells</a>, or <a href="/solutions/retail">retail</a>.</p>
HTML
                ,
                [
                    ['id' => 'ke-1', 'question' => 'Does RelayIQ work for businesses in Kenya?', 'answer' => 'Yes. WhatsApp Cloud API, KES catalog pricing, and M-Pesa STK (including merchant-owned Till or PayBill) are first-class. English and everyday Kenyan chat mix are expected.'],
                    ['id' => 'ke-2', 'question' => 'Do customers pay with M-Pesa inside WhatsApp?', 'answer' => 'Yes. The agent can send an STK push in the same thread so the buyer enters a PIN on their phone. Orders mark paid when the gateway confirms — no screenshot chasing.'],
                    ['id' => 'ke-3', 'question' => 'Do I need a new WhatsApp number?', 'answer' => 'No. You connect the WhatsApp Business number customers already have. Your team can take over any chat from the shared inbox.'],
                    ['id' => 'ke-4', 'question' => 'How is this different from other Kenya WhatsApp AI tools?', 'answer' => 'RelayIQ is a commerce OS: the same catalog powers WhatsApp, a web storefront, bookings, and dine-in QR — not replies-only automation.'],
                ]
            ),
            'whatsapp-mpesa' => self::page(
                '/whatsapp-mpesa',
                'M-Pesa Checkout on WhatsApp — RelayIQ',
                'Accept M-Pesa STK payments inside WhatsApp. Customers enter a PIN on their phone; orders mark paid automatically.',
                'M-Pesa checkout on WhatsApp',
                'Keep the sale in the thread. RelayIQ sends Lipa Na M-Pesa STK from the same chat that recommended the product.',
                <<<'HTML'
<p>Customers who leave WhatsApp to pay often never come back. In-chat M-Pesa keeps momentum for Kenyan and East African sellers.</p>
<h2>How STK works in RelayIQ</h2>
<ol>
<li>The <a href="/whatsapp-ai-sales-agent">AI sales agent</a> confirms item, qty, and total in KES</li>
<li>RelayIQ sends Lipa Na M-Pesa Online (STK push) to the buyer’s phone</li>
<li>They enter their PIN; the gateway confirms</li>
<li>The order flips to paid — receipt in the thread, no screenshot bookkeeping</li>
</ol>
<p>Use your own Till or PayBill. Platform defaults exist if you are testing. Pair with <a href="/ai-sales-agent-kenya">AI sales agent Kenya</a> and <a href="/use-cases/order-taking">order taking</a>.</p>
HTML
                ,
                [
                    ['id' => 'mp-1', 'question' => 'Can money go to my Till or PayBill?', 'answer' => 'Yes. Add your Lipa Na M-Pesa shortcode and passkey in company settings so STK credits your business, not a platform wallet.'],
                    ['id' => 'mp-2', 'question' => 'What if STK fails?', 'answer' => 'The agent can retry or offer card via Paystack/Stripe. Failed prompts are visible on the order so your team can follow up.'],
                    ['id' => 'mp-3', 'question' => 'Is this only for Kenya?', 'answer' => 'M-Pesa STK is for Kenyan numbers. The same checkout stack supports Paystack, Stripe, Flutterwave, and Pesapal for other markets.'],
                ]
            ),
            'solutions-restaurants' => self::page(
                '/solutions/restaurants',
                'WhatsApp Ordering for Restaurants — RelayIQ',
                'Restaurant WhatsApp ordering, table QR dine-in, and M-Pesa in one menu. Delivery, takeaway, and the floor share inventory.',
                'WhatsApp ordering for restaurants',
                'Menus on WhatsApp for delivery and takeaway. QR codes per table for dine-in. One kitchen ticket flow.',
                <<<'HTML'
<p>RelayIQ is built for Kenyan and African restaurants that already take orders on WhatsApp and lose tickets during rush.</p>
<ul>
<li>WhatsApp: “niko na two chicken, niletee South B”</li>
<li>Dine-in: guest scans Table 7 QR, orders, pays M-Pesa</li>
<li>Same menu as the web storefront</li>
</ul>
<p>See the hub at <a href="/solutions">solutions</a>, payments at <a href="/whatsapp-mpesa">M-Pesa</a>, or <a href="/ai-sales-agent-kenya">Kenya</a>.</p>
HTML
            ),
            'solutions-retail' => self::page(
                '/solutions/retail',
                'WhatsApp Commerce for Retail Shops — RelayIQ',
                'Retail WhatsApp sales: stock answers, variants, orders, M-Pesa, and a web storefront from one catalog.',
                'WhatsApp commerce for retail shops',
                'Answer “bado iko?” with real stock. Take the order and payment before the buyer messages the next boutique.',
                <<<'HTML'
<p>Fashion, cosmetics, electronics, and hardware shops live in WhatsApp. RelayIQ quotes live variants, takes the order, and can send M-Pesa — plus a storefront for customers who prefer a browser.</p>
<p>Related: <a href="/whatsapp-for-ecommerce">WhatsApp for ecommerce</a>, <a href="/use-cases/order-taking">order taking</a>, <a href="/ai-sales-agent-kenya">Kenya</a>.</p>
HTML
            ),
            'solutions-ecommerce' => self::page(
                '/solutions/ecommerce',
                'WhatsApp Ecommerce Platform — RelayIQ',
                'Ecommerce on WhatsApp and the web from one catalog: AI sales, checkout, coupons, and order tracking.',
                'Ecommerce on WhatsApp and the web',
                'Ads and SEO can land on a storefront. DMs still close on WhatsApp. Inventory stays in sync.',
                <<<'HTML'
<p>Pure chat brands hit a ceiling when they need Google-able product pages. RelayIQ adds <a href="/whatsapp-commerce">WhatsApp commerce</a> plus a classic cart without a second catalog.</p>
<p>Retail shops: <a href="/solutions/retail">retail playbook</a>. Digital goods: <a href="/whatsapp-for-ecommerce">WhatsApp for ecommerce</a>.</p>
HTML
            ),
            'solutions-services' => self::page(
                '/solutions/services',
                'WhatsApp Booking for Services — RelayIQ',
                'Salons, clinics, tutors, and consultants: qualify in WhatsApp, book a slot, collect an M-Pesa deposit.',
                'WhatsApp booking for service businesses',
                'Less back-and-forth on Saturday availability. Public booking page for customers who want a link, not a chat.',
                <<<'HTML'
<p>Service businesses lose money to “una slot lini?” ping-pong. RelayIQ qualifies the service, shows open times, and can take a deposit via <a href="/whatsapp-mpesa">M-Pesa</a>.</p>
<p>Kenya: <a href="/ai-sales-agent-kenya">AI sales agent Kenya</a>. Hub: <a href="/solutions">solutions</a>.</p>
HTML
            ),
            'use-cases-order-taking' => self::page(
                '/use-cases/order-taking',
                'Take Orders Automatically on WhatsApp — RelayIQ',
                'Take product and menu orders on WhatsApp automatically: item, quantity, delivery, and M-Pesa or card payment.',
                'Take orders automatically on WhatsApp',
                'From “na hiyo red dress” to a paid order without copying details into a notebook.',
                <<<'HTML'
<p>Order taking is the core WhatsApp use case for retail and restaurants. RelayIQ captures line items from your catalog, delivery notes, and payment in one thread.</p>
<p>See <a href="/solutions/retail">retail</a>, <a href="/solutions/restaurants">restaurants</a>, and <a href="/whatsapp-mpesa">M-Pesa</a>.</p>
HTML
            ),
            'use-cases-follow-ups' => self::page(
                '/use-cases/follow-ups',
                'AI WhatsApp Follow-Ups — RelayIQ',
                'Follow up WhatsApp leads automatically when a buyer goes quiet — inside business hours, grounded in the last SKU they asked about.',
                'AI follow-ups for WhatsApp leads',
                'Most chats die after “let me think.” RelayIQ nudges with the actual product, not a generic blast.',
                <<<'HTML'
<p>Follow-ups only work if they are specific and timed. RelayIQ uses conversation context and optional campaigns so you are not grepping old chats at midnight.</p>
<p>Leads: <a href="/whatsapp-lead-generation">lead generation</a>. Agent: <a href="/whatsapp-ai-sales-agent">AI sales agent</a>.</p>
HTML
            ),
            'use-cases-lead-generation' => self::page(
                '/use-cases/lead-generation',
                'Capture WhatsApp Leads Automatically — RelayIQ',
                'Capture and qualify WhatsApp leads automatically, then route ready buyers to your sales team.',
                'Capture WhatsApp leads automatically',
                'Every enquiry becomes a contact with intent — ready for your closer, not lost in a personal phone.',
                <<<'HTML'
<p>Lead generation on WhatsApp is a use case, not a slogan. RelayIQ logs the chat, tags readiness, and escalates when someone is ready to pay.</p>
<p>Product page: <a href="/whatsapp-lead-generation">WhatsApp lead generation</a>. Kenya: <a href="/ai-sales-agent-kenya">AI sales agent Kenya</a>.</p>
HTML
            ),
            'whatsapp-chatbot-vs-ai-sales-agent' => self::page(
                '/whatsapp-chatbot-vs-ai-sales-agent',
                'WhatsApp Chatbot vs AI Sales Agent — RelayIQ',
                'WhatsApp chatbot vs AI sales agent: menus vs catalog-aware selling, tickets vs paid orders, scripts vs M-Pesa checkout.',
                'WhatsApp chatbot vs AI sales agent',
                'A chatbot answers the question in front of it and stops. An AI sales agent behaves like a junior salesperson on WhatsApp.',
                <<<'HTML'
<p>Search “WhatsApp chatbot” and you will find menu builders. Search “AI sales agent” and you should get a system that checks stock, qualifies budget, and closes payment.</p>
<h2>Side by side</h2>
<ul>
<li><strong>Chatbot</strong> — scripted buttons, generic FAQs, no live catalog</li>
<li><strong>AI sales agent</strong> — natural language, real prices, <a href="/whatsapp-mpesa">M-Pesa</a>, human handoff</li>
</ul>
<p>RelayIQ is built as an agent. Read <a href="/whatsapp-ai-sales-agent">AI sales agent for WhatsApp</a> and the <a href="/whatsapp-chatbot">chatbot for sales</a> page. Kenya-specific: <a href="/ai-sales-agent-kenya">AI sales agent for Kenyan businesses</a>.</p>
HTML
                ,
                [
                    ['id' => 'vs-1', 'question' => 'Can I start with a chatbot and upgrade?', 'answer' => 'You start with the same RelayIQ agent. Turn on catalog, payments, and handoff when you are ready — you do not migrate to a second product.'],
                    ['id' => 'vs-2', 'question' => 'Will customers know it is automated?', 'answer' => 'Replies are grounded in your products and tone. Your team can take over any thread; the buyer stays on the same WhatsApp number.'],
                ]
            ),
            'kenya-whatsapp-mpesa-playbook' => self::page(
                '/case-study/kenya-whatsapp-mpesa',
                'WhatsApp + M-Pesa Playbook for Kenyan Retailers — RelayIQ',
                'Typical operator walkthrough: how a Kenyan retailer answers WhatsApp, quotes KES stock, and collects M-Pesa STK in the same thread. Not a named customer testimonial.',
                'How a Kenyan retailer closes WhatsApp sales with M-Pesa',
                'This is a typical-operator playbook for Nairobi and Kenyan retail — not a case study about a named company, and not a fabricated testimonial.',
                <<<'HTML'
<p><strong>How to read this page:</strong> RelayIQ does not invent named customers or percentage lifts. The flow below is what we build for Kenyan shops that already sell on WhatsApp and get paid on M-Pesa.</p>
<h2>The day, in one thread</h2>
<ol>
<li>A buyer messages the shop WhatsApp: “uko na size 42, nyeusi?” — often mixed English and Kiswahili, often with a screenshot.</li>
<li>The <a href="/whatsapp-ai-sales-agent">AI sales agent</a> answers from live catalog (KES, variants, stock), not a numbered menu.</li>
<li>The buyer says “nipe two.” The agent confirms item, quantity, delivery estate, and total.</li>
<li><a href="/whatsapp-mpesa">Lipa Na M-Pesa STK</a> hits the buyer’s phone. PIN on device. Order marks paid when Daraja confirms — no “send screenshot of M-Pesa SMS.”</li>
<li>If the buyer argues a deposit or a wholesale price, the shop owner takes over the same chat from the shared inbox.</li>
</ol>
<h2>What has to be true for this to work</h2>
<ul>
<li>WhatsApp Business Cloud API on the number customers already have</li>
<li>Catalog with real sizes, colours, and stock</li>
<li>Merchant-owned Till or PayBill so money lands in the shop, not a platform wallet</li>
<li>A human inbox for disputes, VIPs, and “nitakulipa kesho”</li>
</ul>
<p>That is the Kenya loop RelayIQ is built for. Product pages: <a href="/ai-sales-agent-kenya">AI sales agent for Kenyan businesses</a>, <a href="/whatsapp-mpesa">M-Pesa on WhatsApp</a>, <a href="/solutions/retail">retail</a>. Trial: <a href="/pricing">pricing</a>.</p>
HTML
                ,
                [
                    ['id' => 'pb-1', 'question' => 'Is this a real named customer story?', 'answer' => 'No. It is a typical Kenyan retail walkthrough so searchers and operators can see the WhatsApp + M-Pesa loop without a fabricated testimonial.'],
                    ['id' => 'pb-2', 'question' => 'Does RelayIQ work if we already have a Till number?', 'answer' => 'Yes. Connect your Lipa Na M-Pesa shortcode and passkey so STK credits your business.'],
                    ['id' => 'pb-3', 'question' => 'Can a person take over mid-chat?', 'answer' => 'Yes. The shared inbox keeps history. AI pauses when a teammate joins.'],
                ]
            ),
        ];
    }

    /**
     * @param  list<Faq>|null  $faqs
     * @return Landing
     */
    private static function page(
        string $path,
        string $title,
        string $description,
        string $h1,
        string $lede,
        string $html,
        ?array $faqs = null,
    ): array {
        $faqs ??= [
            [
                'id' => '1',
                'question' => 'Is there a free plan?',
                'answer' => 'Yes. Starter is free forever: storefront, bookings, dine-in (5 tables), and 20 products. No credit card required. Growth is KSh 2,000/month if you need more capacity.',
            ],
            [
                'id' => '2',
                'question' => 'Does it work on WhatsApp Business?',
                'answer' => 'Yes. RelayIQ connects via official WhatsApp Cloud API / Embedded Signup on the number customers already message.',
            ],
            [
                'id' => '3',
                'question' => 'Can my team take over a chat?',
                'answer' => 'Yes. The shared inbox keeps full history. AI pauses when a human joins.',
            ],
        ];

        return [
            'path' => $path,
            'title' => $title,
            'description' => $description,
            'h1' => $h1,
            'lede' => $lede,
            'html' => $html,
            'ctaTitle' => 'Put an AI employee on your WhatsApp',
            'ctaDescription' => 'Starter is free forever. Connect WhatsApp and start selling.',
            'faqs' => $faqs,
        ];
    }
}
