<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set requires_delivery_address = false for all digital and service products.
     * These product types never need a physical delivery address, but they were
     * originally created with the wrong default (true) before type-aware logic existed.
     */
    public function up(): void
    {
        DB::table('products')
            ->whereIn('product_type', ['digital', 'service'])
            ->where('requires_delivery_address', true)
            ->update(['requires_delivery_address' => false]);
    }

    public function down(): void
    {
        // Intentionally not reversible — we do not want to re-apply incorrect defaults.
    }
};
