<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'agent_business_goals')) {
                $table->json('agent_business_goals')->nullable()->after('agent_commerce_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'agent_proactive_enabled')) {
                $table->boolean('agent_proactive_enabled')->default(false)->after('agent_business_goals');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'agent_proactive_enabled')) {
                $table->dropColumn('agent_proactive_enabled');
            }
            if (Schema::hasColumn('company_settings', 'agent_business_goals')) {
                $table->dropColumn('agent_business_goals');
            }
        });
    }
};
