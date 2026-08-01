<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('orders_accept_mpesa')->default(false)->after('notifications_enabled');
            $table->boolean('orders_accept_stripe')->default(false)->after('orders_accept_mpesa');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('chat_id')->nullable()->after('company_id')->constrained('chats')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['orders_accept_mpesa', 'orders_accept_stripe']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['chat_id']);
        });
    }
};
