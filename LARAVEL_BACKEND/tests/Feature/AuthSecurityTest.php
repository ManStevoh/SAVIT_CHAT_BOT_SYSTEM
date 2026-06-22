<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_blocked_when_setting_off(): void
    {
        PlatformSetting::create(['allow_new_registrations' => false]);

        $response = $this->postJson('/api/auth/register', [
            'companyName' => 'Blocked Co',
            'name' => 'Owner',
            'email' => 'blocked@test.com',
            'phone' => '+254700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptTerms' => true,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'New registrations are currently closed.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $company = Company::create([
            'name' => 'Active Co',
            'email' => 'active-co@test.local',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'inactive',
            'email_verified_at' => now(),
        ]);
        $user->role = 'company_owner';
        $user->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactive@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'account_inactive');
    }

    public function test_suspended_company_user_cannot_login(): void
    {
        $company = Company::create([
            'name' => 'Suspended Co',
            'email' => 'suspended-co@test.local',
            'status' => 'suspended',
        ]);

        $user = User::create([
            'name' => 'Suspended User',
            'email' => 'suspended@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->role = 'company_owner';
        $user->save();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('code', 'company_inactive');
    }

    public function test_company_cannot_mark_order_paid_manually(): void
    {
        $company = Company::create([
            'name' => 'Order Co',
            'email' => 'order-co@test.local',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Order Owner',
            'email' => 'order-owner@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->role = 'company_owner';
        $user->save();

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addYear()->format('Y-m-d'),
            'amount' => 29,
            'billing_cycle' => 'monthly',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-SEC01',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700000000',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/company/orders/{$order->id}", [
                'paymentStatus' => 'paid',
            ]);

        $response->assertStatus(403);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_deactivated_user_tokens_are_revoked_on_admin_status_change(): void
    {
        $user = User::create([
            'name' => 'Target User',
            'email' => 'target@test.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->role = 'company_owner';
        $user->save();
        $user->createToken('auth');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-sec@test.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->role = 'admin';
        $admin->save();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}", ['status' => 'inactive'])
            ->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }
}
