<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_free')->default(false)->after('stripe_price_id');
            $table->boolean('has_trial')->default(false)->after('is_free');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('has_trial');
            $table->string('trial_elapsed_action', 100)->nullable()->after('trial_days');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['is_free', 'has_trial', 'trial_days', 'trial_elapsed_action']);
        });
    }
};
