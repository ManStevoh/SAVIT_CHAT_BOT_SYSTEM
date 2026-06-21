<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the application's database with a super admin user.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@savit.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'company_id' => null,
                'phone' => null,
                'status' => 'active',
            ]
        );
    }
}
