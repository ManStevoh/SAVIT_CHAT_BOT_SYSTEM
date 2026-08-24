<?php

use App\Http\Controllers\GrowthOAuthCallbackController;
use App\Http\Controllers\GrowthRedirectController;
use App\Http\Controllers\Web\LlmsController;
use App\Http\Controllers\Web\OrderDigitalAccessController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\PublicBookingController;
use App\Http\Controllers\Web\PublicDineInController;
use App\Http\Controllers\Web\PublicStorefrontController;
use App\Http\Controllers\Web\RobotsController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\StorefrontAuthController;
use App\Http\Controllers\Web\WebManifestController;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

// SEO & Search Standards
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])
    ->where('section', 'pages|blog|stores')
    ->name('sitemap.section');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/llms.txt', [LlmsController::class, 'summary'])->name('llms.txt');
Route::get('/llms-full.txt', [LlmsController::class, 'full'])->name('llms.full');
Route::get('/site.webmanifest', WebManifestController::class)->name('webmanifest');
Route::get('/whatsapp-debug-log', function () {
    $storageLog = storage_path('logs/whatsapp_debug.log');
    $publicLog = public_path('whatsapp_debug.txt');

    $content = '';
    if (file_exists($publicLog) && filesize($publicLog) > 0) {
        $content = file_get_contents($publicLog);
    } elseif (file_exists($storageLog) && filesize($storageLog) > 0) {
        $content = file_get_contents($storageLog);
    }

    return response($content !== '' ? $content : "No WhatsApp debug logs recorded yet.\nSend a message on WhatsApp and refresh this URL to see step-by-step pipeline execution.", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Robots-Tag' => 'noindex, nofollow',
    ]);
});

Route::get('/whatsapp_debug.txt', function () {
    $publicLog = public_path('whatsapp_debug.txt');
    $storageLog = storage_path('logs/whatsapp_debug.log');

    $content = '';
    if (file_exists($publicLog) && filesize($publicLog) > 0) {
        $content = file_get_contents($publicLog);
    } elseif (file_exists($storageLog) && filesize($storageLog) > 0) {
        $content = file_get_contents($storageLog);
    }

    return response($content !== '' ? $content : "No WhatsApp debug logs recorded yet.\nSend a message on WhatsApp and refresh this URL.", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Robots-Tag' => 'noindex, nofollow',
    ]);
});

Route::get('/debug.txt', function () {
    $publicLog = public_path('whatsapp_debug.txt');
    $storageLog = storage_path('logs/whatsapp_debug.log');

    $content = '';
    if (file_exists($publicLog) && filesize($publicLog) > 0) {
        $content = file_get_contents($publicLog);
    } elseif (file_exists($storageLog) && filesize($storageLog) > 0) {
        $content = file_get_contents($storageLog);
    }

    return response($content !== '' ? $content : "No WhatsApp debug logs recorded yet.\nSend a message on WhatsApp and refresh this URL.", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Robots-Tag' => 'noindex, nofollow',
    ]);
});

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/solutions', [PageController::class, 'solutions'])->name('solutions');
Route::get('/features', [PageController::class, 'features'])->name('features');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/blog', [PageController::class, 'blog'])->name('blog');
Route::get('/blog/{slug}', [PageController::class, 'blogShow'])->name('blog.show');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');
Route::get('/whatsapp-ai-sales-agent', [PageController::class, 'whatsappAiSalesAgent'])->name('seo.whatsapp-ai-sales-agent');
Route::get('/whatsapp-sales-automation', [PageController::class, 'whatsappSalesAutomation'])->name('seo.whatsapp-sales-automation');
Route::get('/whatsapp-chatbot', [PageController::class, 'whatsappChatbot'])->name('seo.whatsapp-chatbot');
Route::get('/whatsapp-commerce', [PageController::class, 'whatsappCommerce'])->name('seo.whatsapp-commerce');
Route::get('/whatsapp-lead-generation', [PageController::class, 'whatsappLeadGeneration'])->name('seo.whatsapp-lead-generation');
Route::get('/ai-customer-service', [PageController::class, 'aiCustomerService'])->name('seo.ai-customer-service');
Route::get('/whatsapp-for-ecommerce', [PageController::class, 'whatsappForEcommerce'])->name('seo.whatsapp-for-ecommerce');
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
Route::get('/dashboard/taxes', [PageController::class, 'dashboardTaxes'])->name('dashboard.taxes');
Route::get('/dashboard/bookings', [PageController::class, 'dashboardBookings'])->name('dashboard.bookings');
Route::get('/dashboard/settings', [PageController::class, 'dashboardSettings'])->name('dashboard.settings');
Route::get('/dashboard/subscription', [PageController::class, 'dashboardSubscription'])->name('dashboard.subscription');
Route::get('/dashboard/storefront', [PageController::class, 'dashboardStorefront'])->name('dashboard.storefront');
Route::get('/dashboard/delivery', [PageController::class, 'dashboardDelivery'])->name('dashboard.delivery');
Route::get('/dashboard/dine-in', [PageController::class, 'dashboardDineIn'])->name('dashboard.dine-in');
Route::get('/dashboard/delivery', [PageController::class, 'dashboardDelivery'])->name('dashboard.delivery');

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

// Direct order payment URL from WhatsApp bot
Route::get('/order/{order}/pay', function (Order $order) {
    $company = $order->company;
    if ($company && $company->slug) {
        return redirect()->route('storefront.confirmation', ['slug' => $company->slug, 'order' => $order->id]);
    }

    return redirect('/');
})->name('orders.pay');

