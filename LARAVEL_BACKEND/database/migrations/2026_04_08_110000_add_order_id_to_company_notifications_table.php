<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_notifications', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('chat_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
