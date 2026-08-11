<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_intent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->string('conversation_step')->nullable();
            $table->text('incoming_message');
            $table->string('predicted_intent');
            $table->decimal('confidence', 4, 3);
            $table->json('entities')->nullable();
            $table->boolean('requires_clarification')->default(false);
            $table->boolean('short_circuited')->default(false);
            $table->string('executed_intent')->nullable();
            $table->string('legacy_route')->nullable();
            $table->boolean('agreed_with_legacy')->nullable();
            $table->boolean('shadow_mode')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['predicted_intent', 'confidence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_intent_logs');
    }
};
