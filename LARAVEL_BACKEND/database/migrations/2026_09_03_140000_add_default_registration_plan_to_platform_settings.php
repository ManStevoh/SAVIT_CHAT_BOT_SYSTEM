<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'default_registration_plan_slug')) {
                $table->string('default_registration_plan_slug')->nullable()->after('allow_new_registrations');
            }
            if (! Schema::hasColumn('platform_settings', 'force_default_registration_plan')) {
                $table->boolean('force_default_registration_plan')->default(false)->after('default_registration_plan_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_settings', 'force_default_registration_plan')) {
                $table->dropColumn('force_default_registration_plan');
            }
            if (Schema::hasColumn('platform_settings', 'default_registration_plan_slug')) {
                $table->dropColumn('default_registration_plan_slug');
            }
        });
    }
};
