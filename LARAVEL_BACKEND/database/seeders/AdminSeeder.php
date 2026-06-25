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
        $user = User::firstOrCreate(
            ['email' => 'admin@essem.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'company_id' => null,
                'phone' => null,
                'status' => 'active',
            ]
        );
        if ($user->role !== 'admin') {
            $user->role = 'admin';
            $user->save();
        }
    }
}
