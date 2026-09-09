<?php

namespace App\Support;

/**
 * Features page source of truth. Applied when CMS still has the old
 * "WhatsApp sales automation features" wall of commas, or the
 * duplicate six-group catalog that retold the same four doors.
 */
final class FeaturesPageCopy
{
    public static function title(): string
    {
        return 'WhatsApp AI, Storefront, Bookings & Dine-in Features — RelayIQ';
    }

    public static function description(): string
    {
        return 'Clear list of what RelayIQ includes: WhatsApp AI sales agent, web storefront, bookings, dine-in QR, M-Pesa checkout, team inbox, and follow-ups. One catalog. Start free.';
    }

    public static function h1(): string
    {
        return 'See exactly what you can turn on';
    }

    public static function lede(): string
    {
        return 'One catalog. Four ways customers already buy. Switch on WhatsApp, a shop link, bookings, or table QR this week — add the rest when you need them.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function shouldReplace(array $payload): bool
    {
        $hero = '';
        $hasVisualFeatures = false;
        $hasDuplicateCatalog = false;

        foreach ($payload['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $key = (string) ($section['key'] ?? '');
            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            if ($key === 'hero') {
                $hero = mb_strtolower(trim((string) ($content['title'] ?? $content['headline'] ?? '')));
            }
            if ($key === 'feature_1') {
                $hasVisualFeatures = true;
            }
            if ($key === 'feature_catalog') {
                $title = mb_strtolower((string) ($content['title'] ?? ''));
                if (str_contains($title, 'full feature list')) {
                    $hasDuplicateCatalog = true;
                }
                foreach ($content['groups'] ?? [] as $group) {
                    if (! is_array($group)) {
                        continue;
                    }
                    $id = (string) ($group['id'] ?? '');
                    if (in_array($id, ['whatsapp-ai', 'storefront', 'bookings', 'dine-in'], true)) {
                        $hasDuplicateCatalog = true;
                        break;
                    }
                }
            }
        }

        if ($hero === '') {
            return true;
        }

        $staleHero = str_contains($hero, 'whatsapp sales automation features')
            || str_contains($hero, 'every feature to sell on whatsapp');

        return $staleHero || $hasDuplicateCatalog || ! $hasVisualFeatures;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    public static function apply(?array $existing = null): array
    {
        $payload = self::payload();
        if (is_array($existing['page'] ?? null)) {
            foreach (['canonicalUrl', 'ogImage'] as $keep) {
                if (filled($existing['page'][$keep] ?? null)) {
                    $payload['page'][$keep] = $existing['page'][$keep];
                }
            }
            $existingMeta = mb_strtolower((string) ($existing['page']['metaTitle'] ?? ''));
            $staleMeta = str_contains($existingMeta, 'whatsapp sales automation features');
            if (! $staleMeta && filled($existing['page']['metaTitle'] ?? null)) {
                $payload['page']['metaTitle'] = $existing['page']['metaTitle'];
                $payload['page']['title'] = $existing['page']['title'] ?? $payload['page']['title'];
            }
            if (! $staleMeta && filled($existing['page']['metaDescription'] ?? null)) {
                $payload['page']['metaDescription'] = $existing['page']['metaDescription'];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        return [
            'page' => [
                'slug' => 'features',
                'title' => 'Features',
                'metaTitle' => self::title(),
                'metaDescription' => self::description(),
                'ogImage' => null,
                'ogTitle' => self::title(),
                'ogDescription' => self::description(),
                'canonicalUrl' => null,
                'robots' => 'index, follow',
            ],
            'sections' => self::sections(),
            'faqs' => self::faqs(),
        ];
    }

    /**
     * @return list<array{key: string, label: string, isEnabled: bool, sortOrder: int, content: array<string, mixed>}>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'hero',
                'label' => 'Hero',
                'isEnabled' => true,
                'sortOrder' => 1,
                'content' => [
                    'usePageHero' => true,
                    'kicker' => 'WHAT RELAYIQ INCLUDES',
                    'title' => self::h1(),
                    'description' => self::lede(),
                    'primaryCtaText' => 'Get started free',
                    'primaryCtaHref' => '/register',
                    'secondaryCtaText' => 'Compare plans',
                    'secondaryCtaHref' => '/pricing',
                ],
            ],
            [
                'key' => 'outcomes',
                'label' => 'Four doors',
                'isEnabled' => true,
                'sortOrder' => 2,
                'content' => [
                    'title' => 'Four doors. One catalog.',
                    'description' => 'Jump to how each one works. Turn on one this week — stock and prices stay in sync when you add the others.',
                    'items' => [
                        ['value' => '01', 'label' => 'WhatsApp AI', 'detail' => 'Sells in the chat they already send', 'href' => '#whatsapp-ai'],
                        ['value' => '02', 'label' => 'Web storefront', 'detail' => 'A shop link for Instagram and Google', 'href' => '#web-storefront'],
                        ['value' => '03', 'label' => 'Bookings', 'detail' => 'Customers pick a time slot', 'href' => '#bookings'],
                        ['value' => '04', 'label' => 'Dine-in table QR', 'detail' => 'Scan, order, and pay at the table', 'href' => '#dine-in-qr'],
                    ],
                ],
            ],
            [
                'key' => 'feature_1',
                'label' => 'Feature: WhatsApp AI',
                'isEnabled' => true,
                'sortOrder' => 3,
                'content' => [
                    'anchor' => 'whatsapp-ai',
                    'label' => '01 · WHATSAPP AI',
                    'title' => 'An AI seller on the number customers already message',
                    'description' => 'A buyer texts you. RelayIQ answers in English or Kiswahili from live stock and FAQs — not a numbered menu — then takes the order and can send M-Pesa in the same thread.',
                    'points' => [
                        'Recommends goods, files, bundles, and bookable services',
                        'Collects name, quantity, and delivery — then a payment prompt',
                        'M-Pesa STK, Paystack, or card in the chat — no SMS screenshots',
                        'Official WhatsApp Business Cloud API',
                    ],
                    'ctaText' => 'See the AI sales agent',
                    'ctaHref' => '/whatsapp-ai-sales-agent',
                    'imageUrl' => '/images/lando/lando-hero.png?v=brand2',
                    'imageAlt' => 'WhatsApp chat with a live product catalog and order confirmation',
                ],
            ],
            [
                'key' => 'feature_2',
                'label' => 'Feature: Storefront',
                'isEnabled' => true,
                'sortOrder' => 4,
                'content' => [
                    'anchor' => 'web-storefront',
                    'label' => '02 · WEB STOREFRONT',
                    'title' => 'A shop link for Instagram, Google, and your bio',
                    'description' => 'Some buyers want to browse. Give them a real URL: search, cart, coupons, and track order — checkout without photo-by-photo DMs.',
                    'points' => [
                        'Browse, filter, and add to cart on any phone',
                        'Digital files and license keys email after payment',
                        '20 products included on the free Starter plan',
                    ],
                    'ctaText' => 'Open a free storefront',
                    'ctaHref' => '/register',
                    'imageUrl' => '/images/lando/lando-feature-storefront.jpg?v=man1',
                    'imageAlt' => 'Customer browsing a shop on his phone outside a Nairobi boutique',
                ],
            ],
            [
                'key' => 'feature_3',
                'label' => 'Feature: Bookings',
                'isEnabled' => true,
                'sortOrder' => 5,
                'content' => [
                    'anchor' => 'bookings',
                    'label' => '03 · BOOKINGS',
                    'title' => 'Fill the calendar — no more missed “are you free Saturday?” DMs',
                    'description' => 'Salons, clinics, tutors, and studios lose bookings to unread chats. RelayIQ lists open slots, confirms in WhatsApp, and gives you a public page for links and bios.',
                    'points' => [
                        'Services with a duration and a price — braids, consults, classes',
                        'The AI lists times. They pick one. It is confirmed in chat.',
                        '30 bookings per month on free Starter',
                    ],
                    'ctaText' => 'Start booking for free',
                    'ctaHref' => '/register',
                    'imageUrl' => '/images/lando/lando-bookings.png?v=brand2',
                    'imageAlt' => 'WhatsApp booking confirmation with calendar and clock',
                ],
            ],
            [
                'key' => 'feature_4',
                'label' => 'Feature: Dine-in',
                'isEnabled' => true,
                'sortOrder' => 6,
                'content' => [
                    'anchor' => 'dine-in-qr',
                    'label' => '04 · DINE-IN QR',
                    'title' => 'Guests scan, order, and pay — without waiting for a waiter',
                    'description' => 'One QR per table. Diners browse the live menu and pay before food leaves the pass. Kitchen tickets show Table 7, so orders never mix. Use WhatsApp for takeaway.',
                    'points' => [
                        'Per-table codes — tickets never mix tables',
                        'M-Pesa or card at the table',
                        '5 tables included on free Starter',
                    ],
                    'ctaText' => 'Add tables for free',
                    'ctaHref' => '/register',
                    'imageUrl' => '/images/lando/lando-dinein.png?v=brand2',
                    'imageAlt' => 'Table QR stand branded with RelayIQ next to a phone menu',
                ],
            ],
            [
                'key' => 'capabilities',
                'label' => 'Also included',
                'isEnabled' => true,
                'sortOrder' => 7,
                'content' => [
                    'anchor' => 'also-included',
                    'title' => 'Also included — once, for every door',
                    'description' => 'Checkout, inbox, and follow-up are not a fifth product. They sit behind WhatsApp, the shop, bookings, and tables.',
                    'items' => [
                        [
                            'title' => 'Payments & orders',
                            'description' => 'M-Pesa STK, cards, and cash on delivery. Money lands on your Till, PayBill, or card account. WhatsApp and email confirm when they place and when they pay. Delivery zones and tax so quotes match checkout.',
                            'icon' => 'payment',
                        ],
                        [
                            'title' => 'Shared inbox & handoff',
                            'description' => 'Full chat history in one team inbox. AI on the front line; a person takes over VIP deals. The AI pauses when a teammate joins.',
                            'icon' => 'inbox',
                        ],
                        [
                            'title' => 'Follow-up & analytics',
                            'description' => 'Abandoned-cart reminders on WhatsApp. Broadcasts, birthday offers, and win-backs. Chats, orders, and revenue — not vanity reply counts.',
                            'icon' => 'growth',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'faq',
                'label' => 'FAQ',
                'isEnabled' => true,
                'sortOrder' => 8,
                'content' => [
                    'title' => 'Questions about what is included',
                ],
            ],
            [
                'key' => 'cta',
                'label' => 'CTA',
                'isEnabled' => true,
                'sortOrder' => 9,
                'content' => [
                    'title' => 'Turn on the door you need this week',
                    'description' => 'Starter is free forever: storefront, bookings, and 5 dine-in tables. Connect WhatsApp when you are ready for the AI seller.',
                    'ctaText' => 'Get started free',
                    'ctaHref' => '/register',
                ],
            ],
        ];
    }

    /**
     * @return list<array{id: string, question: string, answer: string}>
     */
    public static function faqs(): array
    {
        return [
            [
                'id' => 'feat-1',
                'question' => 'Is this just a WhatsApp chatbot?',
                'answer' => 'No. The AI seller is one door. You also get a web storefront, bookings, dine-in QR, payments, a team inbox, and follow-ups — all on the same catalog.',
            ],
            [
                'id' => 'feat-2',
                'question' => 'Do I have to turn everything on at once?',
                'answer' => 'No. Start with the storefront, bookings, or table QR on the free Starter plan. Connect WhatsApp when you want the AI to sell in chat.',
            ],
            [
                'id' => 'feat-3',
                'question' => 'Will stock stay in sync across channels?',
                'answer' => 'Yes. Update a price or quantity once. Chat, the storefront, bookings, and dine-in all read the same catalog.',
            ],
            [
                'id' => 'feat-4',
                'question' => 'Can a person take over a chat?',
                'answer' => 'Yes. The shared inbox keeps the full thread. The AI pauses when a teammate joins.',
            ],
            [
                'id' => 'feat-5',
                'question' => 'What is on the free plan?',
                'answer' => 'Starter is free forever: storefront (20 products), bookings (30/month), and 5 dine-in tables. No credit card. Growth is KSh 2,000/month when you need more.',
            ],
        ];
    }
}
