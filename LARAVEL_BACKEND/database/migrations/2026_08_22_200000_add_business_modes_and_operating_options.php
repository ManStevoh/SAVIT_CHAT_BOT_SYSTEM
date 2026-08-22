<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'business_mode')) {
                $table->string('business_mode', 32)->default('hybrid')->after('dine_in_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'enable_products_catalog')) {
                $table->boolean('enable_products_catalog')->default(true)->after('business_mode');
            }
            if (! Schema::hasColumn('company_settings', 'enable_bookings')) {
                $table->boolean('enable_bookings')->default(true)->after('enable_products_catalog');
            }
            if (! Schema::hasColumn('company_settings', 'enable_dine_in')) {
                $table->boolean('enable_dine_in')->default(false)->after('enable_bookings');
            }
            if (! Schema::hasColumn('company_settings', 'dine_in_qr_target')) {
                $table->string('dine_in_qr_target', 32)->default('web_menu')->after('enable_dine_in');
            }
            if (! Schema::hasColumn('company_settings', 'dine_in_payment_timing')) {
                $table->string('dine_in_payment_timing', 32)->default('pay_upfront')->after('dine_in_qr_target');
            }
        });

        Schema::table('booking_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_settings', 'payment_requirement')) {
                $table->string('payment_requirement', 32)->default('at_venue')->after('is_enabled');
            }
            if (! Schema::hasColumn('booking_settings', 'whatsapp_booking_mode')) {
                $table->string('whatsapp_booking_mode', 32)->default('hybrid')->after('payment_requirement');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_settings', function (Blueprint $table) {
            if (Schema::hasColumn('booking_settings', 'whatsapp_booking_mode')) {
                $table->dropColumn('whatsapp_booking_mode');
            }
            if (Schema::hasColumn('booking_settings', 'payment_requirement')) {
                $table->dropColumn('payment_requirement');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'dine_in_payment_timing')) {
                $table->dropColumn('dine_in_payment_timing');
            }
            if (Schema::hasColumn('company_settings', 'dine_in_qr_target')) {
                $table->dropColumn('dine_in_qr_target');
            }
            if (Schema::hasColumn('company_settings', 'enable_dine_in')) {
                $table->dropColumn('enable_dine_in');
            }
            if (Schema::hasColumn('company_settings', 'enable_bookings')) {
                $table->dropColumn('enable_bookings');
            }
            if (Schema::hasColumn('company_settings', 'enable_products_catalog')) {
                $table->dropColumn('enable_products_catalog');
            }
            if (Schema::hasColumn('company_settings', 'business_mode')) {
                $table->dropColumn('business_mode');
            }
        });
    }
};
