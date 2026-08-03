<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;

use App\Services\FlutterwaveService;
use App\Services\PaymentGateways\Drivers\FlutterwaveGatewayDriver;
use App\Services\PaymentGateways\PaymentGatewayRegistry;
use App\Services\PlatformPayments\Drivers\PlatformFlutterwaveDriver;
use App\Services\PlatformPayments\PlatformPaymentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlutterwavePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::updateOrCreate(
            ['slug' => 'flutterwave'],
            [
                'name' => 'Flutterwave (Cards, Mobile Money & Bank)',
                'is_enabled' => true,
                'config' => [
                    'public_key' => 'FLWPUBK_TEST-12345',
                    'secret_key' => 'FLWSECK_TEST-67890',
                    'secret_hash' => 'test_hash_val',
                    'env' => 'sandbox',
                    'currency' => 'kes',
                ],
            ]
        );
    }

    public function test_flutterwave_service_initialization_and_verification(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'message' => 'Hosted Link Created',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/12345',
                ],
            ], 200),
            'https://api.flutterwave.com/v3/transactions/28820/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 28820,
                    'tx_ref' => 'flw_ref_123',
                    'status' => 'successful',
                    'amount' => 1500,
                    'currency' => 'KES',
                ],
            ], 200),
        ]);

        $service = app(FlutterwaveService::class);
        $this->assertEquals('KES', $service->getCurrency());

        $init = $service->initializePayment([
            'amount' => 1500,
            'tx_ref' => 'flw_ref_123',
        ]);
        $this->assertTrue($init['success']);
        $this->assertEquals('https://checkout.flutterwave.com/v3/hosted/pay/12345', $init['link']);

        $verify = $service->verifyTransaction('28820');
        $this->assertTrue($verify['success']);
        $this->assertTrue($verify['paid']);
    }

    public function test_platform_flutterwave_driver_is_registered_and_available(): void
    {
        $registry = app(PlatformPaymentRegistry::class);
        $driver = $registry->getDriver('flutterwave');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(PlatformFlutterwaveDriver::class, $driver);
        $this->assertTrue($driver->isAvailable());

        $metadata = $driver->getMetadata();
        $this->assertEquals('KES', $metadata['currency']);
        $this->assertEquals('sandbox', $metadata['env']);
    }

    public function test_platform_flutterwave_driver_initiates_plan_payment(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data' => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/sub_123',
                ],
            ], 200),
        ]);

        $company = Company::factory()->create(['email' => 'admin@merchant.com', 'name' => 'Acme Merchant']);
        $plan = Plan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'price_amount' => 5000,
            'is_free' => false,
        ]);

        $driver = app(PlatformFlutterwaveDriver::class);
        $result = $driver->initiatePlanPayment($company, $plan, ['phone' => '+254712345678']);

        $this->assertTrue($result['success']);
        $this->assertEquals('flutterwave', $result['gateway']);
        $this->assertEquals('https://checkout.flutterwave.com/v3/hosted/pay/sub_123', $result['checkout_url']);
    }

    public function test_merchant_flutterwave_driver_is_ready_when_configured(): void
    {
        $company = Company::factory()->create();
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'orders_accept_flutterwave' => true,
            'order_payment_flutterwave_config' => [
                'public_key' => 'FLWPUBK_TEST-merchant',
                'secret_key' => 'FLWSECK_TEST-merchant',
                'env' => 'sandbox',
                'currency' => 'kes',
            ],
        ]);

        $registry = app(PaymentGatewayRegistry::class);
        $driver = $registry->getDriver('flutterwave');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(FlutterwaveGatewayDriver::class, $driver);
        $this->assertTrue($driver->isReady($company));
    }

    public function test_flutterwave_callback_completes_order_payment(): void
    {
        $company = Company::factory()->create();
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_flutterwave' => true,
            'order_payment_flutterwave_config' => [
                'secret_key' => 'FLWSECK_TEST-merchant',
                'env' => 'sandbox',
            ],
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1001',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'total' => 2000.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $txRef = 'essem_flw_'.$order->id.'_test999';

        Http::fake([
            'https://api.flutterwave.com/v3/transactions/77777/verify' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 77777,
                    'tx_ref' => $txRef,
                    'status' => 'successful',
                    'amount' => 2000.00,
                    'currency' => 'KES',
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/flutterwave/callback?status=successful&tx_ref={$txRef}&transaction_id=77777");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Order payment completed',
        ]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('flutterwave', $order->fresh()->payment_method);
    }

    public function test_admin_and_merchant_settings_flutterwave_saving_and_masking(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $company = Company::factory()->create();
        $user->update(['company_id' => $company->id]);

        // 1. Merchant settings save
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/company/settings', [
            'ordersAcceptFlutterwave' => true,
            'orderPaymentFlutterwaveConfig' => [
                'public_key' => 'FLWPUBK_TEST-pub',
                'secret_key' => 'FLWSECK_TEST-secret99',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        $response->assertStatus(200);

        $getRes = $this->actingAs($user, 'sanctum')->getJson('/api/company/settings');
        $getRes->assertStatus(200);
        $getRes->assertJson([
            'ordersAcceptFlutterwave' => true,
            'orderPaymentFlutterwaveConfigured' => true,
            'orderPaymentFlutterwaveConfig' => [
                'public_key' => 'FLWPUBK_TEST-pub',
                'secret_key' => '••••••••et99',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        // 2. Admin payment gateway update
        $adminRes = $this->actingAs($user, 'sanctum')->putJson('/api/admin/payment-gateways/flutterwave', [
            'isEnabled' => true,
            'config' => [
                'public_key' => 'admin_flw_pub',
                'secret_key' => 'admin_flw_sec_7777',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        $adminRes->assertStatus(200);
        $adminRes->assertJson([
            'success' => true,
            'gateway' => [
                'slug' => 'flutterwave',
                'isEnabled' => true,
                'config' => [
                    'public_key' => 'admin_flw_pub',
                    'secret_key' => '••••••••7777',
                    'env' => 'sandbox',
                ],
            ],
        ]);
    }
}
