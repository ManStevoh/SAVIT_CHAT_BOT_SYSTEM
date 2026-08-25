<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_offers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 64)->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 20); // percent | fixed
            $table->decimal('discount_value', 12, 2);
            $table->string('currency', 8)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->unsignedInteger('max_per_company')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('first_payment_only')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_offer_id')->constrained('subscription_offers')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference', 120)->nullable()->index();
            $table->decimal('original_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2);
            $table->decimal('final_amount', 12, 2);
            $table->string('currency', 8)->nullable();
            $table->string('status', 24)->default('applied'); // applied | pending | void
            $table->timestamps();

            $table->index(['company_id', 'subscription_offer_id']);
        });

        Schema::create('subscription_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->date('target_end_date');
            $table->string('channel', 24)->default('email');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['subscription_id', 'days_before', 'target_end_date', 'channel'], 'sub_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminder_logs');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('subscription_offers');
    }
};
