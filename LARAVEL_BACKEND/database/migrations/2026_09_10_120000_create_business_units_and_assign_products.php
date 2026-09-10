<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type', 64)->default('general');
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('hero_image')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('company_id')->constrained('business_units')->nullOnDelete();
            $table->index(['company_id', 'business_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });

        Schema::dropIfExists('business_units');
    }
};
