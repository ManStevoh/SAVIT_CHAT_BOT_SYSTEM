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
        $aboutTeamImage = '/images/lando/lando-about-team.png';

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
                            'imageUrl' => '/images/lando/lando-hero.png',
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
                                ['name' => 'FoodHub', 'logoUrl' => ''],
                                ['name' => 'ShopEase', 'logoUrl' => ''],
                                ['name' => 'TechStore', 'logoUrl' => ''],
                                ['name' => 'FashionCo', 'logoUrl' => ''],
                                ['name' => 'QuickBite', 'logoUrl' => ''],
                                ['name' => 'HomeGoods', 'logoUrl' => ''],
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Platform overview',
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Team inbox',
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Payments in chat',
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Getting started',
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
                            'imageUrl' => $heroImage,
                            'imageAlt' => 'Get started',
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
