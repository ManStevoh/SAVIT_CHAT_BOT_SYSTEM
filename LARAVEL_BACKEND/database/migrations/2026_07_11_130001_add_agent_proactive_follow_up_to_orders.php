<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'agent_proactive_follow_up_at')) {
                $table->timestamp('agent_proactive_follow_up_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'agent_proactive_follow_up_at')) {
                $table->dropColumn('agent_proactive_follow_up_at');
            }
        });
    }
};
