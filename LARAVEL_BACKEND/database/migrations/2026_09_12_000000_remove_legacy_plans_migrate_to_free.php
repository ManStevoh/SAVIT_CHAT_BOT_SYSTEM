<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove all legacy plans (anything except free/professional/enterprise)
     * and move everyone on them to the new always-free plan (slug: free).
     */
    public function up(): void
    {
        $canonical = ['free', 'professional', 'enterprise'];

        // Ensure the always-free plan exists so FK-less references stay valid.
        $freeExists = DB::table('plans')->where('slug', 'free')->exists();
        if (! $freeExists) {
            DB::table('plans')->insert([
                'name' => 'Starter',
                'slug' => 'free',
                'price_display' => 'KSh 0',
                'price_amount' => 0,
                'description' => 'Free forever for businesses — storefront, bookings, and dine-in to get you selling',
                'popular' => false,
                'is_public' => true,
                'cta' => 'Get started free',
                'sort_order' => 0,
                'is_free' => true,
                'has_trial' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $freeId = DB::table('plans')->where('slug', 'free')->value('id');

        $legacyIds = DB::table('plans')->whereNotIn('slug', $canonical)->pluck('id')->all();

        // Subscriptions: move legacy plan slugs to free, keep status/dates intact.
        if (Schema::hasTable('subscriptions')) {
            DB::table('subscriptions')
                ->whereNotIn('plan', $canonical)
                ->update(['plan' => 'free']);
        }

        // Companies: move legacy plan slugs (and null/empty) to free.
        if (Schema::hasTable('companies')) {
            DB::table('companies')
                ->whereNotIn('plan', $canonical)
                ->orWhereNull('plan')
                ->update(['plan' => 'free']);
            // orWhereNull above could match canonical rows too via OR precedence;
            // re-assert canonical rows just in case, then fix empties.
            DB::table('companies')->whereNull('plan')->update(['plan' => 'free']);
            DB::table('companies')->where('plan', '')->update(['plan' => 'free']);
        }

        // Users: re-point selected_plan_id from legacy plans to free.
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'selected_plan_id') && $freeId && $legacyIds !== []) {
            DB::table('users')
                ->whereIn('selected_plan_id', $legacyIds)
                ->update(['selected_plan_id' => $freeId]);
        }

        // Platform default registration plan: force to free.
        if (Schema::hasTable('platform_settings')) {
            if (Schema::hasColumn('platform_settings', 'default_registration_plan_slug')) {
                DB::table('platform_settings')->update([
                    'default_registration_plan_slug' => 'free',
                    'force_default_registration_plan' => true,
                ]);
            }
        }

        // Subscription offers pointing at legacy plans: detach (nullOnDelete semantics).
        if (Schema::hasTable('subscription_offers') && $legacyIds !== []) {
            if (Schema::hasColumn('subscription_offers', 'plan_id')) {
                DB::table('subscription_offers')
                    ->whereIn('plan_id', $legacyIds)
                    ->update(['plan_id' => null]);
            }
        }

        // Finally delete legacy plan rows.
        DB::table('plans')->whereNotIn('slug', $canonical)->delete();
    }

    public function down(): void
    {
        // Cannot restore deleted legacy plan rows.
    }
};
