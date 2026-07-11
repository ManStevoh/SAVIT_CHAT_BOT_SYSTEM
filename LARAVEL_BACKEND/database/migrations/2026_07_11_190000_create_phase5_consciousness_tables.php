<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_action_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_action_requests', 'execution_result')) {
                $table->json('execution_result')->nullable()->after('reasoning');
            }
            if (! Schema::hasColumn('agent_action_requests', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
        });

        Schema::create('commerce_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('experiment_type', 40)->default('promotion_ab');
            $table->string('status', 24)->default('running');
            $table->string('metric_key', 40)->default('conversion_rate');
            $table->unsignedBigInteger('winner_variant_id')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('commerce_experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('commerce_experiments')->cascadeOnDelete();
            $table->string('variant_key', 8);
            $table->string('label');
            $table->json('payload')->nullable();
            $table->unsignedInteger('assignments_count')->default(0);
            $table->unsignedInteger('conversions_count')->default(0);
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['experiment_id', 'variant_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commerce_experiment_variants');
        Schema::dropIfExists('commerce_experiments');
        Schema::table('agent_action_requests', function (Blueprint $table) {
            if (Schema::hasColumn('agent_action_requests', 'execution_result')) {
                $table->dropColumn('execution_result');
            }
            if (Schema::hasColumn('agent_action_requests', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
