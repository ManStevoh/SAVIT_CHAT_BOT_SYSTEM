<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'business_dna')) {
                $table->json('business_dna')->nullable()->after('digital_twin');
            }
        });

        Schema::create('cognitive_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->json('perception')->nullable();
            $table->json('debate')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('confidence_action', 40)->nullable();
            $table->json('critique')->nullable();
            $table->json('governance')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['company_id', 'created_at']);
        });

        Schema::create('strategic_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('strategy_type', 60);
            $table->string('title', 200);
            $table->text('context_summary');
            $table->text('outcome_summary');
            $table->unsignedTinyInteger('success_score')->default(50);
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'strategy_type']);
        });

        Schema::create('tool_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('proposed_name', 120);
            $table->text('description');
            $table->json('tool_chain');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->string('status', 30)->default('proposed');
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('platform_intelligence_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_key', 120)->unique();
            $table->string('pattern_type', 60);
            $table->text('description');
            $table->unsignedInteger('evidence_count')->default(1);
            $table->json('industries')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
        });

        Schema::create('executive_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('goal_statement', 500);
            $table->json('breakdown');
            $table->json('kpi_targets')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        Schema::create('knowledge_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('artifact_type', 60);
            $table->string('title', 200);
            $table->text('content');
            $table->unsignedInteger('source_chat_count')->default(0);
            $table->json('evidence')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();
            $table->index(['company_id', 'artifact_type']);
        });

        Schema::create('cognitive_simulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('scenario_type', 80);
            $table->json('inputs');
            $table->json('scenarios');
            $table->string('recommendation', 500)->nullable();
            $table->timestamps();
            $table->index(['company_id', 'scenario_type']);
        });

        $patterns = config('agent.cognitive.platform_patterns_seed', []);
        foreach ($patterns as $pattern) {
            if (! empty($pattern['pattern_key'])) {
                \DB::table('platform_intelligence_patterns')->insertOrIgnore([
                    'pattern_key' => $pattern['pattern_key'],
                    'pattern_type' => $pattern['pattern_type'] ?? 'general',
                    'description' => $pattern['description'] ?? '',
                    'evidence_count' => 1,
                    'industries' => json_encode($pattern['industries'] ?? ['all']),
                    'metrics' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cognitive_simulations');
        Schema::dropIfExists('knowledge_artifacts');
        Schema::dropIfExists('executive_plans');
        Schema::dropIfExists('platform_intelligence_patterns');
        Schema::dropIfExists('tool_proposals');
        Schema::dropIfExists('strategic_memories');
        Schema::dropIfExists('cognitive_episodes');

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'business_dna')) {
                $table->dropColumn('business_dna');
            }
        });
    }
};
