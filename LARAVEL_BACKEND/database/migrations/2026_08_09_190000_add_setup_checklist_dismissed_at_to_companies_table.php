<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'setup_checklist_dismissed_at')) {
                $table->timestamp('setup_checklist_dismissed_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'setup_checklist_dismissed_at')) {
                $table->dropColumn('setup_checklist_dismissed_at');
            }
        });
    }
};
