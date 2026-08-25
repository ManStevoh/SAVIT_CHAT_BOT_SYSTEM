<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('meta_billing_model', 32)->nullable()->after('whatsapp_business_account_id');
            $table->string('credit_allocation_config_id', 100)->nullable()->after('meta_billing_model');
            $table->timestamp('credit_line_shared_at')->nullable()->after('credit_allocation_config_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'meta_billing_model',
                'credit_allocation_config_id',
                'credit_line_shared_at',
            ]);
        });
    }
};
