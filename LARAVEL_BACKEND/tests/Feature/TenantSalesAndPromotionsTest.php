<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Commerce\CommercePromotionsService;
use App\Services\OrderFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSalesAndPromotionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $attrs = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Promo Co',
            'email' => 'promo-'.uniqid().'@test.local',
            'status' => 'active',
        ], $attrs));

        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);

        return $company;
    }

    private function actingOwner(Company $company): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_tenant_can_set_product_compare_at_price_for_sale(): void
    {
        $company = $this->makeCompany();
        $this->actingOwner($company);

        $create = $this->postJson('/api/company/products', [
            'name' => 'BF Sneakers',
            'description' => 'On sale',
            'price' => 70,
            'compareAtPrice' => 100,
            'category' => 'Shoes',
            'stock' => 5,
            'productType' => 'physical',
        ]);

        $create->assertOk();
        $this->assertSame(100.0, (float) $create->json('product.compareAtPrice'));
        $this->assertTrue((bool) $create->json('product.onSale'));

        $id = $create->json('product.id');
        $update = $this->putJson("/api/company/products/{$id}", [
            'compareAtPrice' => null,
        ]);
        $update->assertOk();
        $this->assertNull($update->json('product.compareAtPrice'));
        $this->assertFalse((bool) $update->json('product.onSale'));
    }

    public function test_tenant_can_manage_storefront_coupons_and_announcement(): void
    {
        $company = $this->makeCompany([
            'store_slug' => 'promo-shop',
            'storefront_enabled' => true,
        ]);
        $this->actingOwner($company);

        $coupon = $this->postJson('/api/company/storefront-coupons', [
            'code' => 'bf50',
            'type' => 'percent',
            'value' => 50,
            'isActive' => true,
        ]);
        $coupon->assertCreated()->assertJsonPath('coupon.code', 'BF50');

        $settings = $this->putJson('/api/company/settings', [
            'storefrontAnnouncementBar' => 'Black Friday — use BF50',
        ]);
        $settings->assertOk();

        $this->getJson('/api/company/settings')
            ->assertOk()
            ->assertJsonPath('storefrontAnnouncementBar', 'Black Friday — use BF50');

        $snap = app(CommercePromotionsService::class)->snapshot($company->fresh());
        $this->assertSame('Black Friday — use BF50', $snap['announcement']);
        $this->assertNotEmpty($snap['coupons']);
        $this->assertSame('BF50', $snap['coupons'][0]['code']);

        $prompt = app(CommercePromotionsService::class)->agentPromptBlock($company->fresh());
        $this->assertStringContainsString('BF50', $prompt);
    }

    public function test_whatsapp_catalog_shows_sale_price_markup(): void
    {
        $company = $this->makeCompany();
        Product::create([
            'company_id' => $company->id,
            'name' => 'Sale Tee',
            'price' => 20,
            'compare_at_price' => 40,
            'stock' => 3,
            'status' => 'active',
            'product_type' => 'physical',
        ]);

        $catalog = app(OrderFlowService::class)->formatCatalogForDisplay($company);
        $this->assertStringContainsString('Sale Tee', $catalog);
        $this->assertStringContainsString('-50%', $catalog);
    }
}
