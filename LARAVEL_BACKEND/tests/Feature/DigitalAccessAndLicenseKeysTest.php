<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductLicenseKey;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DigitalAccessService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DigitalAccessAndLicenseKeysTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $name = 'Digital Co', string $email = 'digital@test.local'): array
    {
        $company = Company::create([
            'name' => $name,
            'email' => $email,
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
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

        return [$company, $user];
    }

    public function test_auto_license_keys_and_signed_download_after_payment(): void
    {
        Storage::fake('local');

        $company = Company::create([
            'name' => 'Digital Co',
            'email' => 'digital@test.local',
            'status' => 'active',
        ]);

        $path = 'products/'.$company->id.'/digital/guide.pdf';
        Storage::disk('local')->put($path, 'PDF-BYTES');

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Growth PDF',
            'price' => 19,
            'category' => 'Books',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'digital_file_path' => $path,
            'digital_file_name' => 'guide.pdf',
            'digital_file_mime' => 'application/pdf',
            'digital_file_size' => 9,
            'license_key_mode' => 'auto',
            'license_key_prefix' => 'GROW',
            'access_expires_days' => 14,
            'stock' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-DIG-1',
            'customer_name' => 'Pat Buyer',
            'customer_phone' => '254700000001',
            'total' => 19,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'price' => 19,
            'fulfillment_data' => $product->fulfillmentSnapshot(),
        ]);

        app(OrderPaymentService::class)->markOrderPaid($order->fresh());

        $line->refresh();
        $data = $line->fulfillment_data;
        $this->assertSame('digital', $data['productType'] ?? null);
        $this->assertNotEmpty($data['licenseKeys'] ?? []);
        $this->assertStringStartsWith('GROW-', $data['licenseKeys'][0]);
        $this->assertNotEmpty($data['digitalFileUrl'] ?? null);
        $this->assertNotEmpty($data['accessExpiresAt'] ?? null);
        $this->assertStringStartsWith('orders/'.$order->id.'/digital/', $data['digitalFilePath'] ?? '');
        $this->assertArrayNotHasKey('digitalFileAbsolutePath', $data);
        $this->assertTrue(Storage::disk('local')->exists($data['digitalFilePath']));

        $this->assertDatabaseHas('product_license_keys', [
            'product_id' => $product->id,
            'status' => ProductLicenseKey::STATUS_ASSIGNED,
            'order_product_id' => $line->id,
        ]);

        $downloadUrl = $data['digitalFileUrl'];
        $this->get($downloadUrl)->assertOk();

        $portalUrl = app(DigitalAccessService::class)->signedAccessPortalUrl($order->fresh());
        $this->get($portalUrl)
            ->assertOk()
            ->assertSee('Growth PDF')
            ->assertSee($data['licenseKeys'][0], false);

        $this->get($order->fresh()->publicReceiptUrl())
            ->assertOk()
            ->assertSee($data['licenseKeys'][0], false);
    }

    public function test_prepare_hydrates_from_product_id_when_fulfillment_missing(): void
    {
        Storage::fake('local');

        $company = Company::create([
            'name' => 'Hydrate Co',
            'email' => 'hydrate@test.local',
            'status' => 'active',
        ]);

        $path = 'products/'.$company->id.'/digital/notes.pdf';
        Storage::disk('local')->put($path, 'NOTES');

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Course Notes',
            'price' => 12,
            'category' => 'Courses',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'digital_file_path' => $path,
            'digital_file_name' => 'notes.pdf',
            'digital_file_mime' => 'application/pdf',
            'digital_file_size' => 5,
            'license_key_mode' => 'auto',
            'license_key_prefix' => 'NOTE',
            'stock' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-HYD-1',
            'customer_name' => 'Hydra',
            'customer_phone' => '254700000099',
            'total' => 12,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'price' => 12,
            'fulfillment_data' => null,
        ]);

        app(DigitalAccessService::class)->preparePaidOrder($order->fresh(['orderProducts.product']));

        $line->refresh();
        $this->assertSame('digital', $line->fulfillment_data['productType'] ?? null);
        $this->assertNotEmpty($line->fulfillment_data['licenseKeys'] ?? []);
        $this->assertNotEmpty($line->fulfillment_data['digitalFileUrl'] ?? null);
    }

    public function test_chat_order_create_attaches_product_fulfillment(): void
    {
        [$company, $user] = $this->companyUser('Chat Co', 'chat@test.local');
        Sanctum::actingAs($user);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111000',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Private Course',
            'price' => 40,
            'category' => 'Courses',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'access_url' => 'https://example.com/course',
            'license_key_mode' => 'none',
            'stock' => 0,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/company/orders', [
            'chatId' => $chat->id,
            'items' => [[
                'productId' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'price' => 40,
            ]],
            'sendWhatsApp' => false,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $orderId = $response->json('order.id') ?? Order::latest('id')->value('id');
        $line = OrderProduct::where('order_id', $orderId)->first();
        $this->assertNotNull($line);
        $this->assertSame($product->id, (int) $line->product_id);
        $this->assertSame('digital', $line->fulfillment_data['productType'] ?? null);
        $this->assertSame('https://example.com/course', $line->fulfillment_data['accessUrl'] ?? null);
    }

    public function test_pool_license_keys_are_assigned_from_inventory(): void
    {
        $company = Company::create([
            'name' => 'Pool Co',
            'email' => 'pool@test.local',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Course Seat',
            'price' => 49,
            'category' => 'Courses',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'access_url' => 'https://example.com/course',
            'license_key_mode' => 'pool',
            'license_key_prefix' => 'SEAT',
            'stock' => 0,
            'status' => 'active',
        ]);

        ProductLicenseKey::create([
            'product_id' => $product->id,
            'license_key' => 'SEAT-POOL-001',
            'status' => ProductLicenseKey::STATUS_AVAILABLE,
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-POOL-1',
            'customer_name' => 'Lee Buyer',
            'customer_phone' => '254700000002',
            'total' => 49,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'price' => 49,
            'fulfillment_data' => $product->fulfillmentSnapshot(),
        ]);

        app(DigitalAccessService::class)->preparePaidOrder($order->fresh(['orderProducts']));

        $line = $order->fresh()->orderProducts->first();
        $this->assertSame(['SEAT-POOL-001'], $line->fulfillment_data['licenseKeys'] ?? null);
        $this->assertDatabaseHas('product_license_keys', [
            'license_key' => 'SEAT-POOL-001',
            'status' => ProductLicenseKey::STATUS_ASSIGNED,
        ]);
    }

    public function test_pool_shortfall_does_not_auto_mint_keys(): void
    {
        $company = Company::create([
            'name' => 'Short Pool',
            'email' => 'short@test.local',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Seat',
            'price' => 10,
            'category' => 'Courses',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'license_key_mode' => 'pool',
            'stock' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-SHORT-1',
            'customer_name' => 'Short',
            'customer_phone' => '254700000044',
            'total' => 10,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 2,
            'price' => 10,
            'fulfillment_data' => $product->fulfillmentSnapshot(),
        ]);

        app(DigitalAccessService::class)->preparePaidOrder($order->fresh(['orderProducts.product']));
        $line->refresh();

        $this->assertSame([], $line->fulfillment_data['licenseKeys'] ?? []);
        $this->assertSame(0, ProductLicenseKey::query()->where('product_id', $product->id)->count());
    }

    public function test_chat_order_rejects_pool_without_keys(): void
    {
        [$company, $user] = $this->companyUser('Reject Pool', 'reject@test.local');
        Sanctum::actingAs($user);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Buyer',
            'customer_phone' => '254700111001',
            'status' => 'active',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Gated Course',
            'price' => 40,
            'category' => 'Courses',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'license_key_mode' => 'pool',
            'stock' => 0,
            'status' => 'active',
        ]);

        $this->postJson('/api/company/orders', [
            'chatId' => $chat->id,
            'items' => [[
                'productId' => $product->id,
                'name' => $product->name,
                'quantity' => 1,
                'price' => 40,
            ]],
            'sendWhatsApp' => false,
        ])->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertSame(0, Order::count());
    }

    public function test_expired_download_and_portal_return_410(): void
    {
        Storage::fake('local');

        $company = Company::create([
            'name' => 'Expire Co',
            'email' => 'expire@test.local',
            'status' => 'active',
        ]);

        $path = 'orders/1/digital/1/old.pdf';
        Storage::disk('local')->put($path, 'OLD');

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-EXP-1',
            'customer_name' => 'Exp',
            'customer_phone' => '254700000010',
            'total' => 10,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Expired PDF',
            'quantity' => 1,
            'price' => 10,
            'fulfillment_data' => [
                'productType' => 'digital',
                'fulfillmentType' => 'download',
                'digitalFilePath' => $path,
                'digitalFileName' => 'old.pdf',
                'digitalFileMime' => 'application/pdf',
                'digitalFileUrl' => 'https://example.com/x',
                'licenseKeys' => ['SECRET-KEY'],
                'accessUrl' => 'https://example.com/course',
                'accessExpiresAt' => now()->subDay()->toIso8601String(),
            ],
        ]);

        // Fix path to match real order id after create.
        $realPath = 'orders/'.$order->id.'/digital/'.$line->id.'/old.pdf';
        Storage::disk('local')->put($realPath, 'OLD');
        $line->update([
            'fulfillment_data' => array_merge($line->fulfillment_data, [
                'digitalFilePath' => $realPath,
            ]),
        ]);

        $download = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);
        $this->get($download)->assertStatus(410);

        $portal = URL::temporarySignedRoute('orders.access', now()->addDay(), [
            'order' => $order->id,
        ]);
        $this->get($portal)->assertStatus(410);

        $this->get($order->publicReceiptUrl())
            ->assertOk()
            ->assertSee('Access for this item has expired', false)
            ->assertDontSee('SECRET-KEY', false)
            ->assertDontSee('https://example.com/course', false);
    }

    public function test_path_injection_is_rejected_on_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secrets/passwd.txt', 'nope');

        $company = Company::create([
            'name' => 'Secure Co',
            'email' => 'secure@test.local',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-SEC-2',
            'customer_name' => 'Sam',
            'customer_phone' => '254700000003',
            'total' => 10,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Bad Path',
            'quantity' => 1,
            'price' => 10,
            'fulfillment_data' => [
                'productType' => 'digital',
                'digitalFilePath' => 'secrets/passwd.txt',
            ],
        ]);

        $signed = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);
        $this->get($signed)->assertNotFound();
    }

    public function test_unsigned_download_and_portal_are_rejected(): void
    {
        $company = Company::create([
            'name' => 'Secure Co',
            'email' => 'secure2@test.local',
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-SEC-1',
            'customer_name' => 'Sam',
            'customer_phone' => '254700000003',
            'total' => 10,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'Secret PDF',
            'quantity' => 1,
            'price' => 10,
            'fulfillment_data' => [
                'productType' => 'digital',
                'digitalFilePath' => 'missing.pdf',
            ],
        ]);

        $this->get(route('orders.digital-download', [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]))->assertForbidden();

        $this->get(route('orders.access', [
            'order' => $order->id,
        ]))->assertForbidden();

        $signed = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);
        $this->get($signed)->assertNotFound();
    }

    public function test_clearing_access_expiry_and_urls_via_api(): void
    {
        [$company, $user] = $this->companyUser('Clear Co', 'clear@test.local');
        Sanctum::actingAs($user);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Clearable',
            'price' => 5,
            'category' => 'Digital',
            'product_type' => 'digital',
            'fulfillment_type' => 'link',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'access_url' => 'https://example.com/old',
            'service_booking_url' => 'https://example.com/book',
            'fulfillment_instructions' => 'Old instructions',
            'access_expires_days' => 30,
            'license_key_mode' => 'none',
            'stock' => 0,
            'status' => 'active',
        ]);

        $this->putJson('/api/company/products/'.$product->id, [
            'accessUrl' => '',
            'serviceBookingUrl' => '',
            'fulfillmentInstructions' => '',
            'accessExpiresDays' => null,
        ])->assertOk();

        $product->refresh();
        $this->assertNull($product->access_url);
        $this->assertNull($product->service_booking_url);
        $this->assertNull($product->fulfillment_instructions);
        $this->assertNull($product->access_expires_days);
    }

    public function test_paid_download_survives_product_file_delete(): void
    {
        Storage::fake('local');

        $company = Company::create([
            'name' => 'Copy Co',
            'email' => 'copy@test.local',
            'status' => 'active',
        ]);

        $path = 'products/'.$company->id.'/digital/guide.pdf';
        Storage::disk('local')->put($path, 'PDF-BYTES');

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Keep File',
            'price' => 9,
            'category' => 'Books',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'digital_file_path' => $path,
            'digital_file_name' => 'guide.pdf',
            'digital_file_mime' => 'application/pdf',
            'digital_file_size' => 9,
            'license_key_mode' => 'none',
            'stock' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-COPY-1',
            'customer_name' => 'Copy',
            'customer_phone' => '254700000077',
            'total' => 9,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'price' => 9,
            'fulfillment_data' => $product->fulfillmentSnapshot(),
        ]);

        app(DigitalAccessService::class)->preparePaidOrder($order->fresh(['orderProducts.product']));
        $line->refresh();
        $orderPath = $line->fulfillment_data['digitalFilePath'];
        $this->assertTrue(Storage::disk('local')->exists($orderPath));

        Storage::disk('local')->delete($path);
        $product->update([
            'digital_file_path' => null,
            'digital_file_name' => null,
            'digital_file_mime' => null,
            'digital_file_size' => null,
        ]);

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        $this->get($line->fulfillment_data['digitalFileUrl'])->assertOk();
    }
}
