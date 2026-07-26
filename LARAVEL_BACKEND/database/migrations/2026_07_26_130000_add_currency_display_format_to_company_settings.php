<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('currency_symbol', 16)->nullable()->after('display_currency');
            $table->string('thousands_separator', 1)->default(',')->after('currency_symbol');
            $table->string('decimal_separator', 1)->default('.')->after('thousands_separator');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['currency_symbol', 'thousands_separator', 'decimal_separator']);
        });
    }
};
