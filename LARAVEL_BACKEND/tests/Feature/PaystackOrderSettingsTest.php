<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentGateways\PaymentGatewayRegistry;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaystackOrderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_paystack_credentials_are_saved_and_make_the_gateway_available(): void
    {
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => ['secret_key' => 'sk_test_platform', 'public_key' => 'pk_test_platform'],
        ]);

        $company = Company::create([
            'name' => 'Paystack Shop',
            'email' => 'paystack-shop@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($owner);

        $this->putJson('/api/company/settings', [
            'ordersAcceptPaystack' => true,
            'orderPaymentPaystackConfig' => [
                'secret_key' => 'sk_test_tenant',
                'public_key' => 'pk_test_tenant',
                'currency' => 'kes',
                'env' => 'sandbox',
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $settings = CompanySetting::where('company_id', $company->id)->firstOrFail();
        $this->assertTrue($settings->orders_accept_paystack);
        $this->assertSame('sk_test_tenant', $settings->order_payment_paystack_config['secret_key']);
        $this->assertSame('pk_test_tenant', $settings->order_payment_paystack_config['public_key']);
        $this->assertSame('sandbox', $settings->order_payment_paystack_config['env']);

        $available = app(PaymentGatewayRegistry::class)
            ->getAvailableDrivers($company->fresh(['settings']));

        $this->assertContains('paystack', array_map(fn ($driver) => $driver->getId(), $available));
    }

    public function test_paystack_order_email_falls_back_to_phone_when_missing(): void
    {
        $company = Company::create([
            'name' => 'Test Co',
            'email' => 'test@example.com',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-TEST',
            'customer_name' => 'Jane',
            'customer_phone' => '+254 712 345 678',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $email = app(PaystackService::class)->resolveOrderCustomerEmail($order);

        $this->assertSame('254712345678@customers.essemchat.com', $email);
    }

    public function test_paystack_order_email_uses_valid_customer_email_when_present(): void
    {
        $company = Company::create([
            'name' => 'Test Co 2',
            'email' => 'test2@example.com',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-TEST2',
            'customer_name' => 'John',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+254712345678',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $email = app(PaystackService::class)->resolveOrderCustomerEmail($order);

        $this->assertSame('buyer@example.com', $email);
    }

    public function test_paystack_order_email_falls_back_to_phone_when_customer_email_invalid(): void
    {
        $company = Company::create([
            'name' => 'Test Co 3',
            'email' => 'test3@example.com',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-TEST3',
            'customer_name' => 'Alex',
            'customer_email' => 'not-an-email',
            'customer_phone' => '0712345678',
            'total' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $email = app(PaystackService::class)->resolveOrderCustomerEmail($order);

        $this->assertSame('0712345678@customers.essemchat.com', $email);
    }
}
