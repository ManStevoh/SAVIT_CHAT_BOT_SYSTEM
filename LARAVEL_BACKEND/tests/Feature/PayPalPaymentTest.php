<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\User;
use App\Services\PayPalService;
use App\Services\PaymentGateways\Drivers\PayPalGatewayDriver;
use App\Services\PaymentGateways\PaymentGatewayRegistry;
use App\Services\PlatformPayments\Drivers\PlatformPayPalDriver;
use App\Services\PlatformPayments\PlatformPaymentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentGateway::updateOrCreate(
            ['slug' => 'paypal'],
            [
                'name' => 'PayPal (Cards & PayPal Balance)',
                'is_enabled' => true,
                'config' => [
                    'client_id' => 'TEST_CLIENT_ID_12345',
                    'client_secret' => 'TEST_CLIENT_SECRET_67890',
                    'webhook_id' => 'TEST_WEBHOOK_9999',
                    'env' => 'sandbox',
                    'currency' => 'usd',
                ],
            ]
        );
    }

    public function test_paypal_service_token_creation_and_capture(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'mock_access_token_abc123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL_ORDER_123',
                'status' => 'CREATED',
                'links' => [
                    [
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_123',
                        'rel' => 'approve',
                        'method' => 'GET',
                    ],
                ],
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL_ORDER_123/capture' => Http::response([
                'id' => 'PAYPAL_ORDER_123',
                'status' => 'COMPLETED',
            ], 200),
        ]);

        $service = app(PayPalService::class);
        $this->assertEquals('USD', $service->getCurrency());

        $tokenRes = $service->getAccessToken();
        $this->assertTrue($tokenRes['success']);
        $this->assertEquals('mock_access_token_abc123', $tokenRes['access_token']);

        $createRes = $service->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => ['currency_code' => 'USD', 'value' => '50.00'],
                ],
            ],
        ]);

        $this->assertTrue($createRes['success']);
        $this->assertEquals('PAYPAL_ORDER_123', $createRes['id']);
        $this->assertEquals('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL_ORDER_123', $createRes['approve_url']);

        $captureRes = $service->captureOrder('PAYPAL_ORDER_123');
        $this->assertTrue($captureRes['success']);
        $this->assertTrue($captureRes['paid']);
    }

    public function test_platform_paypal_driver_is_registered_and_available(): void
    {
        $registry = app(PlatformPaymentRegistry::class);
        $driver = $registry->getDriver('paypal');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(PlatformPayPalDriver::class, $driver);
        $this->assertTrue($driver->isAvailable());

        $metadata = $driver->getMetadata();
        $this->assertEquals('USD', $metadata['currency']);
        $this->assertEquals('sandbox', $metadata['env']);
    }

    public function test_platform_paypal_driver_initiates_plan_payment(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'mock_access_token_abc123',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'SUB_ORDER_999',
                'status' => 'CREATED',
                'links' => [
                    [
                        'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=SUB_ORDER_999',
                        'rel' => 'approve',
                    ],
                ],
            ], 200),
        ]);

        $company = Company::create(['name' => 'Acme Corp', 'email' => 'acme@example.com']);
        $plan = Plan::create([
            'name' => 'Growth Plan',
            'slug' => 'growth',
            'price_amount' => 49.00,
            'price_display' => '$49 / mo',
            'is_free' => false,
        ]);

        $driver = app(PlatformPaymentRegistry::class)->getDriver('paypal');
        $result = $driver->initiatePlanPayment($company, $plan);

        $this->assertTrue($result['success']);
        $this->assertEquals('paypal', $result['gateway']);
        $this->assertEquals('https://www.sandbox.paypal.com/checkoutnow?token=SUB_ORDER_999', $result['checkout_url']);
    }

    public function test_storefront_paypal_driver_is_registered_and_matches_selection(): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $driver = $registry->getDriver('paypal');

        $this->assertNotNull($driver);
        $this->assertInstanceOf(PayPalGatewayDriver::class, $driver);

        $company = Company::create(['name' => 'Store Merchant', 'email' => 'store@example.com']);
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'orders_accept_paypal' => true,
            'order_payment_paypal_config' => [
                'client_id' => 'MERCHANT_CLIENT_ID',
                'client_secret' => 'MERCHANT_CLIENT_SECRET',
                'currency' => 'usd',
                'env' => 'sandbox',
            ],
        ]);

        $this->assertTrue($driver->isReady($company));
        $this->assertTrue($driver->matchesCustomerInput('paypal'));
        $this->assertTrue($driver->matchesCustomerInput('pay with paypal'));

        $matched = $registry->matchCustomerSelection($company, 'paypal');
        $this->assertNotNull($matched);
        $this->assertEquals('paypal', $matched->getId());
    }

    public function test_merchant_can_update_paypal_settings_via_api(): void
    {
        $company = Company::create(['name' => 'Merchant Inc', 'email' => 'merchant@example.com']);
        CompanySetting::create([
            'company_id' => $company->id,
        ]);
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->patchJson('/api/company/settings', [
            'ordersAcceptPayPal' => true,
            'orderPaymentPayPalConfig' => [
                'client_id' => 'CLIENT_ID_NEW',
                'client_secret' => 'CLIENT_SECRET_SECRET_1234',
                'currency' => 'usd',
                'env' => 'production',
            ],
        ]);

        $response->assertStatus(200);

        $settings = CompanySetting::where('company_id', $company->id)->first();
        $this->assertNotNull($settings);
        $this->assertTrue((bool) $settings->orders_accept_paypal);
        $this->assertEquals('CLIENT_ID_NEW', $settings->order_payment_paypal_config['client_id']);
        $this->assertEquals('CLIENT_SECRET_SECRET_1234', $settings->order_payment_paypal_config['client_secret']);
        $this->assertEquals('production', $settings->order_payment_paypal_config['env']);

        // Check secret masking on GET
        $getResponse = $this->actingAs($user)->getJson('/api/company/settings');
        $getResponse->assertStatus(200);
        $paypalConfig = $getResponse->json('orderPaymentPayPalConfig');
        $this->assertEquals('CLIENT_ID_NEW', $paypalConfig['client_id']);
        $this->assertTrue(str_starts_with($paypalConfig['client_secret'], '••••••••'));
    }

    public function test_order_paypal_callback_marks_order_as_paid(): void
    {
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'mock_token',
                'expires_in' => 3600,
            ], 200),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/PP_RETURN_123/capture' => Http::response([
                'id' => 'PP_RETURN_123',
                'status' => 'COMPLETED',
            ], 200),
        ]);

        $company = Company::create(['name' => 'Store Merchant', 'email' => 'store2@example.com']);
        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_paypal' => true,
            'order_payment_paypal_config' => [
                'client_id' => 'MERCHANT_CLIENT_ID',
                'client_secret' => 'MERCHANT_CLIENT_SECRET',
            ],
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-999',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+1234567890',
            'total' => 30.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'pay_token' => 'test-pay-token-paypal',
        ]);

        $response = $this->get('/api/paypal/callback?token=PP_RETURN_123&reference=essem_pp_'.$order->id.'_xyz');

        $response->assertRedirect(url("/pay/{$order->pay_token}"));

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('paypal', $order->payment_method);
    }
}
