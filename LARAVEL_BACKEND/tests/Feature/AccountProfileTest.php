<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = $this->makeAdmin();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = $this->makeAdmin();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/profile', [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@test.com',
            'phone' => '+254711111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.name', 'Updated Admin')
            ->assertJsonPath('user.email', 'updated-admin@test.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Admin',
            'email' => 'updated-admin@test.com',
            'phone' => '+254711111111',
        ]);
    }

    public function test_authenticated_user_can_update_password(): void
    {
        $user = $this->makeAdmin();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/password', [
            'currentPassword' => 'password123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-456', $user->password));
    }

    public function test_password_update_rejects_wrong_current_password(): void
    {
        $user = $this->makeAdmin();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/auth/password', [
            'currentPassword' => 'wrong-password',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['currentPassword']);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $response = $this->putJson('/api/auth/profile', [
            'name' => 'Hacker',
            'email' => 'hacker@test.com',
        ]);

        $response->assertUnauthorized();
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->role = 'admin';
        $user->save();

        return $user;
    }
}
