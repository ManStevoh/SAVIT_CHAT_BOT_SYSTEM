<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }
            if (! Schema::hasColumn('products', 'meta_title')) {
                $table->string('meta_title')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'meta_description')) {
                $table->string('meta_description', 500)->nullable()->after('meta_title');
            }
            if (! Schema::hasColumn('products', 'compare_at_price')) {
                $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('products', 'is_subscription')) {
                $table->boolean('is_subscription')->default(false);
            }
            if (! Schema::hasColumn('products', 'subscription_interval')) {
                $table->string('subscription_interval', 20)->nullable();
            }
        });

        // Unique slug per company (partial uniqueness handled in app if nulls)
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['company_id', 'slug'], 'products_company_slug_unique');
            });
        } catch (\Throwable) {
            // index may already exist
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_phone');
            }
            if (! Schema::hasColumn('orders', 'order_notes')) {
                $table->text('order_notes')->nullable();
            }
            if (! Schema::hasColumn('orders', 'gift_message')) {
                $table->text('gift_message')->nullable();
            }
            if (! Schema::hasColumn('orders', 'tip_amount')) {
                $table->decimal('tip_amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'discount_total')) {
                $table->decimal('discount_total', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable();
            }
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')->nullable();
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'custom_domain')) {
                $table->string('custom_domain')->nullable()->unique();
            }
            if (! Schema::hasColumn('companies', 'custom_domain_verified_at')) {
                $table->timestamp('custom_domain_verified_at')->nullable();
            }
            if (! Schema::hasColumn('companies', 'storefront_theme')) {
                $table->json('storefront_theme')->nullable();
            }
            if (! Schema::hasColumn('companies', 'storefront_sections')) {
                $table->json('storefront_sections')->nullable();
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'abandoned_cart_recovery_enabled')) {
                $table->boolean('abandoned_cart_recovery_enabled')->default(false);
            }
            if (! Schema::hasColumn('company_settings', 'storefront_alt_currencies')) {
                $table->json('storefront_alt_currencies')->nullable();
            }
            if (! Schema::hasColumn('company_settings', 'storefront_default_locale')) {
                $table->string('storefront_default_locale', 10)->default('en');
            }
        });

        Schema::table('storefront_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('storefront_sessions', 'customer_email')) {
                $table->string('customer_email')->nullable();
            }
            if (! Schema::hasColumn('storefront_sessions', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable();
            }
            if (! Schema::hasColumn('storefront_sessions', 'abandoned_notified_at')) {
                $table->timestamp('abandoned_notified_at')->nullable();
            }
            if (! Schema::hasColumn('storefront_sessions', 'wishlist')) {
                $table->json('wishlist')->nullable();
            }
            if (! Schema::hasColumn('storefront_sessions', 'locale')) {
                $table->string('locale', 10)->nullable();
            }
            if (! Schema::hasColumn('storefront_sessions', 'coupon_code')) {
                $table->string('coupon_code', 64)->nullable();
            }
        });

        if (! Schema::hasTable('storefront_coupons')) {
            Schema::create('storefront_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 64);
                $table->string('type', 20); // percent|fixed
                $table->decimal('value', 12, 2);
                $table->decimal('min_order', 12, 2)->nullable();
                $table->unsignedInteger('max_redemptions')->nullable();
                $table->unsignedInteger('redeemed_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['company_id', 'code']);
            });
        }

        if (! Schema::hasTable('storefront_events')) {
            Schema::create('storefront_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('session_token', 64)->nullable()->index();
                $table->string('event', 40);
                $table->foreignId('product_id')->nullable();
                $table->foreignId('order_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['company_id', 'event', 'created_at']);
            });
        }

        if (! Schema::hasTable('storefront_customers')) {
            Schema::create('storefront_customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('name')->nullable();
                $table->string('locale', 10)->nullable();
                $table->timestamp('last_order_at')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'phone']);
            });
        }

        if (! Schema::hasTable('storefront_addresses')) {
            Schema::create('storefront_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('storefront_customer_id')->constrained()->cascadeOnDelete();
                $table->string('label')->nullable();
                $table->text('line');
                $table->string('city')->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable();
                $table->string('author_name');
                $table->unsignedTinyInteger('rating');
                $table->text('body')->nullable();
                $table->boolean('is_approved')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('product_bundle_items')) {
            Schema::create('product_bundle_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('child_product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();
                $table->unique(['bundle_product_id', 'child_product_id'], 'bundle_child_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('storefront_addresses');
        Schema::dropIfExists('storefront_customers');
        Schema::dropIfExists('storefront_events');
        Schema::dropIfExists('storefront_coupons');

        Schema::table('storefront_sessions', function (Blueprint $table) {
            foreach (['customer_email', 'last_activity_at', 'abandoned_notified_at', 'wishlist', 'locale', 'coupon_code'] as $col) {
                if (Schema::hasColumn('storefront_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            foreach (['abandoned_cart_recovery_enabled', 'storefront_alt_currencies', 'storefront_default_locale'] as $col) {
                if (Schema::hasColumn('company_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            foreach (['custom_domain', 'custom_domain_verified_at', 'storefront_theme', 'storefront_sections'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['customer_email', 'order_notes', 'gift_message', 'tip_amount', 'discount_total', 'coupon_code', 'coupon_id'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            try {
                $table->dropUnique('products_company_slug_unique');
            } catch (\Throwable) {
            }
            foreach (['slug', 'meta_title', 'meta_description', 'compare_at_price', 'is_subscription', 'subscription_interval'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
