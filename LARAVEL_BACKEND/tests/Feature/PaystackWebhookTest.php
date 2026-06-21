<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_invalid_signature(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => 'pk_test_x',
                'secret_key' => 'sk_test_secret',
                'currency' => 'ngn',
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');

        $payload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'ref_1']]);

        $this->postJson('/api/paystack/webhook', json_decode($payload, true), [
            'x-paystack-signature' => 'invalid',
        ])->assertStatus(400);
    }

    public function test_webhook_accepts_valid_signature_for_unhandled_event(): void
    {
        $secret = 'sk_test_secret';
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => 'pk_test_x',
                'secret_key' => $secret,
                'currency' => 'ngn',
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');

        $payload = json_encode(['event' => 'customeridentification.failed', 'data' => []]);
        $signature = hash_hmac('sha512', $payload, $secret);

        $this->call(
            'POST',
            '/api/paystack/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_x-paystack-signature' => $signature,
            ],
            $payload
        )->assertOk();
    }
}
