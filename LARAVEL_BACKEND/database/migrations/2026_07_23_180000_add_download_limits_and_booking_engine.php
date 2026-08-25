<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('max_downloads')->nullable()->after('access_expires_days');
            $table->boolean('bookable')->default(false)->after('max_downloads');
            $table->unsignedInteger('booking_duration_minutes')->nullable()->after('bookable');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->unsignedInteger('download_count')->default(0)->after('fulfillment_data');
        });

        Schema::create('booking_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('timezone', 64)->default('Africa/Nairobi');
            $table->unsignedInteger('default_duration_minutes')->default(30);
            $table->unsignedInteger('buffer_minutes')->default(0);
            $table->unsignedInteger('min_notice_minutes')->default(60);
            $table->unsignedInteger('max_days_ahead')->default(30);
            $table->string('public_slug')->unique();
            $table->string('calendar_feed_token', 64)->unique();
            $table->string('calendar_webhook_url', 2048)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=Sunday … 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['company_id', 'weekday']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_product_id')->nullable()->constrained('order_products')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 24)->default('confirmed'); // pending, confirmed, cancelled, completed
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->string('ics_uid')->unique();
            $table->string('manage_token', 64)->unique();
            $table->timestamps();

            $table->index(['company_id', 'starts_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('booking_availabilities');
        Schema::dropIfExists('booking_settings');

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropColumn('download_count');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['max_downloads', 'bookable', 'booking_duration_minutes']);
        });
    }
};
