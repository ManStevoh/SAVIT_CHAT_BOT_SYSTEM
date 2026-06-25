<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 32); // faq, product
            $table->unsignedBigInteger('source_id');
            $table->unsignedSmallInteger('chunk_index')->default(0);
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'source_type', 'source_id']);
        });

        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->unsignedSmallInteger('positive_feedback_count')->default(0)->after('use_count');
            $table->unsignedSmallInteger('negative_feedback_count')->default(0)->after('positive_feedback_count');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('catalog_embedding')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('catalog_embedding');
        });

        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->dropColumn(['positive_feedback_count', 'negative_feedback_count']);
        });

        Schema::dropIfExists('knowledge_chunks');
    }
};
