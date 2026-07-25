<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $starterPlan = DB::table('plans')->where('slug', 'starter')->first();
        if ($starterPlan && ! empty($starterPlan->entitlements)) {
            $entitlements = json_decode($starterPlan->entitlements, true);
            if (is_array($entitlements)) {
                $entitlements['analytics'] = true;
                DB::table('plans')->where('slug', 'starter')->update([
                    'entitlements' => json_encode($entitlements),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op.
    }
};
