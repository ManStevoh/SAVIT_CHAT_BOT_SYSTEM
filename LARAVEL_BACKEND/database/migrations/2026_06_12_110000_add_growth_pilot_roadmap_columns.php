<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'industry')) {
                $table->string('industry', 64)->nullable()->after('plan');
            }
            if (! Schema::hasColumn('companies', 'first_attributed_sale_at')) {
                $table->timestamp('first_attributed_sale_at')->nullable()->after('growth_pilot_at');
            }
            if (! Schema::hasColumn('companies', 'growth_demo_mode')) {
                $table->boolean('growth_demo_mode')->default(false)->after('first_attributed_sale_at');
            }
            if (! Schema::hasColumn('companies', 'attribution_retention_days')) {
                $table->unsignedSmallInteger('attribution_retention_days')->nullable()->after('growth_demo_mode');
            }
        });

        Schema::table('social_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('social_posts', 'publish_error')) {
                $table->text('publish_error')->nullable()->after('external_post_id');
            }
        });

        if (Schema::hasTable('portfolio_recommendations')) {
            Schema::table('portfolio_recommendations', function (Blueprint $table) {
                if (! Schema::hasColumn('portfolio_recommendations', 'industry_cluster')) {
                    $table->string('industry_cluster', 64)->nullable()->after('recommendation_type');
                }
                if (! Schema::hasColumn('portfolio_recommendations', 'approved_for_tenants')) {
                    $table->boolean('approved_for_tenants')->default(false)->after('confidence_score');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('portfolio_recommendations', function (Blueprint $table) {
            $table->dropColumn(['industry_cluster', 'approved_for_tenants']);
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn('publish_error');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['industry', 'first_attributed_sale_at', 'growth_demo_mode', 'attribution_retention_days']);
        });
    }
};
