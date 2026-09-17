<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'storefront_link_shared_at')) {
                $table->timestamp('storefront_link_shared_at')->nullable()->after('setup_checklist_dismissed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'storefront_link_shared_at')) {
                $table->dropColumn('storefront_link_shared_at');
            }
        });
    }
};
