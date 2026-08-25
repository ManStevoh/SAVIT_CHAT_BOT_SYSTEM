<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->decimal('predicted_revenue_score', 5, 2)->nullable()->after('performance_score');
            $table->json('content_tags')->nullable()->after('content_type');
            $table->json('prediction_factors')->nullable()->after('predicted_revenue_score');
        });

        Schema::create('growth_brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('winning_patterns')->nullable();
            $table->json('content_mix_weights')->nullable();
            $table->json('avg_metrics')->nullable();
            $table->timestamp('last_learned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('growth_learning_patterns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('pattern_type', 48);
            $table->string('source', 32)->default('company');
            $table->string('title');
            $table->text('body');
            $table->json('metrics')->nullable();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->boolean('is_applied')->default(false);
            $table->unsignedInteger('applied_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'pattern_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_learning_patterns');
        Schema::dropIfExists('growth_brand_profiles');

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['predicted_revenue_score', 'content_tags', 'prediction_factors']);
        });
    }
};
