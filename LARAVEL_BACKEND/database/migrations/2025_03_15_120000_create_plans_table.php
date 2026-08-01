<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('price_display'); // e.g. "$29", "Custom"
            $table->decimal('price_amount', 12, 2)->nullable(); // null = custom pricing
            $table->string('description')->nullable();
            $table->json('features')->nullable(); // array of feature strings
            $table->boolean('popular')->default(false);
            $table->string('cta')->default('Start Free Trial');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
