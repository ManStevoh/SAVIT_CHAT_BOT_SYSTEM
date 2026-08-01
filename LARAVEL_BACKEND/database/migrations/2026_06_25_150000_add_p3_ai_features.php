<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->text('api_key')->nullable();
            $table->string('api_base_url')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'ai_provider_id']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('ai_credential_mode', 32)->default('platform')->after('ai_reply_mode');
            $table->string('default_reply_language', 10)->nullable()->after('ai_credential_mode');
            $table->boolean('reply_in_customer_language')->default(true)->after('default_reply_language');
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->string('credential_source', 16)->nullable()->after('use_case');
            $table->string('selection_source', 32)->nullable()->after('credential_source');
            $table->decimal('billed_cost_usd', 14, 6)->nullable()->after('estimated_cost_usd');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->string('detected_language', 10)->nullable()->after('customer_phone');
        });

        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('source');
            $table->string('language', 10)->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('language')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_notes')->nullable()->after('reviewed_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation_learning_samples', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['status', 'language', 'reviewed_by', 'reviewed_at', 'review_notes']);
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn('detected_language');
        });

        Schema::table('ai_request_logs', function (Blueprint $table) {
            $table->dropColumn(['credential_source', 'selection_source', 'billed_cost_usd']);
        });

        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_credential_mode', 'default_reply_language', 'reply_in_customer_language']);
        });

        Schema::dropIfExists('company_ai_providers');
    }
};
