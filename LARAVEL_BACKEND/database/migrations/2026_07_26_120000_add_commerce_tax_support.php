<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->decimal('rate', 8, 4);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(false)->after('display_currency');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('price')
                ->constrained('tax_rates')
                ->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('delivery_address');
            $table->decimal('tax_total', 12, 2)->default(0)->after('subtotal');
            $table->json('tax_breakdown')->nullable()->after('tax_total');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('price')
                ->constrained('tax_rates')
                ->nullOnDelete();
            $table->string('tax_name')->nullable()->after('tax_rate_id');
            $table->string('tax_code', 32)->nullable()->after('tax_name');
            $table->decimal('tax_rate', 8, 4)->nullable()->after('tax_code');
            $table->boolean('tax_inclusive')->default(false)->after('tax_rate');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_inclusive');
            $table->decimal('line_subtotal', 12, 2)->default(0)->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn([
                'tax_name',
                'tax_code',
                'tax_rate',
                'tax_inclusive',
                'tax_amount',
                'line_subtotal',
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_total', 'tax_breakdown']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('tax_enabled');
        });

        Schema::dropIfExists('tax_rates');
    }
};
