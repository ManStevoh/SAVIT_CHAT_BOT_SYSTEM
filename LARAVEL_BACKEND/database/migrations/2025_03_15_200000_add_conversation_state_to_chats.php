<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->string('conversation_step', 50)->nullable()->after('agent_handling_at');
            $table->json('order_draft')->nullable()->after('conversation_step');
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn(['conversation_step', 'order_draft']);
        });
    }
};
