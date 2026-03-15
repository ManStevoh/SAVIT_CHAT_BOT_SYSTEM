<?php

use Illuminate\Support\Facades\Route;

// Public (no auth)
Route::get('plans', [App\Http\Controllers\Api\PlanController::class, 'index']);
Route::get('app-branding', [App\Http\Controllers\Api\AppBrandingController::class, 'show']);

// Stripe webhook (no auth; verified by Stripe signature)
Route::post('stripe/webhook', App\Http\Controllers\Api\StripeWebhookController::class);

// M-Pesa callback (no auth; called by Safaricom)
Route::post('mpesa/callback', App\Http\Controllers\Api\MpesaCallbackController::class);

// WhatsApp webhook (no auth; Meta calls for verification and incoming messages)
Route::get('whatsapp/webhook', [App\Http\Controllers\Api\WhatsAppWebhookController::class, 'verify']);
Route::post('whatsapp/webhook', [App\Http\Controllers\Api\WhatsAppWebhookController::class, 'receive']);

// Auth (no auth required)
Route::prefix('auth')->group(function () {
    Route::post('login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('register', [App\Http\Controllers\Api\AuthController::class, 'register']);
    Route::post('forgot-password', [App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [App\Http\Controllers\Api\AuthController::class, 'resetPassword']);
    Route::post('logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Company (auth required)
Route::prefix('company')->middleware('auth:sanctum')->group(function () {
    Route::get('chats', [App\Http\Controllers\Api\Company\ChatController::class, 'index']);
    Route::get('chats/{chatId}/messages', [App\Http\Controllers\Api\Company\ChatMessageController::class, 'index']);
    Route::post('chats/{chatId}/messages', [App\Http\Controllers\Api\Company\ChatMessageController::class, 'store']);
    Route::get('orders', [App\Http\Controllers\Api\Company\OrderController::class, 'index']);
    Route::patch('orders/{order}', [App\Http\Controllers\Api\Company\OrderController::class, 'updateStatus']);
    Route::get('customers', [App\Http\Controllers\Api\Company\CustomerController::class, 'index']);
    Route::get('products', [App\Http\Controllers\Api\Company\ProductController::class, 'index']);
    Route::apiResource('products', App\Http\Controllers\Api\Company\ProductController::class)->only(['store', 'update', 'destroy']);
    Route::get('faqs', [App\Http\Controllers\Api\Company\FaqController::class, 'index']);
    Route::apiResource('faqs', App\Http\Controllers\Api\Company\FaqController::class)->only(['store', 'update', 'destroy']);
    Route::get('analytics', [App\Http\Controllers\Api\Company\AnalyticsController::class, 'index']);
    Route::get('subscription', [App\Http\Controllers\Api\Company\SubscriptionController::class, 'show']);
    Route::get('subscription/invoices', [App\Http\Controllers\Api\Company\SubscriptionController::class, 'invoices']);
    Route::get('settings', [App\Http\Controllers\Api\Company\SettingsController::class, 'show']);
    Route::put('settings', [App\Http\Controllers\Api\Company\SettingsController::class, 'update']);
    Route::post('whatsapp/connect', [App\Http\Controllers\Api\Company\WhatsAppController::class, 'connect']);
    Route::post('whatsapp/disconnect', [App\Http\Controllers\Api\Company\WhatsAppController::class, 'disconnect']);
    Route::get('whatsapp/status', [App\Http\Controllers\Api\Company\WhatsAppController::class, 'status']);
    Route::post('checkout', [App\Http\Controllers\Api\Company\StripeCheckoutController::class, 'createSession']);
    Route::post('billing-portal', [App\Http\Controllers\Api\Company\StripeCheckoutController::class, 'createPortalSession']);
    Route::post('mpesa/initiate', [App\Http\Controllers\Api\Company\MpesaCheckoutController::class, 'initiate']);
});

// Admin (auth:sanctum + admin role)
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('overview', [App\Http\Controllers\Api\Admin\OverviewController::class, 'index']);
    Route::get('companies', [App\Http\Controllers\Api\Admin\CompanyController::class, 'index']);
    Route::get('companies/{company}', [App\Http\Controllers\Api\Admin\CompanyController::class, 'show']);
    Route::put('companies/{company}', [App\Http\Controllers\Api\Admin\CompanyController::class, 'update']);
    Route::patch('companies/{company}', [App\Http\Controllers\Api\Admin\CompanyController::class, 'updateStatus']);
    Route::get('users', [App\Http\Controllers\Api\Admin\UserController::class, 'index']);
    Route::patch('users/{user}', [App\Http\Controllers\Api\Admin\UserController::class, 'updateStatus']);
    Route::post('users/{user}/reset-password', [App\Http\Controllers\Api\Admin\UserController::class, 'resetPassword']);
    Route::get('subscriptions', [App\Http\Controllers\Api\Admin\SubscriptionController::class, 'index']);
    Route::get('plans', [App\Http\Controllers\Api\Admin\PlanController::class, 'index']);
    Route::post('plans', [App\Http\Controllers\Api\Admin\PlanController::class, 'store']);
    Route::put('plans/{plan}', [App\Http\Controllers\Api\Admin\PlanController::class, 'update']);
    Route::delete('plans/{plan}', [App\Http\Controllers\Api\Admin\PlanController::class, 'destroy']);
    Route::get('revenue', [App\Http\Controllers\Api\Admin\RevenueController::class, 'index']);
    Route::get('ai-usage', [App\Http\Controllers\Api\Admin\AIUsageController::class, 'index']);
    Route::get('logs', [App\Http\Controllers\Api\Admin\LogController::class, 'index']);
    Route::get('payment-gateways', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'index']);
    Route::put('payment-gateways/{slug}', [App\Http\Controllers\Api\Admin\PaymentGatewayController::class, 'update']);
    Route::get('settings', [App\Http\Controllers\Api\Admin\PlatformSettingsController::class, 'show']);
    Route::put('settings', [App\Http\Controllers\Api\Admin\PlatformSettingsController::class, 'update']);
    Route::post('settings', [App\Http\Controllers\Api\Admin\PlatformSettingsController::class, 'update']);
    Route::post('settings/test-email', [App\Http\Controllers\Api\Admin\PlatformSettingsController::class, 'testEmail']);
    Route::post('export', [App\Http\Controllers\Api\Admin\ExportController::class, 'export']);
    Route::post('impersonate/user/{user}', [App\Http\Controllers\Api\Admin\ImpersonateController::class, 'impersonateUser']);
    Route::post('impersonate/company/{company}', [App\Http\Controllers\Api\Admin\ImpersonateController::class, 'impersonateCompany']);
});
