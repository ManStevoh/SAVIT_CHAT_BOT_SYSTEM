<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('stripe_subscription_id'); // stripe, mpesa
            $table->string('external_payment_id')->nullable()->after('payment_method'); // M-Pesa TransactionID or other gateway reference
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'external_payment_id']);
        });
    }
};
