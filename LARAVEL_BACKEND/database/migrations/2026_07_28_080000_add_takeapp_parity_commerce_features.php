<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'store_slug')) {
                $table->string('store_slug')->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('companies', 'storefront_enabled')) {
                $table->boolean('storefront_enabled')->default(false)->after('store_slug');
            }
            if (! Schema::hasColumn('companies', 'link_in_bio_enabled')) {
                $table->boolean('link_in_bio_enabled')->default(false)->after('storefront_enabled');
            }
            if (! Schema::hasColumn('companies', 'link_in_bio_headline')) {
                $table->string('link_in_bio_headline')->nullable()->after('link_in_bio_enabled');
            }
            if (! Schema::hasColumn('companies', 'link_in_bio_bio')) {
                $table->text('link_in_bio_bio')->nullable()->after('link_in_bio_headline');
            }
            if (! Schema::hasColumn('companies', 'link_in_bio_links')) {
                $table->json('link_in_bio_links')->nullable()->after('link_in_bio_bio');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'orders_accept_cod')) {
                $table->boolean('orders_accept_cod')->default(false)->after('orders_accept_paystack');
            }
            if (! Schema::hasColumn('company_settings', 'orders_accept_bank_transfer')) {
                $table->boolean('orders_accept_bank_transfer')->default(false)->after('orders_accept_cod');
            }
            if (! Schema::hasColumn('company_settings', 'bank_transfer_instructions')) {
                $table->text('bank_transfer_instructions')->nullable()->after('order_payment_manual_instructions');
            }
            if (! Schema::hasColumn('company_settings', 'delivery_fees_enabled')) {
                $table->boolean('delivery_fees_enabled')->default(false);
            }
            if (! Schema::hasColumn('company_settings', 'default_delivery_fee')) {
                $table->decimal('default_delivery_fee', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('company_settings', 'free_delivery_above')) {
                $table->decimal('free_delivery_above', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'payment_recovery_enabled')) {
                $table->boolean('payment_recovery_enabled')->default(true);
            }
            if (! Schema::hasColumn('company_settings', 'payment_recovery_hours')) {
                $table->json('payment_recovery_hours')->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'birthday_automation_enabled')) {
                $table->boolean('birthday_automation_enabled')->default(false);
            }
            if (! Schema::hasColumn('company_settings', 'birthday_coupon_percent')) {
                $table->unsignedTinyInteger('birthday_coupon_percent')->default(10);
            }
            if (! Schema::hasColumn('company_settings', 'birthday_message_template')) {
                $table->text('birthday_message_template')->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'winback_automation_enabled')) {
                $table->boolean('winback_automation_enabled')->default(false);
            }
            if (! Schema::hasColumn('company_settings', 'winback_days_inactive')) {
                $table->unsignedSmallInteger('winback_days_inactive')->default(30);
            }
            if (! Schema::hasColumn('company_settings', 'spam_order_protection_enabled')) {
                $table->boolean('spam_order_protection_enabled')->default(true);
            }
            if (! Schema::hasColumn('company_settings', 'spam_max_orders_per_hour')) {
                $table->unsignedSmallInteger('spam_max_orders_per_hour')->default(5);
            }
            if (! Schema::hasColumn('company_settings', 'spam_max_orders_per_day')) {
                $table->unsignedSmallInteger('spam_max_orders_per_day')->default(20);
            }
            if (! Schema::hasColumn('company_settings', 'dine_in_enabled')) {
                $table->boolean('dine_in_enabled')->default(false);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 2)->default(0)->after('tax_total');
            }
            if (! Schema::hasColumn('orders', 'fulfillment_type')) {
                $table->string('fulfillment_type', 30)->default('delivery')->after('delivery_address');
            }
            if (! Schema::hasColumn('orders', 'dine_in_table_id')) {
                $table->foreignId('dine_in_table_id')->nullable()->after('fulfillment_type');
            }
            if (! Schema::hasColumn('orders', 'dine_in_table_name')) {
                $table->string('dine_in_table_name')->nullable()->after('dine_in_table_id');
            }
            if (! Schema::hasColumn('orders', 'invoice_token')) {
                $table->string('invoice_token', 64)->nullable()->unique()->after('order_number');
            }
            if (! Schema::hasColumn('orders', 'pay_token')) {
                $table->string('pay_token', 64)->nullable()->unique()->after('invoice_token');
            }
            if (! Schema::hasColumn('orders', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable();
            }
            if (! Schema::hasColumn('orders', 'spam_flagged')) {
                $table->boolean('spam_flagged')->default(false);
            }
            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source', 40)->default('whatsapp');
            }
            if (! Schema::hasColumn('orders', 'payment_recovered_at')) {
                $table->timestamp('payment_recovered_at')->nullable();
            }
        });

        Schema::table('chats', function (Blueprint $table) {
            if (! Schema::hasColumn('chats', 'birthday')) {
                $table->date('birthday')->nullable();
            }
            if (! Schema::hasColumn('chats', 'marketing_opt_in')) {
                $table->boolean('marketing_opt_in')->default(true);
            }
            if (! Schema::hasColumn('chats', 'last_birthday_wish_at')) {
                $table->timestamp('last_birthday_wish_at')->nullable();
            }
            if (! Schema::hasColumn('chats', 'last_winback_at')) {
                $table->timestamp('last_winback_at')->nullable();
            }
            if (! Schema::hasColumn('chats', 'blocked_from_ordering')) {
                $table->boolean('blocked_from_ordering')->default(false);
            }
        });

        if (! Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->decimal('fee', 12, 2)->default(0);
                $table->unsignedInteger('min_order_amount')->nullable();
                $table->json('keywords')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dine_in_tables')) {
            Schema::create('dine_in_tables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('qr_token', 64)->unique();
                $table->unsignedTinyInteger('seats')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'name']);
            });
        }

        if (! Schema::hasTable('payment_recovery_attempts')) {
            Schema::create('payment_recovery_attempts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('attempt_number');
                $table->unsignedSmallInteger('hours_after_order');
                $table->string('channel', 30)->default('whatsapp');
                $table->string('status', 30)->default('sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['order_id', 'attempt_number']);
            });
        }

        if (! Schema::hasTable('storefront_sessions')) {
            Schema::create('storefront_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('session_token', 64)->unique();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone')->nullable();
                $table->json('cart')->nullable();
                $table->string('fulfillment_type', 30)->default('delivery');
                $table->foreignId('dine_in_table_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_sessions');
        Schema::dropIfExists('payment_recovery_attempts');
        Schema::dropIfExists('dine_in_tables');
        Schema::dropIfExists('delivery_zones');

        Schema::table('chats', function (Blueprint $table) {
            foreach (['birthday', 'marketing_opt_in', 'last_birthday_wish_at', 'last_winback_at', 'blocked_from_ordering'] as $col) {
                if (Schema::hasColumn('chats', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'payment_method', 'delivery_fee', 'fulfillment_type', 'dine_in_table_id', 'dine_in_table_name',
                'invoice_token', 'pay_token', 'scheduled_for', 'spam_flagged', 'source', 'payment_recovered_at',
            ] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            foreach ([
                'orders_accept_cod', 'orders_accept_bank_transfer', 'bank_transfer_instructions',
                'delivery_fees_enabled', 'default_delivery_fee', 'free_delivery_above',
                'payment_recovery_enabled', 'payment_recovery_hours',
                'birthday_automation_enabled', 'birthday_coupon_percent', 'birthday_message_template',
                'winback_automation_enabled', 'winback_days_inactive',
                'spam_order_protection_enabled', 'spam_max_orders_per_hour', 'spam_max_orders_per_day',
                'dine_in_enabled',
            ] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'store_slug', 'storefront_enabled', 'link_in_bio_enabled',
                'link_in_bio_headline', 'link_in_bio_bio', 'link_in_bio_links',
            ] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
