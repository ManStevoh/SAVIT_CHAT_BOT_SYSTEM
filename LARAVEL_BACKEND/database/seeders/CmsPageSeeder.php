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
                            'copyright' => date('Y') . ' © Essem Chat / All rights reserved.',
                            'navLinks' => [
                                ['label' => 'Home', 'href' => '/'],
                                ['label' => 'Pricing', 'href' => '/pricing'],
                                ['label' => 'About us', 'href' => '/about'],
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
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'home',
                'title' => 'Home',
                'meta_title' => 'Essem Chat — AI WhatsApp Sales & Order Automation',
                'meta_description' => 'Turn WhatsApp into your best sales channel. AI replies, order flows, M-Pesa & Stripe payments, multi-agent inbox, and Growth Engine attribution.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'kicker' => 'FREE 14 DAYS TRIAL',
                            'title' => 'The best way to sell on WhatsApp.',
                            'description' => 'AI handles replies and orders. Your team steps in when needed. Customers pay with M-Pesa or card without leaving the chat.',
                            'primaryCtaText' => 'Try for free',
                            'primaryCtaHref' => '/register',
                            'secondaryCtaText' => 'See how it works',
                            'secondaryCtaHref' => '#how-to-join',
                            'showFlowSimulation' => true,
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'WhatsApp commerce platform illustration',
                        ],
                    ],
                    [
                        'section_key' => 'trusted_companies',
                        'label' => 'Trusted companies',
                        'sort_order' => 2,
                        'content' => [
                            'title' => "Trusted by individuals and teams at the world's best companies",
                            'companies' => [
                                ['name' => 'FoodHub', 'logoUrl' => '/images/lando/logo-foodhub.svg'],
                                ['name' => 'ShopEase', 'logoUrl' => '/images/lando/logo-shopease.svg'],
                                ['name' => 'TechStore', 'logoUrl' => '/images/lando/logo-techstore.svg'],
                                ['name' => 'FashionCo', 'logoUrl' => '/images/lando/logo-fashionco.svg'],
                                ['name' => 'QuickBite', 'logoUrl' => '/images/lando/logo-quickbite.svg'],
                                ['name' => 'HomeGoods', 'logoUrl' => '/images/lando/logo-homegoods.svg'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'intro_card',
                        'label' => 'Intro card',
                        'sort_order' => 3,
                        'content' => [
                            'title' => 'Introducing WhatsApp commerce',
                            'description' => 'Join businesses using Essem Chat and experience AI-powered sales today!',
                            'ctaText' => 'Try for free',
                            'ctaHref' => '/register',
                            'imageUrl' => $introImage,
                        ],
                    ],
                    [
                        'section_key' => 'feature_1',
                        'label' => 'Feature block 1',
                        'sort_order' => 4,
                        'content' => [
                            'label' => 'SMART INBOX',
                            'title' => 'All your conversations in one place',
                            'description' => 'We take customer data seriously. Every chat, order, and payment is encrypted and stored securely. Your team inbox keeps full history per customer.',
                            'ctaText' => 'Try now',
                            'ctaHref' => '/register',
                            'imageUrl' => $inboxImage,
                            'imagePosition' => 'left',
                        ],
                    ],
                    [
                        'section_key' => 'feature_2',
                        'label' => 'Feature block 2',
                        'sort_order' => 5,
                        'content' => [
                            'label' => 'PAY IN CHAT',
                            'title' => 'Get paid without leaving WhatsApp',
                            'description' => 'M-Pesa STK push and Stripe card payments built into the conversation. Customers browse, order, and pay in one thread — no redirects, no friction.',
                            'ctaText' => 'Try now',
                            'ctaHref' => '/register',
                            'imageUrl' => $paymentsImage,
                            'imagePosition' => 'right',
                        ],
                    ],
                    [
                        'section_key' => 'how_to_join',
                        'label' => 'How to join',
                        'sort_order' => 6,
                        'content' => [
                            'title' => 'How to join our community',
                            'description' => 'Just 3 simple steps to start selling on WhatsApp.',
                            'ctaText' => 'Sign up now',
                            'ctaHref' => '/register',
                            'imageUrl' => $stepsImage,
                            'steps' => [
                                ['title' => 'Step 1', 'description' => 'Create your account and connect your WhatsApp Business number.'],
                                ['title' => 'Step 2', 'description' => 'Add your products, FAQs, and payment methods — we guide you through setup.'],
                                ['title' => 'Step 3', 'description' => 'Go live! AI handles replies while your team takes over when needed.'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'testimonials',
                        'label' => 'Testimonials',
                        'sort_order' => 7,
                        'content' => [
                            'title' => 'Testimonials',
                            'description' => 'People love what we do and we want you to know',
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 8,
                        'content' => [
                            'title' => 'Get started with Essem Chat today',
                            'description' => 'Start selling on WhatsApp with AI automation today.',
                            'ctaText' => 'Sign up now',
                            'ctaHref' => '/register',
                            'imageUrl' => $ctaImage,
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'pricing',
                'title' => 'Pricing',
                'meta_title' => 'Pricing — Essem Chat',
                'meta_description' => 'Straightforward plans for WhatsApp commerce. 14-day free trial on every plan.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Pricing',
                            'description' => "Our pricing is not expensive, but it's not cheap either — it's exactly what it should be.",
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
                            'title' => 'Compare Features',
                            'columns' => [
                                [
                                    'name' => 'Starter',
                                    'features' => ['AI replies', 'Order management', 'M-Pesa payments', '1 WhatsApp number', 'Email support'],
                                ],
                                [
                                    'name' => 'Growth',
                                    'features' => ['Everything in Starter', 'Multi-agent inbox', 'Stripe payments', 'Analytics dashboard', 'Priority support', 'API access'],
                                ],
                                [
                                    'name' => 'Enterprise',
                                    'features' => ['Everything in Growth', 'Unlimited numbers', 'Custom AI training', 'Dedicated manager', 'SLA guarantee', 'On-premise option'],
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
                            'useFaqsApi' => true,
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Get started with Essem Chat today',
                            'description' => 'Start optimizing your WhatsApp sales today.',
                            'ctaText' => 'Sign up now',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'about',
                'title' => 'About us',
                'meta_title' => 'About us — Essem Chat',
                'meta_description' => 'We offer a revolutionary WhatsApp commerce platform. Join the Essem Chat community today.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'About us',
                            'description' => 'We offer a revolutionary solution for WhatsApp sales. Join the Essem Chat community and experience the benefits of commerce automation today!',
                            'imageUrl' => $aboutTeamImage,
                            'imageAlt' => 'Our team',
                        ],
                    ],
                    [
                        'section_key' => 'mission',
                        'label' => 'Mission',
                        'sort_order' => 2,
                        'content' => [
                            'title' => 'Our mission',
                            'description' => 'At Essem Chat, we are committed to helping businesses sell more on WhatsApp. We believe AI and human agents working together can transform customer experience. Our team is dedicated to providing the best possible service and support, and we are always looking for ways to improve and innovate.',
                        ],
                    ],
                    [
                        'section_key' => 'efficiency',
                        'label' => 'Efficiency statement',
                        'sort_order' => 3,
                        'content' => [
                            'title' => "Let's start\nworking\nmore\nefficiently\ntoday!",
                        ],
                    ],
                    [
                        'section_key' => 'team',
                        'label' => 'Team',
                        'sort_order' => 4,
                        'content' => [
                            'title' => 'Team',
                            'description' => 'Meet the people behind our magical product',
                            'members' => [
                                ['name' => 'Hannah Mika', 'role' => 'CEO', 'imageUrl' => '/images/lando/team-ceo.png'],
                                ['name' => 'Daniel Peter', 'role' => 'CTO', 'imageUrl' => '/images/lando/team-cto.png'],
                                ['name' => 'Lars Mikkel', 'role' => 'Head of Operations', 'imageUrl' => '/images/lando/team-operations.png'],
                                ['name' => 'Denis Forner', 'role' => 'Head of Product', 'imageUrl' => '/images/lando/team-product.png'],
                            ],
                        ],
                    ],
                    [
                        'section_key' => 'cta',
                        'label' => 'Call to action',
                        'sort_order' => 5,
                        'content' => [
                            'title' => 'Get started with Essem Chat today',
                            'description' => 'Start optimizing your WhatsApp sales today.',
                            'ctaText' => 'Sign up now',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact',
                'meta_title' => 'Contact — Essem Chat',
                'meta_description' => 'Get in touch with the Essem Chat team. We would love to hear from you.',
                'sections' => [
                    [
                        'section_key' => 'hero',
                        'label' => 'Hero + form',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Contact Us',
                            'description' => 'Explore the future with us. Feel free to get in touch.',
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
                            'title' => 'Get started with Essem Chat today',
                            'description' => 'Start optimizing your WhatsApp sales today.',
                            'ctaText' => 'Sign up now',
                            'ctaHref' => '/register',
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_title' => 'Privacy Policy — Essem Chat',
                'meta_description' => 'How Essem Chat collects, uses, and protects your data.',
                'sections' => [
                    [
                        'section_key' => 'legal_content',
                        'label' => 'Legal content',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Privacy Policy',
                            'lastUpdated' => 'June 2026',
                            'body' => "<h2>1. Who we are</h2>\n<p>Essem Chat is operated by Essem Global Solutions. We provide a multi-tenant SaaS platform for WhatsApp business messaging, AI-assisted replies, order management, and related services.</p>\n<h2>2. Information we collect</h2>\n<p>We collect information you provide when you register and use the platform, including:</p>\n<ul>\n<li>Account details (name, email, company information)</li>\n<li>WhatsApp business configuration and message content routed through the platform</li>\n<li>Customer conversation data processed on your behalf</li>\n<li>Payment and subscription records (processed by Stripe or M-Pesa providers)</li>\n<li>Usage logs for billing, security, and product improvement</li>\n</ul>\n<h2>3. How we use your information</h2>\n<p>We use collected data to provide, operate, and improve the Essem Chat platform, process AI-assisted replies, send service-related communications, and comply with legal obligations.</p>\n<h2>4. Data sharing</h2>\n<p>We do not sell your data. We share information only with service providers necessary to operate the platform (e.g. Meta/WhatsApp Cloud API, payment processors, AI providers you configure) and when required by law.</p>\n<h2>5. Security</h2>\n<p>We use industry-standard measures including encryption in transit and access controls. Each tenant's data is logically isolated in our multi-tenant architecture.</p>\n<h2>6. Your rights</h2>\n<p>Depending on your jurisdiction, you may have rights to access, correct, or delete personal data. Contact us at support@essemglobalsolutions.com to submit a request.</p>\n<h2>7. Changes</h2>\n<p>We may update this policy from time to time. Continued use of the service after changes constitutes acceptance of the updated policy.</p>",
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms of Service',
                'meta_title' => 'Terms of Service — Essem Chat',
                'meta_description' => 'The terms governing your use of Essem Chat.',
                'sections' => [
                    [
                        'section_key' => 'legal_content',
                        'label' => 'Legal content',
                        'sort_order' => 1,
                        'content' => [
                            'title' => 'Terms of Service',
                            'lastUpdated' => 'June 2026',
                            'body' => "<h2>1. Acceptance</h2>\n<p>By creating an account or using Essem Chat, you agree to these Terms of Service. If you are using the service on behalf of a company, you represent that you have authority to bind that company.</p>\n<h2>2. Service description</h2>\n<p>Essem Chat provides WhatsApp business messaging, AI-assisted automation, order management, payment integrations, and related tools. Features vary by subscription plan.</p>\n<h2>3. Your responsibilities</h2>\n<p>You agree to:</p>\n<ul>\n<li>Comply with Meta's WhatsApp Business and Commerce policies</li>\n<li>Obtain necessary consents from your customers for messaging and data processing</li>\n<li>Keep your account credentials secure</li>\n<li>Use the service only for lawful business purposes</li>\n</ul>\n<h2>4. Subscriptions and billing</h2>\n<p>Paid plans are billed according to the pricing shown at checkout. Free trials convert to paid subscriptions unless cancelled before the trial ends. WhatsApp conversation fees charged by Meta may apply separately.</p>\n<h2>5. AI-generated content</h2>\n<p>AI replies are generated based on your configuration and content. You are responsible for reviewing automated responses and ensuring they meet your business and legal requirements.</p>\n<h2>6. Limitation of liability</h2>\n<p>The service is provided \"as is\" to the maximum extent permitted by law. Essem Global Solutions is not liable for indirect, incidental, or consequential damages arising from use of the platform.</p>\n<h2>7. Termination</h2>\n<p>You may cancel your subscription at any time. We may suspend or terminate accounts that violate these terms or applicable law.</p>\n<h2>8. Contact</h2>\n<p>For questions about these terms, contact support@essemglobalsolutions.com.</p>",
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
                        'is_enabled' => true,
                        'sort_order' => $sectionData['sort_order'],
                        'content' => $sectionData['content'],
                    ]
                );
            }
        }

        if (Testimonial::count() === 0) {
            $samples = [
                ['name' => 'Jack Sibire', 'role' => 'Lead Manager, Growio', 'content' => 'Since implementing Essem Chat our business has seen significant growth on WhatsApp.'],
                ['name' => 'Adele Mouse', 'role' => 'Product Manager, Mousio', 'content' => 'I recommend Essem Chat to any business looking to improve WhatsApp sales.'],
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

        if (LandingFaq::count() === 0) {
            $faqs = [
                ['question' => 'How does WhatsApp commerce work?', 'answer' => 'Connect your WhatsApp Business number, add products and FAQs, and our AI handles customer replies, orders, and payments in the chat.'],
                ['question' => 'What payment methods are supported?', 'answer' => 'M-Pesa STK push and Stripe card payments are built into the conversation — customers pay without leaving WhatsApp.'],
                ['question' => 'Is there a free trial?', 'answer' => 'Yes. Every plan includes a 14-day free trial so you can test with real customers before committing.'],
                ['question' => 'Can my team take over conversations?', 'answer' => 'Absolutely. Agents can jump into any thread from the team inbox. The AI pauses until you hand the chat back.'],
                ['question' => 'Can I have custom pricing?', 'answer' => 'Enterprise plans support custom pricing, dedicated support, and on-premise deployment. Contact us to discuss your needs.'],
                ['question' => 'Where do I sign up?', 'answer' => 'Click Sign up in the navigation bar or visit /register to create your account and start your free trial.'],
            ];
            foreach ($faqs as $i => $faq) {
                LandingFaq::create([
                    ...$faq,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }
    }
}
