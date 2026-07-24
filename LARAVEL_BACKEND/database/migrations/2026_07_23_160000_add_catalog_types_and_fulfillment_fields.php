<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 24)->default('physical')->after('category');
            $table->string('fulfillment_type', 24)->default('shipping')->after('product_type');
            $table->boolean('track_inventory')->default(true)->after('image');
            $table->boolean('requires_delivery_address')->default(true)->after('track_inventory');
            $table->string('access_url')->nullable()->after('requires_delivery_address');
            $table->string('service_booking_url')->nullable()->after('access_url');
            $table->text('fulfillment_instructions')->nullable()->after('service_booking_url');
            $table->string('digital_file_path')->nullable()->after('fulfillment_instructions');
            $table->string('digital_file_name')->nullable()->after('digital_file_path');
            $table->string('digital_file_mime')->nullable()->after('digital_file_name');
            $table->unsignedBigInteger('digital_file_size')->nullable()->after('digital_file_mime');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->json('fulfillment_data')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('fulfillment_data');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type',
                'fulfillment_type',
                'track_inventory',
                'requires_delivery_address',
                'access_url',
                'service_booking_url',
                'fulfillment_instructions',
                'digital_file_path',
                'digital_file_name',
                'digital_file_mime',
                'digital_file_size',
            ]);
        });
    }
};
