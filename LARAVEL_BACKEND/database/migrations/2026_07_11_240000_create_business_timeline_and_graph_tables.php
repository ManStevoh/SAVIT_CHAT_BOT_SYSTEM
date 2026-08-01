<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('category', 40)->default('general');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedTinyInteger('importance')->default(50);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'occurred_at']);
            $table->index(['company_id', 'event_type']);
            $table->unique(['company_id', 'event_type', 'source_type', 'source_id'], 'timeline_dedupe');
        });

        Schema::create('business_graph_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('node_type', 40);
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('label');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'node_type', 'ref_type', 'ref_id'], 'graph_node_unique');
            $table->index(['company_id', 'node_type']);
        });

        Schema::create('business_graph_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('business_graph_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('business_graph_nodes')->cascadeOnDelete();
            $table->string('edge_type', 40);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['from_node_id', 'to_node_id', 'edge_type'], 'graph_edge_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_graph_edges');
        Schema::dropIfExists('business_graph_nodes');
        Schema::dropIfExists('business_timeline_events');
    }
};