// Clear Laravel application cache, config, routes, views, and run pending migrations on cPanel
Route::get('/clear-cache', function () {
    $output = [];

    try {
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        $output[] = 'Config cache cleared.';
    } catch (\Throwable $e) {
        $output[] = 'Config clear error: '.$e->getMessage();
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        $output[] = 'Application cache cleared.';
    } catch (\Throwable $e) {
        $output[] = 'Cache clear error: '.$e->getMessage();
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        $output[] = 'Route cache cleared.';
    } catch (\Throwable $e) {
        $output[] = 'Route clear error: '.$e->getMessage();
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('view:clear');
        $output[] = 'View cache cleared.';
    } catch (\Throwable $e) {
        $output[] = 'View clear error: '.$e->getMessage();
    }

    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output[] = 'Database migrations: '.trim(\Illuminate\Support\Facades\Artisan::output() ?: 'Nothing to migrate.');
    } catch (\Throwable $e) {
        $output[] = 'Migration error: '.$e->getMessage();
    }

    return response("<pre>✅ Laravel Application Maintenance Complete!\n\n".implode("\n", $output).'</pre>', 200, [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
});

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

// Public storefront (Take App-style catalog + cart + checkout)
Route::get('/s/{slug}', [PublicStorefrontController::class, 'show'])->name('storefront.show');
Route::get('/s/{slug}/p/{product}', [PublicStorefrontController::class, 'product'])->name('storefront.product');
Route::get('/s/{slug}/cart', [PublicStorefrontController::class, 'cart'])->name('storefront.cart');
Route::post('/s/{slug}/cart', [PublicStorefrontController::class, 'cartAdd'])->name('storefront.cart.add');
Route::post('/s/{slug}/cart/update', [PublicStorefrontController::class, 'cartUpdate'])->name('storefront.cart.update');
Route::post('/s/{slug}/cart/clear', [PublicStorefrontController::class, 'cartClear'])->name('storefront.cart.clear');
Route::get('/s/{slug}/checkout', [PublicStorefrontController::class, 'checkout'])->name('storefront.checkout');
Route::get('/s/{slug}/checkout/suggest', [PublicStorefrontController::class, 'checkoutSuggest'])->name('storefront.checkout.suggest');
Route::post('/s/{slug}/checkout/quote', [PublicStorefrontController::class, 'checkoutQuote'])->name('storefront.checkout.quote');
Route::post('/s/{slug}/checkout', [PublicStorefrontController::class, 'checkoutStore'])->name('storefront.checkout.store');
Route::get('/s/{slug}/order/{order}', [PublicStorefrontController::class, 'confirmation'])->name('storefront.confirmation');
Route::get('/s/{slug}/track', [PublicStorefrontController::class, 'track'])->name('storefront.track');
Route::post('/s/{slug}/track', [PublicStorefrontController::class, 'trackLookup'])->name('storefront.track.lookup');
Route::get('/s/{slug}/wishlist', [PublicStorefrontController::class, 'wishlist'])->name('storefront.wishlist');
Route::post('/s/{slug}/wishlist/toggle', [PublicStorefrontController::class, 'wishlistToggle'])->name('storefront.wishlist.toggle');
Route::post('/s/{slug}/p/{product}/reviews', [PublicStorefrontController::class, 'reviewStore'])->name('storefront.product.reviews.store');

// Storefront Customer Auth (Email + Password for non-WhatsApp buyers)
Route::post('/s/{slug}/account/register', [StorefrontAuthController::class, 'register'])->name('storefront.account.register');
Route::post('/s/{slug}/account/login', [StorefrontAuthController::class, 'login'])->name('storefront.account.login');
Route::post('/s/{slug}/account/logout', [StorefrontAuthController::class, 'logout'])->name('storefront.account.logout');

// Link-in-bio
Route::get('/b/{slug}', [PublicStorefrontController::class, 'bio'])->name('storefront.bio');

// Public pay + invoice pages (secret-token based, no auth)
Route::get('/pay/{token}', [PublicStorefrontController::class, 'pay'])->name('storefront.pay');
Route::post('/pay/{token}', [PublicStorefrontController::class, 'payAction'])->name('storefront.pay.action');
Route::get('/orders/payment-complete', [PublicStorefrontController::class, 'paystackPaymentComplete'])
    ->name('storefront.paystack.complete');
Route::get('/invoice/{token}', [PublicStorefrontController::class, 'invoice'])->name('storefront.invoice');

// Dine-in table QR
Route::get('/t/{qrToken}', [PublicDineInController::class, 'byToken'])->name('dinein.token');
Route::get('/s/{slug}/table/{qrToken}', [PublicDineInController::class, 'storeTable'])->name('storefront.table');

// Diagnostic: show recent error entries (separate from WhatsApp debug log)
Route::get('/debug-error', function () {
    $file = public_path('error_log.txt');
    if (! file_exists($file)) {
        return response('<pre>No errors logged yet. Try visiting the failing page first, then reload this page.</pre>', 200, ['Content-Type' => 'text/html']);
    }
    $content = file_get_contents($file);
    // Extract just the ERROR summary lines (first line of each entry has the message)
    $lines = explode("\n", $content);
    $summaries = [];
    foreach ($lines as $line) {
        if (str_starts_with($line, '[') && str_contains($line, 'ERROR:')) {
            $summaries[] = $line;
        }
    }
    if (empty($summaries)) {
        return response('<pre>Log file exists but no ERROR entries found. Raw first 2000 chars:\n\n' . htmlspecialchars(substr($content, 0, 2000)) . '</pre>', 200, ['Content-Type' => 'text/html']);
    }
    // Show last 20 error summaries
    $recent = array_slice($summaries, -20);
    $output = "Found " . count($summaries) . " error(s). Showing last " . count($recent) . ":\n\n" . implode("\n\n", $recent);
    return response('<pre>' . htmlspecialchars($output) . '</pre>', 200, ['Content-Type' => 'text/html']);
});
