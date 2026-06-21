<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('primary_color', 50)->nullable()->after('platform_name');
            $table->string('secondary_color', 50)->nullable()->after('primary_color');
            $table->string('app_logo')->nullable()->after('secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color', 'app_logo']);
        });
    }
};
