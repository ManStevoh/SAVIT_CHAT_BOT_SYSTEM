<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_accounts', 'meta_app_secret')) {
                $table->text('meta_app_secret')->nullable()->after('verify_token');
            }
            if (! Schema::hasColumn('whatsapp_accounts', 'connected_via')) {
                $table->string('connected_via', 32)->nullable()->after('meta_app_secret');
            }
        });

        // Existing rows default to embedded (platform App Secret). Manual reconnect/fix can switch to manual.
        if (Schema::hasColumn('whatsapp_accounts', 'connected_via')) {
            \Illuminate\Support\Facades\DB::table('whatsapp_accounts')
                ->whereNull('connected_via')
                ->update(['connected_via' => 'embedded']);
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_accounts', 'connected_via')) {
                $table->dropColumn('connected_via');
            }
            if (Schema::hasColumn('whatsapp_accounts', 'meta_app_secret')) {
                $table->dropColumn('meta_app_secret');
            }
        });
    }
};
