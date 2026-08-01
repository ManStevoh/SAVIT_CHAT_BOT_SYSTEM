<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        DB::table('platform_settings')
            ->whereIn('platform_name', ['Essem', 'Essem Chat'])
            ->update(['platform_name' => 'RelayIQ']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        DB::table('platform_settings')
            ->where('platform_name', 'RelayIQ')
            ->update(['platform_name' => 'Essem Chat']);
    }
};
