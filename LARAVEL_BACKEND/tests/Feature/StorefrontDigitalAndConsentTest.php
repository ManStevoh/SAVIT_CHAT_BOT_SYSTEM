<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StorefrontLegalCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontDigitalAndConsentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: Product, 2: Product}
     */
    private function seedDigitalStore(): array
    {
        $company = Company::create([
            'name' => 'Digital Studio',
            'email' => 'studio@test.local',
            'status' => 'active',
            'store_slug' => 'digital-studio',
            'storefront_enabled' => true,
        ]);

        CompanySetting::create([
            'company_id' => $company->id,
            'orders_accept_cod' => true,
            'delivery_fees_enabled' => true,
            'default_delivery_fee' => 7,
            'whatsapp_number' => '254700888999',
            'display_currency' => 'KES',
        ]);

        $ebook = Product::create([
            'company_id' => $company->id,
            'name' => 'Brand Playbook',
            'slug' => 'brand-playbook',
            'price' => 25,
            'stock' => 100,
            'status' => 'active',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
        ]);

        $mug = Product::create([
            'company_id' => $company->id,
            'name' => 'Studio Mug',
            'slug' => 'studio-mug',
            'price' => 12,
            'stock' => 20,
            'status' => 'active',
            'product_type' => 'physical',
            'fulfillment_type' => 'shipping',
            'track_inventory' => true,
            'requires_delivery_address' => true,
        ]);

        return [$company, $ebook, $mug];
    }

    public function test_terms_page_uses_default_copy_until_merchant_writes_their_own(): void
    {
        [$company] = $this->seedDigitalStore();
        $slug = $company->store_slug;

        $this->get("/s/{$slug}/terms")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/terms')
                ->where('isDefault', true)
                ->where('body', StorefrontLegalCopy::defaultTerms((string) $company->name))
            );

        $company->update([
            'storefront_theme' => [
                'terms_title' => 'Studio purchase rules',
                'terms_body' => 'Refunds for digital files are handled by Digital Studio within 7 days.',
            ],
        ]);

        $this->get("/s/{$slug}/terms")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/terms')
                ->where('isDefault', false)
                ->where('title', 'Studio purchase rules')
                ->where('body', 'Refunds for digital files are handled by Digital Studio within 7 days.')
            );
    }

    public function test_merchant_can_save_storefront_terms_in_settings(): void
    {
        [$company] = $this->seedDigitalStore();
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        Sanctum::actingAs(User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]));

        $this->putJson('/api/company/settings', [
            'storefrontTermsTitle' => 'Our shop rules',
            'storefrontTermsBody' => 'Digital files are emailed after payment.',
        ])->assertOk();

        $this->getJson('/api/company/settings')
            ->assertOk()
            ->assertJsonPath('storefrontTermsTitle', 'Our shop rules')
            ->assertJsonPath('storefrontTermsBody', 'Digital files are emailed after payment.');
    }

    public function test_storefront_register_requires_terms_and_stores_optional_marketing_consent(): void
    {
        [$company] = $this->seedDigitalStore();
        $slug = $company->store_slug;

        $this->postJson("/s/{$slug}/account/register", [
            'name' => 'Ken Wafula',
            'email' => 'ken@example.com',
            'password' => 'secret1',
        ])->assertStatus(422)->assertJsonValidationErrors('acceptTerms');

        $this->postJson("/s/{$slug}/account/register", [
            'name' => 'Ken Wafula',
            'email' => 'ken@example.com',
            'password' => 'secret1',
            'acceptTerms' => true,
            'marketingConsent' => false,
        ])->assertOk()->assertJsonPath('success', true);

        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', 'ken@example.com')
            ->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->terms_accepted_at);
        $this->assertFalse($customer->marketing_consent);
        $this->assertNull($customer->marketing_consent_at);

        $this->postJson("/s/{$slug}/account/logout")->assertOk();

        $this->postJson("/s/{$slug}/account/register", [
            'name' => 'Ada Buyer',
            'email' => 'ada@example.com',
            'password' => 'secret1',
            'acceptTerms' => true,
            'marketingConsent' => true,
        ])->assertOk();

        $optedIn = StorefrontCustomer::where('email', 'ada@example.com')->first();
        $this->assertTrue($optedIn?->marketing_consent);
        $this->assertNotNull($optedIn?->marketing_consent_at);
    }

    public function test_digital_only_checkout_skips_address_requires_email_and_records_consent(): void
    {
        [$company, $ebook] = $this->seedDigitalStore();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", [
            'productId' => $ebook->id,
            'quantity' => 1,
        ])->assertRedirect();

        $this->get("/s/{$slug}/checkout?phone=254711999000")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/checkout')
                ->where('digitalOnly', true)
                ->where('hasDigitalItems', true)
                ->where('company.termsUrl', "/s/{$slug}/terms")
            );

        $this->postJson("/s/{$slug}/checkout/quote", [
            'fulfillmentType' => 'delivery',
            'deliveryAddress' => 'Should be ignored',
        ])->assertOk()
            ->assertJsonPath('deliveryFee', 0);

        $this->from("/s/{$slug}/checkout?phone=254711999000")
            ->post("/s/{$slug}/checkout?phone=254711999000", [
                'customerName' => 'Digital Buyer',
                'customerPhone' => '254711999000',
                'customerEmail' => 'buyer@example.com',
                'fulfillmentType' => 'delivery',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('acceptTerms');

        $this->from("/s/{$slug}/checkout?phone=254711999000")
            ->post("/s/{$slug}/checkout?phone=254711999000", [
                'customerName' => 'Digital Buyer',
                'customerPhone' => '254711999000',
                'fulfillmentType' => 'digital',
                'acceptTerms' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('customerEmail');

        $this->post("/s/{$slug}/checkout?phone=254711999000", [
            'customerName' => 'Digital Buyer',
            'customerPhone' => '254711999000',
            'customerEmail' => 'buyer@example.com',
            'fulfillmentType' => 'delivery',
            'acceptTerms' => true,
            'marketingConsent' => true,
        ])->assertRedirect();

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('digital', $order->fulfillment_type);
        $this->assertSame('buyer@example.com', $order->customer_email);
        $this->assertNull($order->delivery_address);
        $this->assertEquals(0.0, (float) $order->delivery_fee);

        $customer = StorefrontCustomer::where('company_id', $company->id)
            ->where('email', 'buyer@example.com')
            ->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->terms_accepted_at);
        $this->assertTrue($customer->marketing_consent);
    }

    public function test_mixed_cart_keeps_physical_fulfillment_and_flags_digital_items(): void
    {
        [$company, $ebook, $mug] = $this->seedDigitalStore();
        $slug = $company->store_slug;

        $this->post("/s/{$slug}/cart", ['productId' => $ebook->id, 'quantity' => 1]);
        $this->post("/s/{$slug}/cart", ['productId' => $mug->id, 'quantity' => 1]);

        $this->get("/s/{$slug}/checkout?phone=254711555000")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('store/checkout')
                ->where('digitalOnly', false)
                ->where('hasDigitalItems', true)
            );

        $this->from("/s/{$slug}/checkout?phone=254711555000")
            ->post("/s/{$slug}/checkout?phone=254711555000", [
                'customerName' => 'Mixed Buyer',
                'customerPhone' => '254711555000',
                'fulfillmentType' => 'delivery',
                'acceptTerms' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('customerEmail');

        $this->post("/s/{$slug}/checkout?phone=254711555000", [
            'customerName' => 'Mixed Buyer',
            'customerPhone' => '254711555000',
            'customerEmail' => 'mixed@example.com',
            'fulfillmentType' => 'delivery',
            'deliveryAddress' => 'Westlands Nairobi',
            'acceptTerms' => true,
        ])->assertRedirect();

        $order = Order::where('company_id', $company->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('delivery', $order->fulfillment_type);
        $this->assertSame('Westlands Nairobi', $order->delivery_address);
        $this->assertGreaterThan(0, (float) $order->delivery_fee);
    }
}
