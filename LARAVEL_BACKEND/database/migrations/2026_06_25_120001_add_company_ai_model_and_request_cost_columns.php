<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('ai_model_mode', 20)->default('auto')->after('ai_tone');
            $table->foreignId('ai_model_id')->nullable()->after('ai_model_mode')->constrained('ai_models')->nullOnDelete();
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->foreignId('ai_provider_id')->nullable()->after('company_id')->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->after('ai_provider_id')->constrained('ai_models')->nullOnDelete();
            $table->decimal('estimated_cost_usd', 14, 6)->default(0)->after('total_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_model_id');
            $table->dropConstrainedForeignId('ai_provider_id');
            $table->dropColumn('estimated_cost_usd');
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_model_id');
            $table->dropColumn('ai_model_mode');
        });
    }
};
