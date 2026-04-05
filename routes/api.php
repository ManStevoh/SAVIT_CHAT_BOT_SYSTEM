<?php

use App\Http\Controllers\Api\Admin\AIUsageController;
use App\Http\Controllers\Api\Admin\CompanyController;
use App\Http\Controllers\Api\Admin\ImpersonateController;
use App\Http\Controllers\Api\Admin\LandingFaqController;
use App\Http\Controllers\Api\Admin\LogController;
use App\Http\Controllers\Api\Admin\OverviewController;
use App\Http\Controllers\Api\Admin\PaymentGatewayController;
use App\Http\Controllers\Api\Admin\PlatformSettingsController;
use App\Http\Controllers\Api\Admin\RevenueController;
use App\Http\Controllers\Api\Admin\TestimonialController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AppBrandingController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Company\AnalyticsController;
use App\Http\Controllers\Api\Company\ChatController;
use App\Http\Controllers\Api\Company\ChatMessageController;
use App\Http\Controllers\Api\Company\CustomerController;
use App\Http\Controllers\Api\Company\ExportController;
use App\Http\Controllers\Api\Company\FaqController;
use App\Http\Controllers\Api\Company\ImportController;
use App\Http\Controllers\Api\Company\MpesaCheckoutController;
use App\Http\Controllers\Api\Company\OrderController;
use App\Http\Controllers\Api\Company\ProductController;
use App\Http\Controllers\Api\Company\SettingsController;
use App\Http\Controllers\Api\Company\StripeCheckoutController;
use App\Http\Controllers\Api\Company\SubscriptionController;
use App\Http\Controllers\Api\Company\TeamController;
use App\Http\Controllers\Api\Company\WhatsAppController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LandingController;
use App\Http\Controllers\Api\MpesaCallbackController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Public (no auth)
Route::get('plans', [PlanController::class, 'index']);
Route::get('app-branding', [AppBrandingController::class, 'show']);
Route::get('landing', [LandingController::class, 'index']);

// Stripe webhook (no auth; verified by Stripe signature)
Route::post('stripe/webhook', StripeWebhookController::class);

// M-Pesa callback (no auth; called by Safaricom)
Route::post('mpesa/callback', MpesaCallbackController::class);

// WhatsApp webhook (no auth; Meta calls for verification and incoming messages)
Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);

// Auth (no auth required)
Route::prefix('auth')->group(function () {
    Route::get('verify-email', EmailVerificationController::class)->name('api.verification.verify');
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Company (auth required; subscription must be active except for subscription/checkout routes)
Route::prefix('company')->middleware(['auth:sanctum', 'subscription.active'])->group(function () {
    Route::get('chats', [ChatController::class, 'index']);
    Route::post('chats/{chatId}/hand-back', [ChatController::class, 'handBack']);
    Route::get('chats/{chatId}/messages', [ChatMessageController::class, 'index']);
    Route::post('chats/{chatId}/messages', [ChatMessageController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::patch('orders/{order}', [OrderController::class, 'updateStatus']);
    Route::get('customers/stats', [CustomerController::class, 'stats']);
    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products/{product}/variants', [ProductController::class, 'storeVariant']);
    Route::put('product-variants/{productVariant}', [ProductController::class, 'updateVariant']);
    Route::delete('product-variants/{productVariant}', [ProductController::class, 'destroyVariant']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::get('faqs', [FaqController::class, 'index']);
    Route::apiResource('faqs', FaqController::class)->only(['store', 'update', 'destroy']);
    Route::get('analytics', [AnalyticsController::class, 'index']);
    Route::get('subscription', [SubscriptionController::class, 'show']);
    Route::get('subscription/invoices', [SubscriptionController::class, 'invoices']);
    Route::get('subscription/usage', [SubscriptionController::class, 'usage']);
    Route::get('team', [TeamController::class, 'index']);
    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::post('whatsapp/connect', [WhatsAppController::class, 'connect']);
    Route::post('whatsapp/disconnect', [WhatsAppController::class, 'disconnect']);
    Route::get('whatsapp/status', [WhatsAppController::class, 'status']);
    Route::get('whatsapp/numbers', [WhatsAppController::class, 'numbers']);
    Route::post('export', [ExportController::class, 'export']);
    Route::get('export/download/{filename}', [ExportController::class, 'download']);
    Route::post('import/products', [ImportController::class, 'importProducts']);
    Route::post('import/faqs', [ImportController::class, 'importFaqs']);
    Route::post('checkout', [StripeCheckoutController::class, 'createSession']);
    Route::post('billing-portal', [StripeCheckoutController::class, 'createPortalSession']);
    Route::post('mpesa/initiate', [MpesaCheckoutController::class, 'initiate']);
});

// Admin (auth:sanctum + admin role)
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('overview', [OverviewController::class, 'index']);
    Route::get('companies', [CompanyController::class, 'index']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::put('companies/{company}', [CompanyController::class, 'update']);
    Route::patch('companies/{company}', [CompanyController::class, 'updateStatus']);
    Route::get('users', [UserController::class, 'index']);
    Route::patch('users/{user}', [UserController::class, 'updateStatus']);
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
    Route::get('subscriptions', [App\Http\Controllers\Api\Admin\SubscriptionController::class, 'index']);
    Route::get('plans', [App\Http\Controllers\Api\Admin\PlanController::class, 'index']);
    Route::post('plans', [App\Http\Controllers\Api\Admin\PlanController::class, 'store']);
    Route::put('plans/{plan}', [App\Http\Controllers\Api\Admin\PlanController::class, 'update']);
    Route::delete('plans/{plan}', [App\Http\Controllers\Api\Admin\PlanController::class, 'destroy']);
    Route::get('revenue', [RevenueController::class, 'index']);
    Route::get('ai-usage', [AIUsageController::class, 'index']);
    Route::get('logs', [LogController::class, 'index']);
    Route::get('payment-gateways', [PaymentGatewayController::class, 'index']);
    Route::put('payment-gateways/{slug}', [PaymentGatewayController::class, 'update']);
    Route::get('settings', [PlatformSettingsController::class, 'show']);
    Route::put('settings', [PlatformSettingsController::class, 'update']);
    Route::post('settings', [PlatformSettingsController::class, 'update']);
    Route::post('settings/test-email', [PlatformSettingsController::class, 'testEmail']);
    Route::post('export', [App\Http\Controllers\Api\Admin\ExportController::class, 'export']);
    Route::get('export/download/{filename}', [App\Http\Controllers\Api\Admin\ExportController::class, 'download']);
    Route::post('impersonate/user/{user}', [ImpersonateController::class, 'impersonateUser']);
    Route::post('impersonate/company/{company}', [ImpersonateController::class, 'impersonateCompany']);
    Route::get('testimonials', [TestimonialController::class, 'index']);
    Route::post('testimonials', [TestimonialController::class, 'store']);
    Route::put('testimonials/{testimonial}', [TestimonialController::class, 'update']);
    Route::delete('testimonials/{testimonial}', [TestimonialController::class, 'destroy']);
    Route::get('landing-faqs', [LandingFaqController::class, 'index']);
    Route::post('landing-faqs', [LandingFaqController::class, 'store']);
    Route::put('landing-faqs/{landing_faq}', [LandingFaqController::class, 'update']);
    Route::delete('landing-faqs/{landing_faq}', [LandingFaqController::class, 'destroy']);
});
