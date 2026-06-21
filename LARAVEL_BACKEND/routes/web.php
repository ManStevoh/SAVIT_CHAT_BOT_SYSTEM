<?php

use App\Http\Controllers\GrowthOAuthCallbackController;
use App\Http\Controllers\GrowthRedirectController;
use App\Http\Controllers\Web\PageController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/order-paid', [PageController::class, 'orderPaid'])->name('order-paid');

// Auth pages
Route::get('/login', [PageController::class, 'login'])->name('login');
Route::get('/register', [PageController::class, 'register'])->name('register');
Route::get('/forgot-password', [PageController::class, 'forgotPassword'])->name('password.request');
Route::get('/reset-password', [PageController::class, 'resetPassword'])->name('password.reset');

// Company dashboard
Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
Route::get('/dashboard/analytics', [PageController::class, 'dashboardAnalytics'])->name('dashboard.analytics');
Route::get('/dashboard/chats', [PageController::class, 'dashboardChats'])->name('dashboard.chats');
Route::get('/dashboard/customers', [PageController::class, 'dashboardCustomers'])->name('dashboard.customers');
Route::get('/dashboard/faq', [PageController::class, 'dashboardFaq'])->name('dashboard.faq');
Route::get('/dashboard/growth', [PageController::class, 'dashboardGrowth'])->name('dashboard.growth');
Route::get('/dashboard/orders', [PageController::class, 'dashboardOrders'])->name('dashboard.orders');
Route::get('/dashboard/products', [PageController::class, 'dashboardProducts'])->name('dashboard.products');
Route::get('/dashboard/settings', [PageController::class, 'dashboardSettings'])->name('dashboard.settings');
Route::get('/dashboard/subscription', [PageController::class, 'dashboardSubscription'])->name('dashboard.subscription');

// Super admin
Route::get('/admin', [PageController::class, 'admin'])->name('admin');
Route::get('/admin/ai-usage', [PageController::class, 'adminAiUsage'])->name('admin.ai-usage');
Route::get('/admin/companies', [PageController::class, 'adminCompanies'])->name('admin.companies');
Route::get('/admin/growth', [PageController::class, 'adminGrowth'])->name('admin.growth');
Route::get('/admin/landing-faqs', [PageController::class, 'adminLandingFaqs'])->name('admin.landing-faqs');
Route::get('/admin/logs', [PageController::class, 'adminLogs'])->name('admin.logs');
Route::get('/admin/payment-gateways', [PageController::class, 'adminPaymentGateways'])->name('admin.payment-gateways');
Route::get('/admin/plans', [PageController::class, 'adminPlans'])->name('admin.plans');
Route::get('/admin/revenue', [PageController::class, 'adminRevenue'])->name('admin.revenue');
Route::get('/admin/settings', [PageController::class, 'adminSettings'])->name('admin.settings');
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
