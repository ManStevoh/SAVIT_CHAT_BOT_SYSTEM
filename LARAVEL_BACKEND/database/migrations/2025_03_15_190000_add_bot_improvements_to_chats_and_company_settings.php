<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->timestamp('agent_handling_at')->nullable()->after('ai_handled');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->text('fallback_message')->nullable()->after('ai_tone');
            $table->text('away_message')->nullable()->after('fallback_message');
            $table->string('timezone', 50)->nullable()->after('away_message')->default('UTC');
            $table->json('working_hours')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('agent_handling_at');
        });
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['fallback_message', 'away_message', 'timezone', 'working_hours']);
        });
    }
};
