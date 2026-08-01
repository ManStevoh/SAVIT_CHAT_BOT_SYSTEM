<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->string('question_fingerprint', 64)->nullable()->after('customer_message');
            $table->index(['company_id', 'question_fingerprint'], 'learning_company_fingerprint_idx');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->dropIndex('learning_company_fingerprint_idx');
            $table->dropColumn('question_fingerprint');
        });
    }
};
