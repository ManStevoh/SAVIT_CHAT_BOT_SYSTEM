<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('status', 32)->default('pending');
            $table->json('config')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_integrations');
    }
};
