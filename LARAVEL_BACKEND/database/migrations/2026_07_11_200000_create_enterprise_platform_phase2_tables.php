<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (! Schema::hasColumn('plans', 'entitlements')) {
                $table->json('entitlements')->nullable()->after('features');
            }
        });

        Schema::create('company_entitlement_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->json('overrides');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('usage_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('meter_key', 40);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('consumed')->default(0);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'meter_key', 'period_start']);
            $table->index(['company_id', 'meter_key', 'period_end']);
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 24);
            $table->string('external_event_id', 120);
            $table->string('external_payment_id', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('status', 24)->default('paid');
            $table->string('payment_type', 40)->default('subscription');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_event_id']);
            $table->index(['company_id', 'paid_at']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('channel', 24)->default('in_app');
            $table->string('title', 200);
            $table->text('body_template')->nullable();
            $table->string('type', 24)->default('info');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_key', 80);
            $table->string('channel', 24);
            $table->string('status', 24)->default('sent');
            $table->string('recipient', 200)->nullable();
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('company_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('key_prefix', 16);
            $table->string('key_hash', 64);
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'revoked_at']);
            $table->index('key_prefix');
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('secret', 64);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('status', 24)->default('pending');
            $table->json('payload')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('company_api_keys');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('usage_meters');
        Schema::dropIfExists('company_entitlement_overrides');
        if (Schema::hasColumn('plans', 'entitlements')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('entitlements');
            });
        }
    }
};
