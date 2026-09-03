<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->string('display_currency', 3)->default('KES')->change();
            });

            // Update existing company settings to KES / KSh default
            DB::table('company_settings')
                ->where('display_currency', 'USD')
                ->orWhereNull('display_currency')
                ->update([
                    'display_currency' => 'KES',
                    'currency_symbol'  => 'KSh',
                ]);

            // Ensure all companies have a company_settings row initialized with KES
            $companyIds = DB::table('companies')->pluck('id');
            foreach ($companyIds as $cid) {
                $exists = DB::table('company_settings')->where('company_id', $cid)->exists();
                if (! $exists) {
                    DB::table('company_settings')->insert([
                        'company_id'       => $cid,
                        'display_currency' => 'KES',
                        'currency_symbol'  => 'KSh',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('company_settings')) {
            Schema::table('company_settings', function (Blueprint $table) {
                $table->string('display_currency', 3)->default('USD')->change();
            });
        }
    }
};
