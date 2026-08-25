<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('platform_name')->nullable();
            $table->string('support_email')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->string('ai_model')->nullable();
            $table->unsignedInteger('max_tokens_per_request')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
