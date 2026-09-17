<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_lifecycle_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('step', 48);
            $table->string('channel', 16);
            $table->string('status', 16)->default('sent');
            $table->string('error', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'step', 'channel']);
            $table->index(['step', 'status']);
        });

        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'lifecycle_whatsapp_enabled')) {
                $table->boolean('lifecycle_whatsapp_enabled')->default(true);
            }
            if (! Schema::hasColumn('platform_settings', 'lifecycle_whatsapp_phone_number_id')) {
                $table->string('lifecycle_whatsapp_phone_number_id', 80)->nullable();
            }
            if (! Schema::hasColumn('platform_settings', 'lifecycle_whatsapp_access_token')) {
                $table->text('lifecycle_whatsapp_access_token')->nullable();
            }
            if (! Schema::hasColumn('platform_settings', 'lifecycle_whatsapp_template_lang')) {
                $table->string('lifecycle_whatsapp_template_lang', 12)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_lifecycle_sends');

        Schema::table('platform_settings', function (Blueprint $table) {
            foreach ([
                'lifecycle_whatsapp_enabled',
                'lifecycle_whatsapp_phone_number_id',
                'lifecycle_whatsapp_access_token',
                'lifecycle_whatsapp_template_lang',
            ] as $col) {
                if (Schema::hasColumn('platform_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
