<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_marketing_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->text('body_html')->nullable();
            $table->text('body_text');
            $table->string('cta_label', 80)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_whatsapp')->default(true);
            $table->boolean('channel_popup')->default(true);
            $table->string('audience', 40)->default('marketing_consent');
            $table->string('status', 20)->default('draft');
            $table->string('send_mode', 20)->default('manual');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('recurring_interval', 20)->nullable();
            $table->string('trigger', 20)->nullable();
            $table->unsignedInteger('trigger_delay_seconds')->default(0);
            $table->boolean('popup_once')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_mode']);
            $table->index('scheduled_at');
        });

        Schema::create('platform_marketing_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('platform_marketing_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16)->default('sent');
            $table->string('period_key', 32)->default('');
            $table->string('error', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'channel', 'period_key'], 'pm_sends_unique');
            $table->index(['user_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_marketing_sends');
        Schema::dropIfExists('platform_marketing_messages');
    }
};
