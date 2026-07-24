<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('users', 'marketing_consent')) {
                $table->boolean('marketing_consent')->default(false)->after('terms_accepted_at');
            }
            if (! Schema::hasColumn('users', 'marketing_consent_at')) {
                $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            }
            if (! Schema::hasColumn('users', 'selected_plan_id')) {
                $table->unsignedBigInteger('selected_plan_id')->nullable()->after('marketing_consent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['terms_accepted_at', 'marketing_consent', 'marketing_consent_at', 'selected_plan_id'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
