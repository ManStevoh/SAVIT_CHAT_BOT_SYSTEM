<?php

use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Inline handler so production deploys cannot miss a separate controller class (signed URL from WhatsApp).
Route::get('/order/{order}/receipt', function (Order $order) {
    $order->load(['company', 'orderProducts']);

    return response()->view('order-receipt', ['order' => $order]);
})->middleware('signed')->name('orders.receipt');
