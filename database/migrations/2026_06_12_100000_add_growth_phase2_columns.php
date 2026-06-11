<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('growth_pilot_at')->nullable()->after('status');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('ad_account_id')->nullable()->after('page_id');
        });

        Schema::table('growth_ad_spend_entries', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('source');
            $table->unique(['company_id', 'external_id'], 'growth_ad_spend_company_external_unique');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->timestamp('crm_last_follow_up_at')->nullable()->after('agent_handling_at');
            $table->unsignedTinyInteger('crm_follow_up_count')->default(0)->after('crm_last_follow_up_at');
        });

        Schema::create('portfolio_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recommendation_type', 32);
            $table->string('title');
            $table->text('body');
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_recommendations');

        Schema::table('chats', function (Blueprint $table) {
            $table->dropColumn(['crm_last_follow_up_at', 'crm_follow_up_count']);
        });

        Schema::table('growth_ad_spend_entries', function (Blueprint $table) {
            $table->dropUnique('growth_ad_spend_company_external_unique');
            $table->dropColumn('external_id');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('ad_account_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('growth_pilot_at');
        });
    }
};
