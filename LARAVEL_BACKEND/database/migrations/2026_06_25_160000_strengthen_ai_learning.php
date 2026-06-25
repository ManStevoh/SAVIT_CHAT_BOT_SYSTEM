<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->json('question_embedding')->nullable()->after('question_fingerprint');
            $table->unsignedSmallInteger('use_count')->default(0)->after('language');
            $table->timestamp('last_used_at')->nullable()->after('use_count');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->tinyInteger('learning_feedback')->nullable()->after('reply_source')
                ->comment('-1 negative, 1 positive');
            $table->foreignId('learning_sample_id')->nullable()->after('learning_feedback')
                ->constrained('conversation_learning_samples')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['learning_sample_id']);
            $table->dropColumn(['learning_feedback', 'learning_sample_id']);
        });

        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->dropColumn(['question_embedding', 'use_count', 'last_used_at']);
        });
    }
};
