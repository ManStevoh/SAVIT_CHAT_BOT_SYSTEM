<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_vision_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('analysis_type', 32)->default('general');
            $table->json('labels')->nullable();
            $table->json('product_matches')->nullable();
            $table->boolean('warranty_detected')->default(false);
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('raw_response')->nullable();
            $table->string('model_used', 64)->nullable();
            $table->timestamps();

            $table->unique('message_id');
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('company_brain_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snapshot_at');
            $table->json('commerce_data')->nullable();
            $table->json('growth_data')->nullable();
            $table->text('summary_text')->nullable();
            $table->json('digest')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'snapshot_at']);
        });

        Schema::create('owner_analytics_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('question', 500);
            $table->string('period', 8)->default('30d');
            $table->string('status', 24)->default('completed');
            $table->json('evidence')->nullable();
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('model_used', 64)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_analytics_investigations');
        Schema::dropIfExists('company_brain_snapshots');
        Schema::dropIfExists('message_vision_analyses');
    }
};
