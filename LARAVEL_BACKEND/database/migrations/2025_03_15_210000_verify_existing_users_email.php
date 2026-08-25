<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Mark all existing users as email-verified so they are not locked out
 * after enabling MustVerifyEmail. New sign-ups will receive verification emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Cannot safely revert without tracking who was previously null.
    }
};
