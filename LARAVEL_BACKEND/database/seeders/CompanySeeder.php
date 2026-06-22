<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    /**
     * Sample companies and their login users.
     * Run: php artisan db:seed --class=CompanySeeder
     *
     * Login details (use with POST /api/login):
     * - Company 1: demo1@company.local / password
     * - Company 2: demo2@company.local / password
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Acme Demo Store',
                'email' => 'contact@acme-demo.local',
                'phone' => '+1 555 0100',
                'plan' => 'starter',
                'status' => 'active',
                'user' => [
                    'name' => 'Acme Owner',
                    'email' => 'demo1@company.local',
                    'password' => 'password',
                    'role' => 'company_owner',
                ],
            ],
            [
                'name' => 'Beta Commerce Ltd',
                'email' => 'hello@beta-commerce.local',
                'phone' => '+1 555 0200',
                'plan' => 'professional',
                'status' => 'active',
                'user' => [
                    'name' => 'Beta Admin',
                    'email' => 'demo2@company.local',
                    'password' => 'password',
                    'role' => 'company_owner',
                ],
            ],
        ];

        foreach ($companies as $data) {
            $userData = $data['user'];
            unset($data['user']);

            $company = Company::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'company_id' => $company->id,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            if ($user->role !== $userData['role']) {
                $user->role = $userData['role'];
                $user->save();
            }

            Subscription::firstOrCreate(
                ['company_id' => $company->id],
                [
                    'plan' => $data['plan'],
                    'status' => 'active',
                    'start_date' => now()->subMonth(),
                    'end_date' => now()->addYear(),
                    'amount' => 29.00,
                    'billing_cycle' => 'monthly',
                ]
            );
        }
    }
}
