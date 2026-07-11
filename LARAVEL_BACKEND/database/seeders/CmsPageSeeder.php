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
