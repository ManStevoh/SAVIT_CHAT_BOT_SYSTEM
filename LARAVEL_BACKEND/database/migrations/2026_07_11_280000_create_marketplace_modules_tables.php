<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 32)->default('industry');
            $table->string('publisher', 32)->default('platform');
            $table->string('required_plan', 32)->nullable();
            $table->text('prompt_addon')->nullable();
            $table->json('tools')->nullable();
            $table->json('manifest')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('company_module_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 64);
            $table->string('status', 24)->default('installed');
            $table->json('config')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'module_key']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_module_installations');
        Schema::dropIfExists('marketplace_modules');
    }
};
