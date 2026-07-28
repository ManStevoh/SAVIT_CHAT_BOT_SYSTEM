<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DineInTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\OrderFlowService;
use App\Services\Orders\CustomerRetentionService;
use App\Services\Orders\SpamOrderGuard;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutSpamRetentionDineInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User, 2: Product}
     */
    private function seedCompanyCatalog(array $companyOverrides = [], array $settingOverrides = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Parity Co',
            'email' => 'parity@test.local',
            'status' => 'active',
        ], $companyOverrides));
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create(array_merge([
            'company_id' => $company->id,
            'display_currency' => 'USD',
            'orders_collect_payment_enabled' => false,
        ], $settingOverrides));
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
            'email' => 'owner-parity@test.local',
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Burger',
            'price' => 10,
            'stock' => 50,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => false,
        ]);

        return [$company, $user, $product];
    }

    public function test_order_flow_summary_includes_items_and_confirmation_includes_pay_links(): void
    {
        [$company, , $product] = $this->seedCompanyCatalog();

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000010',
            'customer_name' => 'Buyer',
            'status' => 'active',
            'last_message' => 'order',
            'last_message_at' => now(),
            'conversation_step' => OrderFlowService::STEP_PRODUCT,
            'order_draft' => [
                'items' => [[
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => 3,
                    'fulfillment_data' => $product->fulfillmentSnapshot(),
                ]],
                'fulfillment_type' => 'pickup',
            ],
        ]);

        $service = app(OrderFlowService::class);

        // "done" (STEP_PRODUCT -> STEP_CONFIRM) must render a clear, itemized summary.
        $summaryReply = $service->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'done',
            'Buyer',
            '254700000010',
        );
        $this->assertNotNull($summaryReply);
        $this->assertStringContainsString('Burger', (string) $summaryReply);
        $this->assertStringContainsString('3', (string) $summaryReply);
        $this->assertStringContainsString('Subtotal:', (string) $summaryReply);
        $this->assertStringContainsString('Total:', (string) $summaryReply);
        $this->assertStringContainsString('Pickup', (string) $summaryReply);

        // "confirm" (STEP_CONFIRM) creates the order and shares pay + invoice links.
        $confirmReply = $service->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'confirm',
            'Buyer',
            '254700000010',
        );
        $this->assertNotNull($confirmReply);
        $this->assertStringContainsString('Pay online:', (string) $confirmReply);
        $this->assertStringContainsString('Invoice:', (string) $confirmReply);

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('pickup', $order->fulfillment_type);
        $this->assertEquals(30.0, (float) $order->total);
        $this->assertNotEmpty($order->pay_token);

        // Public helper formats a consistent summary directly from the persisted order.
        $summary = $service->formatOrderSummary($order->fresh(['orderProducts']));
        $this->assertStringContainsString('Burger', $summary);
        $this->assertStringContainsString('Total:', $summary);
    }

    public function test_spam_order_guard_blocks_after_hourly_limit_exceeded(): void
    {
        [$company] = $this->seedCompanyCatalog([], [
            'spam_order_protection_enabled' => true,
            'spam_max_orders_per_hour' => 2,
            'spam_max_orders_per_day' => 20,
        ]);
        $phone = '254700000099';

        for ($i = 0; $i < 2; $i++) {
            Order::create([
                'company_id' => $company->id,
                'order_number' => 'ORD-TEST'.$i,
                'customer_name' => 'Buyer',
                'customer_phone' => $phone,
                'subtotal' => 10,
                'tax_total' => 0,
                'total' => 10,
                'status' => 'pending',
                'payment_status' => 'pending',
                'created_at' => now(),
            ]);
        }

        $guard = app(SpamOrderGuard::class);
        $result = $guard->assertCanPlaceOrder($company->fresh(['settings']), $phone, null);

        $this->assertFalse($result['allowed']);
        $this->assertNotEmpty($result['reason'] ?? null);
    }

    public function test_spam_order_guard_blocks_chat_flagged_as_blocked_from_ordering(): void
    {
        [$company] = $this->seedCompanyCatalog();
        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000077',
            'customer_name' => 'Blocked Buyer',
            'status' => 'active',
            'last_message' => 'hi',
            'last_message_at' => now(),
            'blocked_from_ordering' => true,
        ]);

        $guard = app(SpamOrderGuard::class);
        $result = $guard->assertCanPlaceOrder($company->fresh(['settings']), $chat->customer_phone, $chat);

        $this->assertFalse($result['allowed']);
    }

    public function test_block_ordering_api_endpoint_flags_chat(): void
    {
        [$company, $user] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000055',
            'customer_name' => 'Repeat Buyer',
            'status' => 'active',
            'last_message' => 'hi',
            'last_message_at' => now(),
        ]);

        $res = $this->postJson("/api/company/chats/{$chat->id}/block-ordering");
        $res->assertOk()->assertJsonPath('blockedFromOrdering', true);
        $this->assertTrue((bool) $chat->fresh()->blocked_from_ordering);

        $res = $this->postJson("/api/company/chats/{$chat->id}/unblock-ordering");
        $res->assertOk()->assertJsonPath('blockedFromOrdering', false);
        $this->assertFalse((bool) $chat->fresh()->blocked_from_ordering);
    }

    public function test_birthday_service_marks_last_birthday_wish_at(): void
    {
        [$company] = $this->seedCompanyCatalog([], [
            'birthday_automation_enabled' => true,
            'birthday_coupon_percent' => 15,
        ]);

        WhatsAppAccount::create([
            'company_id' => $company->id,
            'phone_number_id' => 'test-phone-id',
            'access_token' => 'test-access-token',
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000123',
            'customer_name' => 'Birthday Buyer',
            'status' => 'active',
            'last_message' => 'hi',
            'last_message_at' => now(),
            'birthday' => now()->subYears(20)->format('Y-m-d'),
            'marketing_opt_in' => true,
        ]);

        $this->mock(WhatsAppMessageSenderService::class, function ($mock) {
            $mock->shouldReceive('sendText')->once()->andReturn(['success' => true]);
        });

        $sent = app(CustomerRetentionService::class)->sendBirthdayWishes();

        $this->assertSame(1, $sent);
        $this->assertNotNull($chat->fresh()->last_birthday_wish_at);
    }

    public function test_dine_in_table_create_via_api_and_public_token_returns_200(): void
    {
        [$company, $user] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/dine-in-tables', [
            'name' => 'Table 5',
            'seats' => 4,
        ]);
        $create->assertCreated()->assertJsonPath('success', true);
        $qrToken = $create->json('table.qrToken');
        $this->assertNotEmpty($qrToken);

        $table = DineInTable::where('qr_token', $qrToken)->first();
        $this->assertNotNull($table);
        $this->assertEquals('Table 5', $table->name);

        // Storefront disabled by default → public token route renders the minimal dine-in page directly.
        $this->get("/t/{$qrToken}")->assertOk();
    }
}
