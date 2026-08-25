<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'voice_transcript')) {
                    $table->text('voice_transcript')->nullable()->after('content');
                }
                if (! Schema::hasColumn('messages', 'voice_duration')) {
                    $table->integer('voice_duration')->nullable()->after('voice_transcript');
                }
            });
        }

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'agent_voice_reply_mode')) {
                $table->string('agent_voice_reply_mode', 40)->default('dual_text_and_voice')->after('agent_voice_reply_enabled');
            }
            if (! Schema::hasColumn('company_settings', 'agent_voice_id')) {
                $table->string('agent_voice_id', 40)->default('nova')->after('agent_voice_reply_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['voice_transcript', 'voice_duration']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['agent_voice_reply_mode', 'agent_voice_id']);
        });
    }
};
