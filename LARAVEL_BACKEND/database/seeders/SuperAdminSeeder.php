<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Seed the application's database with a super admin user.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@savit.local')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'company_id' => null,
                'phone' => null,
                'status' => 'active',
                'avatar' => null,
                'email_verified_at' => now(),
            ]
        );
        if ($user->role !== 'admin') {
            $user->role = 'admin';
            $user->save();
        }
    }
}
