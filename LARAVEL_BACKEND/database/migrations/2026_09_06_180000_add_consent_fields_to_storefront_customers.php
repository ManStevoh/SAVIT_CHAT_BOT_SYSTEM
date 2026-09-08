<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('storefront_customers')) {
            return;
        }

        Schema::table('storefront_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_customers', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('password');
            }
            if (! Schema::hasColumn('storefront_customers', 'marketing_consent')) {
                $table->boolean('marketing_consent')->default(false)->after('terms_accepted_at');
            }
            if (! Schema::hasColumn('storefront_customers', 'marketing_consent_at')) {
                $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('storefront_customers')) {
            return;
        }

        Schema::table('storefront_customers', function (Blueprint $table) {
            foreach (['terms_accepted_at', 'marketing_consent', 'marketing_consent_at'] as $col) {
                if (Schema::hasColumn('storefront_customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
