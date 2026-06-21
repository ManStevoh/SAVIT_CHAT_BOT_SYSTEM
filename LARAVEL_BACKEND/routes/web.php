<?php

use App\Http\Controllers\GrowthOAuthCallbackController;
use App\Http\Controllers\GrowthRedirectController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Attribution short links: /g/{slug} → track click → redirect (WhatsApp or landing)
Route::get('/g/{slug}', [GrowthRedirectController::class, 'redirect'])->name('growth.redirect');

// OAuth callback for Growth Engine social platforms (Meta, LinkedIn, TikTok, X)
Route::get('/oauth/growth/callback', [GrowthOAuthCallbackController::class, 'callback'])->name('growth.oauth.callback');

// Inline handler so production deploys cannot miss a separate controller class (signed URL from WhatsApp).
Route::get('/order/{order}/receipt', function (Order $order) {
    $order->load(['company', 'orderProducts']);

    return response()->view('order-receipt', ['order' => $order]);
})->middleware('signed')->name('orders.receipt');
