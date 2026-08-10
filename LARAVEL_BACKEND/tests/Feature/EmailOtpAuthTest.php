<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailOtpAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_login_otp_fails_if_user_does_not_exist(): void
    {
        $response = $this->postJson('/api/auth/send-login-otp', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_send_login_otp_succeeds_for_existing_tenant(): void
    {
        $company = Company::create([
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Tenant Owner',
            'email' => 'tenant@example.com',
            'password' => bcrypt('Password123!'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/send-login-otp', [
            'email' => 'tenant@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('email_otps', [
            'email' => 'tenant@example.com',
            'purpose' => 'login',
        ]);
    }

    public function test_verify_login_otp_logs_in_tenant(): void
    {
        $company = Company::create([
            'name' => 'Test Tenant',
            'email' => 'tenant@example.com',
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Tenant Owner',
            'email' => 'tenant@example.com',
            'password' => bcrypt('Password123!'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        EmailOtp::create([
            'email' => 'tenant@example.com',
            'code' => '654321',
            'purpose' => 'login',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/verify-login-otp', [
            'email' => 'tenant@example.com',
            'code' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['token', 'user']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_send_register_otp_and_verify_register_otp(): void
    {
        $sendResponse = $this->postJson('/api/auth/send-register-otp', [
            'email' => 'newtenant@example.com',
        ]);

        $sendResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $otp = EmailOtp::where('email', 'newtenant@example.com')->first();
        $this->assertNotNull($otp);

        $verifyResponse = $this->postJson('/api/auth/verify-register-otp', [
            'companyName' => 'New Company',
            'name' => 'New Tenant',
            'email' => 'newtenant@example.com',
            'phone' => '+254700123456',
            'password' => 'Password123!',
            'confirmPassword' => 'Password123!',
            'acceptTerms' => true,
            'code' => $otp->code,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'email' => 'newtenant@example.com',
        ]);
    }
}
