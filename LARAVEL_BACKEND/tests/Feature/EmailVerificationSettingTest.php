<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_auto_verifies_when_setting_off(): void
    {
        PlatformSetting::create(['require_email_verification' => false]);

        $response = $this->postJson('/api/auth/register', [
            'companyName' => 'Test Co',
            'name' => 'Test User',
            'email' => 'owner@test.com',
            'phone' => '+254700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptTerms' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('requireEmailVerification', false);

        $user = User::where('email', 'owner@test.com')->first();
        $this->assertNotNull($user->email_verified_at);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'owner@test.com',
            'password' => 'password123',
        ]);

        $login->assertOk()->assertJsonPath('success', true);
    }

    public function test_registration_requires_verification_when_setting_on(): void
    {
        PlatformSetting::create(['require_email_verification' => true]);

        $response = $this->postJson('/api/auth/register', [
            'companyName' => 'Verify Co',
            'name' => 'Verify User',
            'email' => 'verify@test.com',
            'phone' => '+254700000001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptTerms' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('requireEmailVerification', true);

        $user = User::where('email', 'verify@test.com')->first();
        $this->assertNull($user->email_verified_at);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'verify@test.com',
            'password' => 'password123',
        ]);

        $login->assertStatus(403)
            ->assertJsonPath('code', 'email_not_verified');
    }
}
