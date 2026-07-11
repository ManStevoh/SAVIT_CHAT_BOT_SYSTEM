<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_analytics_investigation_id')->nullable()->constrained('owner_analytics_investigations')->nullOnDelete();
            $table->string('goal', 1000);
            $table->string('status', 24)->default('open');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('steps')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
        });

        Schema::create('intelligence_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('recommendation_key', 64);
            $table->text('recommended_action');
            $table->string('outcome', 24)->default('pending');
            $table->text('notes')->nullable();
            $table->json('metrics')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('measured_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'source_type', 'source_id']);
            $table->index(['company_id', 'outcome']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type', 24)->default('user');
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['company_id', 'event_type']);
        });

        Schema::create('company_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 80);
            $table->string('subject_role', 40)->nullable();
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('requires_role', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'action_type', 'is_active']);
        });

        Schema::create('business_probability_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 32)->nullable();
            $table->string('score_type', 24);
            $table->decimal('probability', 5, 4);
            $table->json('factors')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index(['company_id', 'score_type', 'computed_at']);
            $table->index(['company_id', 'customer_phone', 'score_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_probability_scores');
        Schema::dropIfExists('company_policy_rules');
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('intelligence_outcomes');
        Schema::dropIfExists('investigation_cases');
    }
};
