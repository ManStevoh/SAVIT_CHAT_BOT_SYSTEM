<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;

use App\Services\PaymentGateways\Drivers\PesapalGatewayDriver;
use App\Services\PaymentGateways\PaymentGatewayRegistry;
use App\Services\PesapalService;
use App\Services\PlatformPayments\Drivers\PlatformPesapalDriver;
use App\Services\PlatformPayments\PlatformPaymentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesapalPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::updateOrCreate(
            ['slug' => 'pesapal'],
            [
                'name' => 'Pesapal (Cards, Mobile Money & Bank)',
                'is_enabled' => true,
                'config' => [
                    'consumer_key' => 'test_consumer_key',
                    'consumer_secret' => 'test_consumer_secret',
                    'env' => 'sandbox',
                    'currency' => 'kes',
                    'ipn_id' => 'test-ipn-guid-1234',
                ],
            ]
        );
    }

    public function test_pesapal_service_environment_base_url_and_auth_token(): void
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response([
                'token' => 'mock_bearer_token_123',
                'status' => '200',
            ], 200),
        ]);

        $service = app(PesapalService::class);
        $this->assertEquals('https://cybqa.pesapal.com/pesapalv3', $service->getBaseUrl());
        $this->assertEquals('KES', $service->getCurrency());

        $auth = $service->getAuthToken();
        $this->assertTrue($auth['success']);
        $this->assertEquals('mock_bearer_token_123', $auth['token']);
    }

    public function test_platform_pesapal_driver_is_registered_and_available(): void
    {
        $registry = app(PlatformPaymentRegistry::class);
        $driver = $registry->getDriver('pesapal');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(PlatformPesapalDriver::class, $driver);
        $this->assertTrue($driver->isAvailable());

        $metadata = $driver->getMetadata();
        $this->assertEquals('KES', $metadata['currency']);
        $this->assertEquals('sandbox', $metadata['env']);
    }

    public function test_platform_pesapal_driver_initiates_plan_payment(): void
    {
        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'mock_token'], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'redirect_url' => 'https://cybqa.pesapal.com/pesapalv3/pay?id=123',
                'order_tracking_id' => 'track_abc_123',
                'status' => '200',
            ], 200),
        ]);

        $company = Company::factory()->create(['email' => 'admin@merchant.com', 'name' => 'Acme Merchant']);
        $plan = Plan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro',
            'price_amount' => 5000,
            'is_free' => false,
        ]);

        $driver = app(PlatformPesapalDriver::class);
        $result = $driver->initiatePlanPayment($company, $plan, ['phone' => '+254712345678']);

        $this->assertTrue($result['success']);
        $this->assertEquals('pesapal', $result['gateway']);
        $this->assertEquals('https://cybqa.pesapal.com/pesapalv3/pay?id=123', $result['checkout_url']);
        $this->assertEquals('track_abc_123', $result['order_tracking_id']);
    }

    public function test_merchant_pesapal_driver_is_ready_when_configured(): void
    {
        $company = Company::factory()->create();
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'orders_accept_pesapal' => true,
            'order_payment_pesapal_config' => [
                'consumer_key' => 'merchant_key',
                'consumer_secret' => 'merchant_secret',
                'env' => 'sandbox',
                'currency' => 'kes',
            ],
        ]);

        $registry = app(PaymentGatewayRegistry::class);
        $driver = $registry->getDriver('pesapal');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(PesapalGatewayDriver::class, $driver);
        $this->assertTrue($driver->isReady($company));
    }

    public function test_pesapal_callback_completes_order_payment(): void
    {
        $company = Company::factory()->create();
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_pesapal' => true,
            'order_payment_pesapal_config' => [
                'consumer_key' => 'merchant_key',
                'consumer_secret' => 'merchant_secret',
                'env' => 'sandbox',
            ],
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1001',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'total' => 1500.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $merchantReference = 'essem_ord_'.$order->id.'_test123';
        $orderTrackingId = 'track_pesapal_999';

        Http::fake([
            'https://cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'mock_token'], 200),
            'https://cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus?orderTrackingId='.$orderTrackingId => Http::response([
                'payment_status_description' => 'Completed',
                'status_code' => 1,
                'amount' => 1500.00,
                'currency' => 'KES',
            ], 200),
        ]);

        $response = $this->getJson("/api/pesapal/callback?OrderTrackingId={$orderTrackingId}&OrderMerchantReference={$merchantReference}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => '200',
            'orderTrackingId' => $orderTrackingId,
        ]);

        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertEquals('pesapal', $order->fresh()->payment_method);
    }

    public function test_admin_and_merchant_settings_pesapal_saving_and_masking(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $company = Company::factory()->create();
        $user->update(['company_id' => $company->id]);

        // 1. Merchant settings save
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/company/settings', [
            'ordersAcceptPesapal' => true,
            'orderPaymentPesapalConfig' => [
                'consumer_key' => 'ck_test_12345678',
                'consumer_secret' => 'cs_test_secret_9999',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        $response->assertStatus(200);

        $getRes = $this->actingAs($user, 'sanctum')->getJson('/api/company/settings');
        $getRes->assertStatus(200);
        $getRes->assertJson([
            'ordersAcceptPesapal' => true,
            'orderPaymentPesapalConfigured' => true,
            'orderPaymentPesapalConfig' => [
                'consumer_key' => 'ck_test_12345678',
                'consumer_secret' => '••••••••9999',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        // 2. Admin payment gateway update
        $adminRes = $this->actingAs($user, 'sanctum')->putJson('/api/admin/payment-gateways/pesapal', [
            'isEnabled' => true,
            'config' => [
                'consumer_key' => 'admin_pesapal_key',
                'consumer_secret' => 'admin_pesapal_secret_8888',
                'env' => 'sandbox',
                'currency' => 'KES',
            ],
        ]);

        $adminRes->assertStatus(200);
        $adminRes->assertJson([
            'success' => true,
            'gateway' => [
                'slug' => 'pesapal',
                'isEnabled' => true,
                'config' => [
                    'consumer_key' => 'admin_pesapal_key',
                    'consumer_secret' => '••••••••8888',
                    'env' => 'sandbox',
                ],
            ],
        ]);
    }
}
