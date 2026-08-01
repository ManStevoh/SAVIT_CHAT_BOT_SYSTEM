<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->foreignId('reply_to_message_id')
                    ->nullable()
                    ->after('whatsapp_message_id')
                    ->constrained('messages')
                    ->nullOnDelete();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index('whatsapp_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'reply_to_message_id')) {
                $table->dropConstrainedForeignId('reply_to_message_id');
            }
            try {
                $table->dropIndex(['whatsapp_message_id']);
            } catch (\Throwable) {
                // ignore
            }
        });
    }
};
