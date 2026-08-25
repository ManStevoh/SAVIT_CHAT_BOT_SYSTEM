<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number_id'); // Meta Phone Number ID (used in API and webhook)
            $table->string('whatsapp_business_account_id')->nullable();
            $table->text('access_token'); // stored encrypted in model
            $table->string('verify_token')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->string('display_phone_number')->nullable(); // e.g. +201234567890
            $table->timestamps();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('whatsapp_message_id')->nullable()->after('status'); // Meta message id for deduplication
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('whatsapp_message_id');
        });
        Schema::dropIfExists('whatsapp_accounts');
    }
};
