<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'agent_morning_brief_whatsapp_enabled')) {
                $table->boolean('agent_morning_brief_whatsapp_enabled')->default(false)->after('agent_voice_reply_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'owner_whatsapp_phone')) {
                $table->string('owner_whatsapp_phone', 32)->nullable()->after('agent_morning_brief_whatsapp_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'consciousness_last_sensed_at')) {
                $table->timestamp('consciousness_last_sensed_at')->nullable()->after('owner_whatsapp_phone');
            }
        });

        Schema::table('commerce_briefs', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_briefs', 'pushed_to_owner_at')) {
                $table->timestamp('pushed_to_owner_at')->nullable()->after('executive_decisions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_briefs', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_briefs', 'pushed_to_owner_at')) {
                $table->dropColumn('pushed_to_owner_at');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            foreach (['consciousness_last_sensed_at', 'owner_whatsapp_phone', 'agent_morning_brief_whatsapp_enabled'] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
