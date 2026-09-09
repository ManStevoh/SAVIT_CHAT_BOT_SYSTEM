<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'billing_model')) {
                $table->string('billing_model')->nullable()->after('plan');
            }
            if (! Schema::hasColumn('companies', 'commission_rate')) {
                $table->decimal('commission_rate', 5, 2)->nullable()->after('billing_model');
            }
            if (! Schema::hasColumn('companies', 'commission_basis')) {
                $table->string('commission_basis')->default('total')->after('commission_rate');
            }
            if (! Schema::hasColumn('companies', 'waive_subscription_fee')) {
                $table->boolean('waive_subscription_fee')->default(true)->after('commission_basis');
            }
            if (! Schema::hasColumn('companies', 'commission_invoice_threshold')) {
                $table->decimal('commission_invoice_threshold', 10, 2)->nullable()->after('waive_subscription_fee');
            }
            if (! Schema::hasColumn('companies', 'commission_balance_due')) {
                $table->decimal('commission_balance_due', 10, 2)->default(0.00)->after('commission_invoice_threshold');
            }
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'default_billing_model')) {
                $table->string('default_billing_model')->default('subscription')->after('default_registration_plan_slug');
            }
            if (! Schema::hasColumn('platform_settings', 'default_commission_rate')) {
                $table->decimal('default_commission_rate', 5, 2)->default(5.00)->after('default_billing_model');
            }
            if (! Schema::hasColumn('platform_settings', 'allow_public_commission_signup')) {
                $table->boolean('allow_public_commission_signup')->default(false)->after('default_commission_rate');
            }
            if (! Schema::hasColumn('platform_settings', 'default_commission_threshold')) {
                $table->decimal('default_commission_threshold', 10, 2)->default(1000.00)->after('allow_public_commission_signup');
            }
            if (! Schema::hasColumn('platform_settings', 'commission_grace_period_days')) {
                $table->integer('commission_grace_period_days')->default(7)->after('default_commission_threshold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $cols = array_intersect(
                ['billing_model', 'commission_rate', 'commission_basis', 'waive_subscription_fee', 'commission_invoice_threshold', 'commission_balance_due'],
                Schema::getColumnListing('companies')
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            $cols = array_intersect(
                ['default_billing_model', 'default_commission_rate', 'allow_public_commission_signup', 'default_commission_threshold', 'commission_grace_period_days'],
                Schema::getColumnListing('platform_settings')
            );
            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
