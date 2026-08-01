<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('default_timezone')->default('UTC')->after('maintenance_mode');
            $table->text('maintenance_message')->nullable()->after('default_timezone');
            $table->boolean('allow_new_registrations')->default(true)->after('maintenance_message');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['default_timezone', 'maintenance_message', 'allow_new_registrations']);
        });
    }
};
