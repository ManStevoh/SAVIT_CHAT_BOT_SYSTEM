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
                            'copyright' => date('Y') . ' © Essem Digital Innovation Limited. RelayIQ is a product of Essem Digital Innovation Limited. All rights reserved.',
                            'navLinks' => [
                                ['label' => 'Home', 'href' => '/'],
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
                            'showMobileApp' => false,
                            'mobileAppTitle' => 'Get the mobile app',
                            'mobileAppDescription' => 'Manage chats, orders, and growth on the go.',
                            'playStoreUrl' => '',
                            'appStoreUrl' => '',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'home',
                'title' => 'Home',
                'meta_title' => 'RelayIQ — AI Commerce OS for WhatsApp',
                'meta_description' => 'Your AI commerce agent on WhatsApp: sell physical & digital products, take bookings, collect M-Pesa/Paystack/Stripe, grow with attribution — with memory and a human team inbox.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'kicker' => 'FREE 14-DAY TRIAL',
                            'title' => 'Your AI commerce OS for WhatsApp.',
                            'description' => 'Not a menu bot — a fluent agent that knows your catalog, takes orders, collects payment, books services, delivers digital products, and hands off to your team when needed.',
                            'primaryCtaText' => 'Start free trial',
                            'primaryCtaHref' => '/register',
                            'secondaryCtaText' => 'See how it works',
                            'secondaryCtaHref' => '#how-to-join',
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
                            'title' => 'Everything your WhatsApp business needs',
                            'description' => 'One platform for conversation, catalog, payments, growth — and an AI that improves as you sell.',
                            'items' => [
                                ['icon' => 'bot', 'title' => 'AI commerce agent', 'description' => 'Fluent sales & support with memory — not rigid numbered menus.'],
                                ['icon' => 'package', 'title' => 'Sell anything', 'description' => 'Physical goods, digital files & licenses, and bookable services.'],
                                ['icon' => 'payment', 'title' => 'Pay in the chat', 'description' => 'M-Pesa, Paystack, and Stripe — customers pay without leaving WhatsApp.'],
                                ['icon' => 'booking', 'title' => 'Bookings & services', 'description' => 'Qualify needs, share availability, and convert service requests into bookings.'],
                                ['icon' => 'growth', 'title' => 'Growth Engine', 'description' => 'AI posts, social publishing, and WhatsApp referral attribution.'],
                                ['icon' => 'inbox', 'title' => 'Team inbox', 'description' => 'AI handles the front line; humans take over any thread instantly.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'intro_card',
                        'label' => 'Intro card',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Not a chatbot. A commerce operating system.',
                            'description' => 'RelayIQ runs the full customer journey: discover → recommend → order → pay → fulfill → follow up. It learns from conversations so every reply gets sharper over time.',
                            'ctaText' => 'Explore pricing',
                            'ctaHref' => '/pricing',
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
                        'section_key' => 'growth_engine',
                        'label' => 'Growth Engine',
                        'sort_order' => 7,
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
                        'sort_order' => 8,
                        'content' => [
                            'title' => 'Go live in three steps',
                            'description' => 'From signup to your first AI-assisted sale — without a technical team.',
                            'ctaText' => 'Create your account',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'imageAlt' => 'Three steps to go live',
                            'steps' => [
                                ['title' => 'Connect WhatsApp', 'description' => 'Sign up, connect your WhatsApp Business number (Embedded Signup or Cloud API).'],
                                ['title' => 'Add catalog & payments', 'description' => 'Products (physical, digital, bookings), FAQs, and M-Pesa / Paystack / Stripe.'],
                                ['title' => 'Let the agent sell', 'description' => 'AI handles replies and orders; your team takes over any chat when needed.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'trusted_companies',
                        'label' => 'Trusted companies',
                        'sort_order' => 9,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'Built for WhatsApp-first sellers across Africa and beyond',
                            'companies' => [],
                        ],
                    ],
                    [
                        'section_key' => 'testimonials',
                        'label' => 'Testimonials',
                        'sort_order' => 10,
                        'is_enabled' => false,
                        'content' => [
                            'title' => 'What sellers say',
                            'description' => 'Real stories from businesses running RelayIQ.',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 11,
                        'content' => [
                            'title' => 'Put an AI employee on your WhatsApp today',
                            'description' => '14-day free trial on Starter and Growth. No credit card required to start.',
                            'ctaText' => 'Start free trial',
                            'ctaHref' => '/register',
                            'imageUrl' => $ctaImage,
                            'imageAlt' => 'Start RelayIQ free trial',
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
                            'description' => 'Start with a free trial. Upgrade when volume, bookings, or Growth Engine needs grow.',
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
                                        'Conversational AI OS + memory',
                                        'Physical & digital catalog',
                                        'M-Pesa / Paystack / Stripe',
                                        '5,000 messages / month',
                                        'Growth Engine (20 AI posts)',
                                        'Up to 3 team seats',
                                        '14-day free trial',
                                    ],
                                ],
                                [
                                    'name' => 'Growth',
                                    'features' => [
                                        'Everything in Starter',
                                        'Bookings & services',
                                        '50,000 messages / month',
                                        'Advanced AI + BYOK preferred',
                                        'Growth Engine (100 AI posts)',
                                        'Analytics + API access',
                                        'Up to 10 team seats',
                                    ],
                                ],
                                [
                                    'name' => 'Enterprise',
                                    'features' => [
                                        'Everything in Growth',
                                        'Unlimited messages',
                                        'Custom AI models + company keys',
                                        'Growth Engine (500 AI posts)',
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
                'meta_title' => 'About us — RelayIQ',
                'meta_description' => 'RelayIQ by Essem Digital Innovation — the AI commerce OS that helps businesses sell on WhatsApp.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'About RelayIQ',
                            'description' => 'We build the AI commerce operating system for WhatsApp-first businesses — so owners can sell more without hiring a call center.',
                            'imageUrl' => $aboutTeamImage,
                            'imageAlt' => 'RelayIQ by Essem Digital',
                        ],
                    ],
                    [
                        'section_key' => 'mission',
                        'label' => 'Mission',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Our mission',
                            'description' => 'RelayIQ is a product of Essem Digital Innovation Limited. We believe every business deserves an intelligent front line on WhatsApp: accurate catalog answers, payments that work for Africa and the world, digital delivery, bookings, and humans in the loop. Intelligence at the center — not hard-coded menus.',
                        ],
                    ],
                    [
                        'section_key' => 'efficiency',
                        'label' => 'Efficiency statement',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Sell smarter. Stay in control.',
                            'description' => 'Automate the busywork of replies and orders while your team focuses on relationships and exceptions.',
                            'ctaText' => 'Start free trial',
                            'ctaHref' => '/register',
                        ],
                    ],
                    [
                        'section_key' => 'team',
                        'label' => 'Team',
                        'sort_order' => 4,
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
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Join businesses running on RelayIQ',
                            'description' => 'Start your free trial and connect WhatsApp today.',
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
            ['question' => 'What can I sell?', 'answer' => 'Physical products, digital goods (download links and license keys), and bookable services — all from one catalog the AI understands.'],
            ['question' => 'What payment methods are supported?', 'answer' => 'M-Pesa (including your own Till/PayBill), Paystack, and Stripe. Customers can pay in the conversation flow.'],
            ['question' => 'Is there a free trial?', 'answer' => 'Yes. Starter and Growth include a 14-day free trial. Enterprise is custom — contact sales.'],
            ['question' => 'Can my team take over conversations?', 'answer' => 'Yes. Agents can jump into any thread from the team inbox. The AI pauses until you hand the chat back.'],
            ['question' => 'What is the Growth Engine?', 'answer' => 'AI-assisted posts, social publishing, and WhatsApp referral attribution so you can measure which campaigns drive chats and orders.'],
            ['question' => 'Where do I sign up?', 'answer' => 'Click Sign up in the navigation or visit /register to create your account and start your free trial.'],
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
