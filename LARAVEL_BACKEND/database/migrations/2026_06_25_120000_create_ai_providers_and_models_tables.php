<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name');
            $table->text('api_key')->nullable();
            $table->string('api_base_url')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model_key', 120);
            $table->string('display_name');
            $table->string('capability', 20)->default('chat');
            $table->decimal('input_cost_per_million', 12, 4)->default(0);
            $table->decimal('output_cost_per_million', 12, 4)->default(0);
            $table->unsignedInteger('max_output_tokens')->default(512);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_platform_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'model_key', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
