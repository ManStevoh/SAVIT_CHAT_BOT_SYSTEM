<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Home/page');
    }

    public function privacy(): Response
    {
        return Inertia::render('legal/privacy/page');
    }

    public function terms(): Response
    {
        return Inertia::render('legal/terms/page');
    }

    public function orderPaid(): Response
    {
        return Inertia::render('order-paid/page');
    }

    public function login(): Response
    {
        return Inertia::render('Auth/login/page');
    }

    public function register(): Response
    {
        return Inertia::render('Auth/register/page');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Auth/forgot-password/page');
    }

    public function resetPassword(): Response
    {
        return Inertia::render('Auth/reset-password/page');
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

    public function dashboardSettings(): Response
    {
        return Inertia::render('dashboard/settings/page');
    }

    public function dashboardSubscription(): Response
    {
        return Inertia::render('dashboard/subscription/page');
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
