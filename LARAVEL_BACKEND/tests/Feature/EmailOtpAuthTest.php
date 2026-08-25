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
