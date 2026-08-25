<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_type', 40);
            $table->string('status', 30)->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'agent_type', 'status']);
        });

        Schema::create('product_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('relationship_type', 40);
            $table->string('label', 120)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'related_product_id', 'relationship_type'], 'product_rel_unique');
            $table->index(['company_id', 'relationship_type']);
        });

        Schema::create('commerce_agent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('event_key', 200);
            $table->json('payload')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'event_key']);
            $table->index(['company_id', 'event_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_agent_events');
        Schema::dropIfExists('product_relationships');
        Schema::dropIfExists('commerce_agent_runs');
    }
};
