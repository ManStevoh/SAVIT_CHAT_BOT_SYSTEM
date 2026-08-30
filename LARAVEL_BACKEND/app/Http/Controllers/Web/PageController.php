<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Cms\CmsPagePayloadBuilder;
use App\Services\Cms\CmsSeoService;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function __construct(
        private readonly CmsSeoService $seo,
        private readonly CmsPagePayloadBuilder $cmsPayloads,
    ) {
    }

    public function home(): Response
    {
        return $this->marketing('Home/page', 'home');
    }

    public function pricing(): Response
    {
        return $this->marketing('Pricing/page', 'pricing');
    }

    public function about(): Response
    {
        return $this->marketing('About/page', 'about');
    }

    public function solutions(): Response
    {
        return $this->marketing('Solutions/page', 'solutions');
    }

    public function features(): Response
    {
        return $this->marketingPage('features', 'Features — RelayIQ');
    }

    public function whatsappAiSalesAgent(): Response
    {
        return $this->marketingPage('whatsapp-ai-sales-agent', 'AI Sales Agent for WhatsApp — RelayIQ');
    }

    public function whatsappSalesAutomation(): Response
    {
        return $this->marketingPage('whatsapp-sales-automation', 'WhatsApp Sales Automation — RelayIQ');
    }

    public function whatsappChatbot(): Response
    {
        return $this->marketingPage('whatsapp-chatbot', 'WhatsApp Chatbot for Sales — RelayIQ');
    }

    public function whatsappCommerce(): Response
    {
        return $this->marketingPage('whatsapp-commerce', 'WhatsApp Commerce — RelayIQ');
    }

    public function whatsappLeadGeneration(): Response
    {
        return $this->marketingPage('whatsapp-lead-generation', 'WhatsApp Lead Generation — RelayIQ');
    }

    public function aiCustomerService(): Response
    {
        return $this->marketingPage('ai-customer-service', 'WhatsApp Customer Service Automation — RelayIQ');
    }

    public function whatsappForEcommerce(): Response
    {
        return $this->marketingPage('whatsapp-for-ecommerce', 'WhatsApp for Ecommerce — RelayIQ');
    }

    public function contact(): Response
    {
        return $this->marketing('Contact/page', 'contact');
    }

    public function adminCms(): Response
    {
        return Inertia::render('admin/cms/page');
    }

    public function adminBlog(): Response
    {
        return Inertia::render('admin/blog/page');
    }

    public function privacy(): Response
    {
        return $this->marketing('legal/privacy/page', 'privacy');
    }

    public function terms(): Response
    {
        return $this->marketing('legal/terms/page', 'terms');
    }

    public function blog(): Response
    {
        $posts = [];
        try {
            $posts = BlogPost::published()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (BlogPost $post) => $post->toPublicArray())
                ->values()
                ->all();
        } catch (\Throwable) {
            $posts = [];
        }

        return Inertia::render('Blog/page', [
            'seo' => $this->seo->forBlogIndex(),
            'initialPosts' => $posts,
        ]);
    }

    public function blogShow(string $slug): Response|\Symfony\Component\HttpFoundation\Response
    {
        $seo = $this->seo->forBlogPost($slug);
        if (! $seo) {
            abort(404);
        }

        $post = null;
        try {
            $model = BlogPost::published()->where('slug', $slug)->first();
            $post = $model?->toPublicArray();
        } catch (\Throwable) {
            $post = null;
        }

        return Inertia::render('Blog/show', [
            'slug' => $slug,
            'seo' => $seo,
            'initialPost' => $post,
        ]);
    }

    private function marketing(string $component, string $slug): Response
    {
        return Inertia::render($component, [
            'seo' => $this->seo->forSlug($slug),
            'cms' => $this->cmsPayloads->forSlug($slug),
            'cmsGlobal' => $this->cmsPayloads->forSlug('global'),
        ]);
    }

    private function marketingPage(string $slug, ?string $fallbackTitle = null): Response
    {
        return Inertia::render('Marketing/page', [
            'slug' => $slug,
            'fallbackTitle' => $fallbackTitle,
            'seo' => $this->seo->forSlug($slug),
            'cms' => $this->cmsPayloads->forSlug($slug),
            'cmsGlobal' => $this->cmsPayloads->forSlug('global'),
        ]);
    }

    public function orderPaid(): Response
    {
        return Inertia::render('order-paid/page', [
            'seo' => $this->seo->noindex('Order paid — '.config('app.name', 'RelayIQ')),
        ]);
    }

    public function login(): Response
    {
        return Inertia::render('Auth/login/page', [
            'seo' => $this->seo->noindex('Log in — '.config('app.name', 'RelayIQ')),
        ]);
    }

    public function register(): Response
    {
        return Inertia::render('Auth/register/page', [
            'seo' => $this->seo->noindex('Sign up — '.config('app.name', 'RelayIQ')),
        ]);
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Auth/forgot-password/page', [
            'seo' => $this->seo->noindex('Forgot password — '.config('app.name', 'RelayIQ')),
        ]);
    }

    public function resetPassword(): Response
    {
        return Inertia::render('Auth/reset-password/page', [
            'seo' => $this->seo->noindex('Reset password — '.config('app.name', 'RelayIQ')),
        ]);
    }

    public function dashboard(): Response
    {
        return Inertia::render('dashboard/page');
    }

    public function dashboardAnalytics(): Response
    {
        return Inertia::render('dashboard/analytics/page');
    }

    public function dashboardChats(): Response
    {
        return Inertia::render('dashboard/chats/page');
    }

    public function dashboardCustomers(): Response
    {
        return Inertia::render('dashboard/customers/page');
    }

    public function dashboardFaq(): Response
    {
        return Inertia::render('dashboard/faq/page');
    }

    public function dashboardGrowth(): Response
    {
        return Inertia::render('dashboard/growth/page');
    }

    public function dashboardExecutive(): Response
    {
        return Inertia::render('dashboard/executive/page');
    }

    public function dashboardCognitive(): Response
    {
        return Inertia::render('dashboard/cognitive/page');
    }

    public function dashboardAgentOps(): Response
    {
        return Inertia::render('dashboard/agent-ops/page');
    }

    public function dashboardBusinessIntelligence(): Response
    {
        return Inertia::render('dashboard/business-intelligence/page');
    }

    public function dashboardMissionControl(): Response
    {
        return Inertia::render('dashboard/mission-control/page');
    }

    public function dashboardMarketplace(): Response
    {
        return Inertia::render('dashboard/marketplace/page');
    }

    public function dashboardWhatsAppCampaigns(): Response
    {
        return Inertia::render('dashboard/whatsapp/campaigns/page');
    }

    public function dashboardOrders(): Response
    {
        return Inertia::render('dashboard/orders/page');
    }

    public function dashboardProducts(): Response
    {
        return Inertia::render('dashboard/products/page');
    }

    public function dashboardTaxes(): Response
    {
        return Inertia::render('dashboard/taxes/page');
    }

    public function dashboardBookings(): Response
    {
        return Inertia::render('dashboard/bookings/page');
    }

    public function dashboardSettings(): Response
    {
        return Inertia::render('dashboard/settings/page');
    }

    public function dashboardSubscription(): Response
    {
        return Inertia::render('dashboard/subscription/page');
    }

    public function dashboardStorefront(): Response
    {
        return Inertia::render('dashboard/storefront/page');
    }

    public function dashboardDelivery(): Response
    {
        return Inertia::render('dashboard/delivery/page');
    }

    public function dashboardDineIn(): Response
    {
        return Inertia::render('dashboard/dine-in/page');
    }

    public function adminAccount(): Response
    {
        return Inertia::render('admin/account/page');
    }

    public function dashboardAccount(): Response
    {
        return Inertia::render('dashboard/account/page');
    }

    public function admin(): Response
    {
        return Inertia::render('admin/page');
    }

    public function adminAiUsage(): Response
    {
        return Inertia::render('admin/ai-usage/page');
    }

    public function adminAiLearning(): Response
    {
        return Inertia::render('admin/ai-learning/page');
    }

    public function adminAiModels(): Response
    {
        return Inertia::render('admin/ai-models/page');
    }

    public function adminCompanies(): Response
    {
        return Inertia::render('admin/companies/page');
    }

    public function adminGrowth(): Response
    {
        return Inertia::render('admin/growth/page');
    }

    public function adminLandingFaqs(): Response
    {
        return Inertia::render('admin/landing-faqs/page');
    }

    public function adminContact(): Response
    {
        return Inertia::render('admin/contact/page');
    }

    public function adminLogs(): Response
    {
        return Inertia::render('admin/logs/page');
    }

    public function adminPaymentGateways(): Response
    {
        return Inertia::render('admin/payment-gateways/page');
    }

    public function adminPlans(): Response
    {
        return Inertia::render('admin/plans/page');
    }

    public function adminOffers(): Response
    {
        return Inertia::render('admin/offers/page');
    }

    public function adminRevenue(): Response
    {
        return Inertia::render('admin/revenue/page');
    }

    public function adminSettings(): Response
    {
        return Inertia::render('admin/settings/page');
    }

    public function adminWhatsApp(): Response
    {
        return Inertia::render('admin/whatsapp/page');
    }

    public function adminSubscriptions(): Response
    {
        return Inertia::render('admin/subscriptions/page');
    }

    public function adminTestimonials(): Response
    {
        return Inertia::render('admin/testimonials/page');
    }

    public function adminUsers(): Response
    {
        return Inertia::render('admin/users/page');
    }
}
