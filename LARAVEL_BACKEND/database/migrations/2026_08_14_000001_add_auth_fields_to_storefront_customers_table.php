<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storefront_customers')) {
            Schema::table('storefront_customers', function (Blueprint $table) {
                if (! Schema::hasColumn('storefront_customers', 'password')) {
                    $table->string('password')->nullable()->after('email');
                }
                if (! Schema::hasColumn('storefront_customers', 'remember_token')) {
                    $table->rememberToken()->after('password');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('storefront_customers')) {
            Schema::table('storefront_customers', function (Blueprint $table) {
                if (Schema::hasColumn('storefront_customers', 'password')) {
                    $table->dropColumn('password');
                }
                if (Schema::hasColumn('storefront_customers', 'remember_token')) {
                    $table->dropColumn('remember_token');
                }
            });
        }
    }
};
