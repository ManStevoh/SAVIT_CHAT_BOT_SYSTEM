<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('growth_ad_spend_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32)->nullable();
            $table->string('campaign_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->date('spent_at');
            $table->string('source', 32)->default('manual'); // manual, csv_import, meta_api
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'spent_at']);
            $table->index(['company_id', 'platform']);
        });

        Schema::create('growth_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('state_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_oauth_states');
        Schema::dropIfExists('growth_ad_spend_entries');
    }
};
