<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('license_key_mode', 16)->default('none')->after('digital_file_size');
            $table->string('license_key_prefix', 32)->nullable()->after('license_key_mode');
            $table->unsignedInteger('access_expires_days')->nullable()->after('license_key_prefix');
        });

        Schema::create('product_license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('license_key');
            $table->string('status', 16)->default('available'); // available, assigned
            $table->foreignId('order_product_id')->nullable()->constrained('order_products')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'license_key']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_license_keys');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['license_key_mode', 'license_key_prefix', 'access_expires_days']);
        });
    }
};
