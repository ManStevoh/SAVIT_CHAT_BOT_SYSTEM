<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The always-free plan (slug: free) has no trial. Accounts migrated
     * from legacy plans kept status='trial', which shows trial banners
     * ("You're on a free trial...") on a plan that is free forever.
     * Flip those rows to active with a far-future end date.
     */
    public function up(): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        DB::table('subscriptions')
            ->where('plan', 'free')
            ->where('status', 'trial')
            ->update([
                'status' => 'active',
                'end_date' => now()->addYears(10)->toDateString(),
            ]);
    }

    public function down(): void
    {
        // No-op: do not put free accounts back into trial.
    }
};
