<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_world_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->json('world_model');
            $table->string('trigger', 60)->default('scheduled');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('business_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('opportunity_type', 80);
            $table->string('title', 200);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->json('estimated_impact')->nullable();
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('medium');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamps();
            $table->index(['company_id', 'status', 'priority']);
        });

        Schema::create('agent_trust_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type', 80);
            $table->string('goal', 120)->nullable();
            $table->text('reasoning_summary')->nullable();
            $table->json('tools_used')->nullable();
            $table->json('data_consulted')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('outcome', 40)->nullable();
            $table->json('explainability')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('agent_action_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action_type', 80);
            $table->string('risk_level', 20);
            $table->json('payload')->nullable();
            $table->text('reasoning')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('organizational_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('title', 200);
            $table->text('content');
            $table->string('source', 40)->default('agent');
            $table->timestamps();
            $table->index(['company_id', 'category']);
        });

        Schema::create('business_health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('score_date');
            $table->unsignedTinyInteger('overall_score');
            $table->json('factors');
            $table->json('trends')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'score_date']);
        });

        Schema::table('commerce_briefs', function (Blueprint $table) {
            if (! Schema::hasColumn('commerce_briefs', 'executive_decisions')) {
                $table->json('executive_decisions')->nullable()->after('recommendations');
            }
        });
    }

    public function down(): void
    {
        Schema::table('commerce_briefs', function (Blueprint $table) {
            if (Schema::hasColumn('commerce_briefs', 'executive_decisions')) {
                $table->dropColumn('executive_decisions');
            }
        });
        Schema::dropIfExists('business_health_scores');
        Schema::dropIfExists('organizational_memories');
        Schema::dropIfExists('agent_action_requests');
        Schema::dropIfExists('agent_trust_logs');
        Schema::dropIfExists('business_opportunities');
        Schema::dropIfExists('business_world_snapshots');
    }
};
