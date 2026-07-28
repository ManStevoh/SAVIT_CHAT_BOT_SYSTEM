<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PaymentRecoveryAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\Orders\OrderPaymentDetailsService;
use App\Services\Orders\PaymentRecoveryService;
use App\Services\Storefront\DeliveryFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryCodPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $settingsOverrides = []): Company
    {
        $company = Company::create(['name' => 'Zone Co', 'email' => 'zone-'.uniqid().'@test.local', 'status' => 'active']);
        CompanySetting::create(array_merge([
            'company_id' => $company->id,
        ], $settingsOverrides));

        return $company;
    }

    private function actingCompanyOwner(Company $company): User
    {
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    // --- DeliveryFeeService ---------------------------------------------------

    public function test_delivery_fee_service_matches_zone_by_keyword(): void
    {
        $company = $this->makeCompany([
            'delivery_fees_enabled' => true,
            'default_delivery_fee' => 100,
        ]);

        DeliveryZone::create([
            'company_id' => $company->id,
            'name' => 'CBD',
            'fee' => 50,
            'keywords' => ['Nairobi CBD', 'Downtown'],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $quote = app(DeliveryFeeService::class)->quote($company, '123 Downtown Street', 200.0);

        $this->assertSame(50.0, $quote['fee']);
        $this->assertSame('CBD', $quote['zone_name']);
        $this->assertFalse($quote['free']);
    }

    public function test_delivery_fee_service_falls_back_to_default_when_no_zone_matches(): void
    {
        $company = $this->makeCompany([
            'delivery_fees_enabled' => true,
            'default_delivery_fee' => 100,
        ]);

        DeliveryZone::create([
            'company_id' => $company->id,
            'name' => 'CBD',
            'fee' => 50,
            'keywords' => ['Downtown'],
            'is_active' => true,
        ]);

        $quote = app(DeliveryFeeService::class)->quote($company, 'Some Random Suburb', 200.0);

        $this->assertSame(100.0, $quote['fee']);
        $this->assertNull($quote['zone_name']);
        $this->assertFalse($quote['free']);
    }

    public function test_delivery_fee_service_applies_free_threshold(): void
    {
        $company = $this->makeCompany([
            'delivery_fees_enabled' => true,
            'default_delivery_fee' => 100,
            'free_delivery_above' => 500,
        ]);

        $quote = app(DeliveryFeeService::class)->quote($company, 'Anywhere', 600.0);

        $this->assertSame(0.0, $quote['fee']);
        $this->assertTrue($quote['free']);
    }

    public function test_delivery_fee_service_returns_zero_when_disabled(): void
    {
        $company = $this->makeCompany([
            'delivery_fees_enabled' => false,
            'default_delivery_fee' => 100,
        ]);

        $quote = app(DeliveryFeeService::class)->quote($company, 'Anywhere', 50.0);

        $this->assertSame(0.0, $quote['fee']);
        $this->assertFalse($quote['free']);
    }

    // --- OrderPaymentDetailsService --------------------------------------------

    public function test_cod_and_bank_transfer_are_included_in_payment_methods(): void
    {
        $company = $this->makeCompany([
            'orders_accept_cod' => true,
            'orders_accept_bank_transfer' => true,
            'bank_transfer_instructions' => 'Bank: Test Bank, Acc: 12345',
        ]);

        $service = app(OrderPaymentDetailsService::class);
        $pay = $service->resolveAcceptance($company);

        $this->assertTrue($pay['cod']);
        $this->assertTrue($pay['bank_transfer']);

        $methods = $service->methodKeys($pay);
        $this->assertContains('cod', $methods);
        $this->assertContains('bank_transfer', $methods);
    }

    public function test_share_for_customer_message_mentions_cod_bank_and_pay_links(): void
    {
        $company = $this->makeCompany([
            'orders_accept_cod' => true,
            'orders_accept_bank_transfer' => true,
            'bank_transfer_instructions' => 'Bank: Test Bank, Acc: 12345',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'channel_user_id' => '254700000111',
            'customer_name' => 'Jane',
            'customer_phone' => '254700000111',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-COD-001',
            'customer_name' => 'Jane',
            'customer_phone' => '254700000111',
            'total' => 1500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $result = app(OrderPaymentDetailsService::class)->shareForCustomer($company, '254700000111');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Cash on delivery', $result['customer_message']);
        $this->assertStringContainsString('Bank transfer', $result['customer_message']);
        $this->assertStringContainsString('Pay online: '.$order->fresh()->publicPayUrl(), $result['customer_message']);
    }

    // --- Delivery zone API -------------------------------------------------

    public function test_authenticated_company_user_can_create_and_list_delivery_zones(): void
    {
        $company = $this->makeCompany();
        $this->actingCompanyOwner($company);

        $createResponse = $this->postJson('/api/company/delivery-zones', [
            'name' => 'Westlands',
            'fee' => 150,
            'keywords' => ['Westlands', 'ABC Place'],
            'isActive' => true,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('zone.name', 'Westlands');
        $this->assertEquals(150, $createResponse->json('zone.fee'));

        $this->assertDatabaseHas('delivery_zones', [
            'company_id' => $company->id,
            'name' => 'Westlands',
        ]);

        $listResponse = $this->getJson('/api/company/delivery-zones');
        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json());
        $this->assertSame('Westlands', $listResponse->json()[0]['name']);
    }

    public function test_delivery_zone_api_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/company/delivery-zones')->assertUnauthorized();
    }

    // --- PaymentRecoveryService ---------------------------------------------

    public function test_payment_recovery_service_sends_due_attempt_and_records_it(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.recovery.1']]], 200),
        ]);

        $company = $this->makeCompany([
            'payment_recovery_enabled' => true,
            'payment_recovery_hours' => [1, 24, 72],
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-recovery',
            'whatsapp_business_account_id' => 'waba-recovery',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'channel_user_id' => '254711000222',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000222',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-RECOVERY-001',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000222',
            'total' => 2500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->created_at = now()->subHours(2);
        $order->save();

        $result = app(PaymentRecoveryService::class)->processDue();

        $this->assertSame(1, $result['sent']);

        $this->assertDatabaseHas('payment_recovery_attempts', [
            'order_id' => $order->id,
            'company_id' => $company->id,
            'attempt_number' => 1,
            'status' => 'sent',
        ]);
    }

    public function test_payment_recovery_service_skips_orders_already_attempted_for_current_offset(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.recovery.2']]], 200),
        ]);

        $company = $this->makeCompany([
            'payment_recovery_enabled' => true,
            'payment_recovery_hours' => [1, 24, 72],
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-recovery-2',
            'whatsapp_business_account_id' => 'waba-recovery-2',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'channel_user_id' => '254711000333',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000333',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-RECOVERY-002',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000333',
            'total' => 2500,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $order->created_at = now()->subHours(2);
        $order->save();

        PaymentRecoveryAttempt::create([
            'order_id' => $order->id,
            'company_id' => $company->id,
            'attempt_number' => 1,
            'hours_after_order' => 1,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'sent_at' => now()->subHour(),
        ]);

        $result = app(PaymentRecoveryService::class)->processDue();

        // Attempt 1 already exists and attempt 2 (24h) is not yet due, so nothing new is sent.
        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, PaymentRecoveryAttempt::where('order_id', $order->id)->count());
    }

    public function test_payment_recovery_service_skips_cod_orders(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.recovery.3']]], 200),
        ]);

        $company = $this->makeCompany([
            'payment_recovery_enabled' => true,
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'phone-recovery-3',
            'whatsapp_business_account_id' => 'waba-recovery-3',
            'access_token' => 'wa-token',
            'status' => 'active',
            'onboarding_status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'channel' => 'whatsapp',
            'channel_user_id' => '254711000444',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000444',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'chat_id' => $chat->id,
            'order_number' => 'ORD-RECOVERY-003',
            'customer_name' => 'Jane',
            'customer_phone' => '254711000444',
            'total' => 2500,
            'status' => 'confirmed',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);
        $order->created_at = now()->subHours(2);
        $order->save();

        $result = app(PaymentRecoveryService::class)->processDue();

        $this->assertSame(0, $result['sent']);
        $this->assertDatabaseMissing('payment_recovery_attempts', ['order_id' => $order->id]);
    }
}
