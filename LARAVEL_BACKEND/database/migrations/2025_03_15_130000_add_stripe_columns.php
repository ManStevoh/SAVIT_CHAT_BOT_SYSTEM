<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_price_id')->nullable()->after('sort_order');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('stripe_price_id');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_subscription_id');
        });
    }
};
