<?php

namespace Tests\Feature;

use App\Jobs\Orders\ProcessCustomerRetentionJob;
use App\Jobs\Orders\ProcessPaymentRecoveryJob;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\DineInTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Orders\CustomerRetentionService;
use App\Services\Orders\PaymentRecoveryService;
use App\Services\Orders\SpamOrderGuard;
use App\Services\Storefront\DeliveryFeeService;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class TakeAppParityCommerceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: CompanySetting, 2: Product, 3: User}
     */
    private function seedStoreCompany(array $companyExtras = [], array $settingExtras = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Parity Cafe',
            'email' => 'parity@test.local',
            'status' => 'active',
            'store_slug' => 'parity-cafe',
            'storefront_enabled' => true,
            'link_in_bio_enabled' => true,
            'link_in_bio_headline' => 'Parity Cafe',
            'link_in_bio_bio' => 'Coffee & bites',
            'link_in_bio_links' => [['label' => 'Menu', 'url' => 'https://example.com']],
        ], $companyExtras));

        $settings = CompanySetting::create(array_merge([
            'company_id' => $company->id,
            'orders_collect_payment_enabled' => true,
            'orders_accept_cod' => true,
            'orders_accept_bank_transfer' => true,
            'bank_transfer_instructions' => 'Pay to Acc 123456 Bank XYZ',
            'order_payment_manual_instructions' => 'Till 99999',
            'delivery_fees_enabled' => true,
            'default_delivery_fee' => 5,
            'free_delivery_above' => 50,
            'payment_recovery_enabled' => true,
            'payment_recovery_hours' => [1, 24],
            'spam_order_protection_enabled' => true,
            'spam_max_orders_per_hour' => 2,
            'spam_max_orders_per_day' => 5,
            'birthday_automation_enabled' => true,
            'birthday_coupon_percent' => 15,
            'winback_automation_enabled' => true,
            'winback_days_inactive' => 30,
            'dine_in_enabled' => true,
            'whatsapp_number' => '254700000001',
        ], $settingExtras));

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Latte',
            'price' => 10,
            'stock' => 100,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => false,
            'requires_delivery_address' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return [$company, $settings, $product, $user];
    }

    public function test_public_storefront_catalog_and_checkout_create_order_with_pay_token(): void
    {
        [$company, , $product] = $this->seedStoreCompany();

        $this->get('/s/parity-cafe')->assertOk();
        $this->get('/b/parity-cafe')->assertOk();

        $this->post('/s/parity-cafe/cart', [
            'productId' => $product->id,
            'quantity' => 2,
        ])->assertRedirect();

        $this->post('/s/parity-cafe/checkout', [
            'customerName' => 'Ada',
            'customerPhone' => '254711223344',
            'fulfillmentType' => 'delivery',
            'deliveryAddress' => 'Westlands Nairobi',
        ])->assertRedirect();

        $order = Order::where('company_id', $company->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertNotEmpty($order->pay_token);
        $this->assertNotEmpty($order->invoice_token);
        $this->assertSame('storefront', $order->source);
        $this->assertGreaterThan(0, (float) $order->total);

        $this->get('/pay/'.$order->pay_token)->assertOk();
        $this->get('/invoice/'.$order->invoice_token)->assertOk();
    }

    public function test_delivery_fee_zone_match_and_free_threshold(): void
    {
        [$company] = $this->seedStoreCompany();
        DeliveryZone::create([
            'company_id' => $company->id,
            'name' => 'CBD',
            'fee' => 3,
            'keywords' => ['westlands', 'cbd'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = app(DeliveryFeeService::class);
        $zoneQuote = $service->quote($company->fresh('settings'), 'Near Westlands mall', 20);
        $this->assertSame(3.0, $zoneQuote['fee']);
        $this->assertSame('CBD', $zoneQuote['zone_name']);

        $freeQuote = $service->quote($company->fresh('settings'), 'Anywhere', 60);
        $this->assertSame(0.0, $freeQuote['fee']);
        $this->assertTrue($freeQuote['free']);
    }

    public function test_spam_guard_blocks_excess_hourly_orders(): void
    {
        [$company] = $this->seedStoreCompany();
        $phone = '254700111222';
        for ($i = 0; $i < 2; $i++) {
            Order::create([
                'company_id' => $company->id,
                'order_number' => 'ORD-SPAM'.$i,
                'customer_name' => 'Spam',
                'customer_phone' => $phone,
                'total' => 10,
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);
        }

        $result = app(SpamOrderGuard::class)->assertCanPlaceOrder($company->fresh('settings'), $phone);
        $this->assertFalse($result['allowed']);
    }

    public function test_payment_recovery_records_attempt(): void
    {
        [$company] = $this->seedStoreCompany();
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pnid-1',
            'access_token' => 'token',
            'whatsapp_business_account_id' => 'waba-1',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-REC1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700333444',
            'total' => 25,
            'status' => 'pending',
            'payment_status' => 'pending',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
        // Force created_at past recovery offset (Eloquent may rewrite timestamps on create).
        Order::where('id', $order->id)->update([
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $mock = Mockery::mock(WhatsAppMessageSenderService::class);
        $mock->shouldReceive('sendText')->once()->andReturn(['success' => true, 'message_id' => 'wamid.1']);
        $this->app->instance(WhatsAppMessageSenderService::class, $mock);

        $result = app(PaymentRecoveryService::class)->processDue();
        $this->assertGreaterThanOrEqual(1, $result['sent'] ?? 0);
        $this->assertDatabaseHas('payment_recovery_attempts', [
            'order_id' => $order->id,
            'attempt_number' => 1,
            'status' => 'sent',
        ]);
    }

    public function test_birthday_retention_marks_wish(): void
    {
        [$company] = $this->seedStoreCompany();
        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'pnid-2',
            'access_token' => 'token',
            'whatsapp_business_account_id' => 'waba-2',
            'status' => 'active',
            'onboarding_status' => 'active',
            'connected_at' => now(),
        ]);
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700555666',
            'customer_name' => 'Sam',
            'status' => 'active',
            'birthday' => now()->toDateString(),
            'marketing_opt_in' => true,
            'last_message_at' => now(),
        ]);

        $mock = Mockery::mock(WhatsAppMessageSenderService::class);
        $mock->shouldReceive('sendText')->once()->andReturn(['success' => true, 'message_id' => 'wamid.2']);
        $this->app->instance(WhatsAppMessageSenderService::class, $mock);

        $sent = app(CustomerRetentionService::class)->sendBirthdayWishes();
        $this->assertSame(1, $sent);
        $this->assertNotNull($chat->fresh()->last_birthday_wish_at);
    }

    public function test_dine_in_table_api_and_public_qr_page(): void
    {
        [, , , $user] = $this->seedStoreCompany();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/dine-in-tables', [
            'name' => 'Table 4',
            'seats' => 4,
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $token = $create->json('table.qrToken');
        $this->assertNotEmpty($token);

        $this->get('/t/'.$token)->assertRedirect();
        $this->get('/s/parity-cafe/table/'.$token)->assertOk();
    }

    public function test_delivery_zones_api_crud(): void
    {
        [, , , $user] = $this->seedStoreCompany();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/delivery-zones', [
            'name' => 'Kilimani',
            'fee' => 7.5,
            'keywords' => ['kilimani', 'yaya'],
            'isActive' => true,
        ])->assertSuccessful();

        $this->getJson('/api/company/delivery-zones')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Kilimani']);
    }

    public function test_scheduled_jobs_are_dispatchable(): void
    {
        $this->assertTrue(class_exists(ProcessPaymentRecoveryJob::class));
        $this->assertTrue(class_exists(ProcessCustomerRetentionJob::class));
        (new ProcessPaymentRecoveryJob)->handle(app(PaymentRecoveryService::class));
        (new ProcessCustomerRetentionJob)->handle(app(CustomerRetentionService::class));
        $this->assertTrue(true);
    }
}
