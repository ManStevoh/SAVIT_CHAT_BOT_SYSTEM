<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\OrderFlowService;
use App\Services\Orders\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceTaxTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User, 2: Product}
     */
    private function seedCompanyCatalog(): array
    {
        $company = Company::create([
            'name' => 'Tax Co',
            'email' => 'tax@test.local',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'tax_enabled' => true,
            'display_currency' => 'USD',
            'orders_collect_payment_enabled' => true,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
            'email' => 'owner-tax@test.local',
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Widget',
            'price' => 100,
            'stock' => 50,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => false,
        ]);

        return [$company, $user, $product];
    }

    public function test_tax_rate_crud_and_default_exclusivity(): void
    {
        [, $user] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/company/tax-rates', [
            'name' => 'VAT',
            'code' => 'VAT',
            'rate' => 16,
            'isDefault' => true,
            'isInclusive' => false,
        ]);
        $create->assertCreated();
        $firstId = $create->json('taxRate.id');

        $second = $this->postJson('/api/company/tax-rates', [
            'name' => 'GST',
            'code' => 'GST',
            'rate' => 5,
            'isDefault' => true,
        ]);
        $second->assertCreated();

        $this->assertFalse(TaxRate::find($firstId)->fresh()->is_default);
        $this->assertTrue(TaxRate::find($second->json('taxRate.id'))->fresh()->is_default);

        $list = $this->getJson('/api/company/tax-rates');
        $list->assertOk();
        $this->assertCount(2, $list->json());
    }

    public function test_exclusive_tax_applied_on_order_flow_checkout(): void
    {
        [$company, , $product] = $this->seedCompanyCatalog();
        $rate = TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT',
            'code' => 'VAT',
            'rate' => 16,
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);
        $product->update(['tax_rate_id' => $rate->id]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_phone' => '254700000001',
            'customer_name' => 'Buyer',
            'status' => 'active',
            'last_message' => 'order',
            'last_message_at' => now(),
            'conversation_step' => OrderFlowService::STEP_CONFIRM,
            'order_draft' => [
                'items' => [[
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'quantity' => 2,
                    'fulfillment_data' => $product->fulfillmentSnapshot(),
                ]],
            ],
        ]);

        $reply = app(OrderFlowService::class)->processMessage(
            $chat->fresh(),
            $company->fresh(['settings']),
            'confirm',
            'Buyer',
            '254700000001',
        );

        $this->assertNotNull($reply);
        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(200.0, (float) $order->subtotal);
        $this->assertEquals(32.0, (float) $order->tax_total);
        $this->assertEquals(232.0, (float) $order->total);
        $this->assertStringContainsString('Subtotal:', (string) $reply);
        $this->assertStringContainsString('VAT', (string) $reply);
        $this->assertStringContainsString('Total:', (string) $reply);

        $line = $order->orderProducts()->first();
        $this->assertEquals(32.0, (float) $line->tax_amount);
        $this->assertEquals('VAT', $line->tax_name);
    }

    public function test_inclusive_tax_keeps_catalog_total(): void
    {
        [$company, , $product] = $this->seedCompanyCatalog();
        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT Incl',
            'code' => 'VAT',
            'rate' => 16,
            'is_inclusive' => true,
            'is_default' => true,
            'is_active' => true,
        ]);

        $calc = app(TaxCalculationService::class)->calculateForCompany($company->fresh(['settings']), [
            [
                'product_id' => $product->id,
                'price' => 116,
                'quantity' => 1,
            ],
        ]);

        $this->assertEquals(116.0, $calc['total']);
        $this->assertEquals(16.0, $calc['tax_total']);
        $this->assertEquals(100.0, $calc['subtotal']);
    }

    public function test_tax_disabled_means_zero_tax(): void
    {
        [$company, , $product] = $this->seedCompanyCatalog();
        $company->settings->update(['tax_enabled' => false]);
        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT',
            'rate' => 16,
            'is_default' => true,
            'is_active' => true,
        ]);

        $calc = app(TaxCalculationService::class)->calculateForCompany($company->fresh(['settings']), [
            ['product_id' => $product->id, 'price' => 100, 'quantity' => 1],
        ]);

        $this->assertEquals(100.0, $calc['total']);
        $this->assertEquals(0.0, $calc['tax_total']);
    }

    public function test_settings_tax_enabled_toggle(): void
    {
        [$company, $user] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);

        $res = $this->putJson('/api/company/settings', ['taxEnabled' => false]);
        $res->assertOk();
        $this->assertFalse((bool) $company->settings()->first()->tax_enabled);

        $show = $this->getJson('/api/company/settings');
        $show->assertOk();
        $this->assertFalse($show->json('taxEnabled'));
    }

    public function test_product_accepts_tax_rate_id(): void
    {
        [$company, $user, $product] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);
        $rate = TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT',
            'rate' => 10,
            'is_active' => true,
        ]);

        $res = $this->putJson('/api/company/products/'.$product->id, [
            'taxRateId' => $rate->id,
        ]);
        $res->assertOk();
        $this->assertEquals($rate->id, $product->fresh()->tax_rate_id);
    }

    public function test_preview_totals_endpoint_applies_exclusive_tax(): void
    {
        [$company, $user, $product] = $this->seedCompanyCatalog();
        Sanctum::actingAs($user);
        TaxRate::create([
            'company_id' => $company->id,
            'name' => 'VAT',
            'code' => 'VAT',
            'rate' => 16,
            'is_inclusive' => false,
            'is_default' => true,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/company/orders/preview-totals', [
            'items' => [[
                'productId' => $product->id,
                'price' => 100,
                'quantity' => 1,
            ]],
        ]);
        $res->assertOk()
            ->assertJsonPath('subtotal', 100)
            ->assertJsonPath('taxTotal', 16)
            ->assertJsonPath('total', 116);
    }
}
