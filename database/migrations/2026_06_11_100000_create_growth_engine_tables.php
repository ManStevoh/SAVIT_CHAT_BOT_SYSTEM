<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32); // facebook, instagram, linkedin, tiktok, twitter
            $table->string('account_name')->nullable();
            $table->string('external_account_id')->nullable();
            $table->string('page_id')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('status', 32)->default('disconnected'); // connected, disconnected, expired, error
            $table->json('metadata')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'platform', 'external_account_id'], 'social_accounts_company_platform_ext_unique');
            $table->index(['company_id', 'platform']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 32);
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('content_type', 32)->default('text'); // text, image, video, carousel
            $table->json('media_urls')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('status', 32)->default('draft'); // draft, scheduled, published, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'platform']);
            $table->index('scheduled_at');
        });

        Schema::create('attribution_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 16)->unique();
            $table->text('destination_url');
            $table->text('whatsapp_prefill')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::create('social_post_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->decimal('engagement_rate', 8, 4)->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['social_post_id', 'recorded_at']);
        });

        Schema::create('attribution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attribution_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 32); // click, whatsapp_start, lead, order, revenue
            $table->string('platform', 32)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'event_type']);
            $table->index(['social_post_id', 'event_type']);
            $table->index('created_at');
        });

        Schema::create('competitor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('account_name');
            $table->string('account_url')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('competitor_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedInteger('follower_count')->nullable();
            $table->decimal('avg_engagement', 8, 4)->nullable();
            $table->json('top_hashtags')->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();

            $table->index(['competitor_profile_id', 'recorded_at']);
        });

        Schema::create('growth_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type', 32); // topic, timing, format, hashtag, competitor, strategy
            $table->string('title');
            $table->text('body');
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_read']);
        });

        Schema::create('growth_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('agent_type', 32); // research, content, posting, analytics, crm, strategy
            $table->string('status', 32)->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'agent_type', 'status']);
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('social_post_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('attribution_link_id')->nullable()->after('social_post_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('social_post_id')->nullable()->after('chat_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_post_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attribution_link_id');
            $table->dropConstrainedForeignId('social_post_id');
        });

        Schema::dropIfExists('growth_agent_runs');
        Schema::dropIfExists('growth_insights');
        Schema::dropIfExists('competitor_snapshots');
        Schema::dropIfExists('competitor_profiles');
        Schema::dropIfExists('attribution_events');
        Schema::dropIfExists('social_post_metrics');
        Schema::dropIfExists('attribution_links');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};
