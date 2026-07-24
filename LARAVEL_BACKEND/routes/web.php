<?php

use App\Http\Controllers\GrowthOAuthCallbackController;
use App\Http\Controllers\GrowthRedirectController;
use App\Http\Controllers\Web\OrderDigitalAccessController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublicBookingController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

// SEO
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/blog', [PageController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [PageController::class, 'blogShow'])->name('blog.show');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/order-paid', [PageController::class, 'orderPaid'])->name('order-paid');

// Auth pages
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::get('/register', [PageController::class, 'register'])->name('register');
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password', [PageController::class, 'resetPassword'])->name('password.reset');

// Company dashboard
Route::get('/dashboard/account', [PageController::class, 'dashboardAccount'])->name('dashboard.account');
Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
Route::get('/dashboard/analytics', [PageController::class, 'dashboardAnalytics'])->name('dashboard.analytics');
Route::get('/dashboard/chats', [PageController::class, 'dashboardChats'])->name('dashboard.chats');
Route::get('/dashboard/customers', [PageController::class, 'dashboardCustomers'])->name('dashboard.customers');
Route::get('/dashboard/faq', [PageController::class, 'dashboardFaq'])->name('dashboard.faq');
Route::get('/dashboard/growth', [PageController::class, 'dashboardGrowth'])->name('dashboard.growth');
Route::get('/dashboard/executive', [PageController::class, 'dashboardExecutive'])->name('dashboard.executive');
Route::get('/dashboard/cognitive', [PageController::class, 'dashboardCognitive'])->name('dashboard.cognitive');
    Route::get('/dashboard/agent-ops', [PageController::class, 'dashboardAgentOps'])->name('dashboard.agent-ops');
    Route::get('/dashboard/business-intelligence', [PageController::class, 'dashboardBusinessIntelligence'])->name('dashboard.business-intelligence');
Route::get('/dashboard/mission-control', [PageController::class, 'dashboardMissionControl'])->name('dashboard.mission-control');
Route::get('/dashboard/marketplace', [PageController::class, 'dashboardMarketplace'])->name('dashboard.marketplace');
Route::get('/dashboard/whatsapp/campaigns', [PageController::class, 'dashboardWhatsAppCampaigns'])->name('dashboard.whatsapp.campaigns');
Route::get('/dashboard/orders', [PageController::class, 'dashboardOrders'])->name('dashboard.orders');
Route::get('/dashboard/products', [PageController::class, 'dashboardProducts'])->name('dashboard.products');
Route::get('/dashboard/bookings', [PageController::class, 'dashboardBookings'])->name('dashboard.bookings');
Route::get('/dashboard/settings', [PageController::class, 'dashboardSettings'])->name('dashboard.settings');
Route::get('/dashboard/subscription', [PageController::class, 'dashboardSubscription'])->name('dashboard.subscription');

// Super admin
Route::get('/admin/account', [PageController::class, 'adminAccount'])->name('admin.account');
Route::get('/admin', [PageController::class, 'admin'])->name('admin');
Route::get('/admin/ai-usage', [PageController::class, 'adminAiUsage'])->name('admin.ai-usage');
Route::get('/admin/ai-learning', [PageController::class, 'adminAiLearning'])->name('admin.ai-learning');
Route::get('/admin/ai-models', [PageController::class, 'adminAiModels'])->name('admin.ai-models');
Route::get('/admin/companies', [PageController::class, 'adminCompanies'])->name('admin.companies');
Route::get('/admin/growth', [PageController::class, 'adminGrowth'])->name('admin.growth');
Route::get('/admin/cms', [PageController::class, 'adminCms'])->name('admin.cms');
Route::get('/admin/blog', [PageController::class, 'adminBlog'])->name('admin.blog');
Route::get('/admin/landing-faqs', [PageController::class, 'adminLandingFaqs'])->name('admin.landing-faqs');
Route::get('/admin/logs', [PageController::class, 'adminLogs'])->name('admin.logs');
Route::get('/admin/payment-gateways', [PageController::class, 'adminPaymentGateways'])->name('admin.payment-gateways');
Route::get('/admin/plans', [PageController::class, 'adminPlans'])->name('admin.plans');
Route::get('/admin/offers', [PageController::class, 'adminOffers'])->name('admin.offers');
Route::get('/admin/revenue', [PageController::class, 'adminRevenue'])->name('admin.revenue');
Route::get('/admin/settings', [PageController::class, 'adminSettings'])->name('admin.settings');
Route::get('/admin/whatsapp', [PageController::class, 'adminWhatsApp'])->name('admin.whatsapp');
Route::get('/admin/subscriptions', [PageController::class, 'adminSubscriptions'])->name('admin.subscriptions');
Route::get('/admin/testimonials', [PageController::class, 'adminTestimonials'])->name('admin.testimonials');
Route::get('/admin/users', [PageController::class, 'adminUsers'])->name('admin.users');

// Attribution short links
Route::get('/g/{slug}', [GrowthRedirectController::class, 'redirect'])->name('growth.redirect');

// OAuth callback for Growth Engine
Route::get('/oauth/growth/callback', [GrowthOAuthCallbackController::class, 'callback'])->name('growth.oauth.callback');

// Order receipt (signed URL from WhatsApp)
Route::get('/order/{order}/receipt', function (Order $order) {
    $order->load(['company', 'orderProducts']);

    return response()->view('order-receipt', ['order' => $order]);
})->middleware('signed')->name('orders.receipt');

// Paid digital access portal + private downloads (signed URLs)
Route::get('/order/{order}/access', [OrderDigitalAccessController::class, 'portal'])
    ->middleware('signed')
    ->name('orders.access');
Route::get('/order/{order}/download/{orderProduct}', [OrderDigitalAccessController::class, 'download'])
    ->middleware('signed')
    ->name('orders.digital-download');

// Public booking pages + calendar feed
Route::get('/book/{slug}', [PublicBookingController::class, 'show'])->name('book.show');
Route::post('/book/{slug}', [PublicBookingController::class, 'store'])->name('book.store');
Route::get('/book/{slug}/slots', [PublicBookingController::class, 'slots'])->name('book.slots');
Route::get('/book/{slug}/confirmation/{token}', [PublicBookingController::class, 'confirmation'])->name('book.confirmation');
Route::get('/book/{slug}/calendar.ics', [PublicBookingController::class, 'calendarFeed'])->name('book.calendar');
Route::get('/bookings/{booking}/ics', [PublicBookingController::class, 'bookingIcs'])->name('bookings.ics');
