<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingSetting;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DigitalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DownloadLimitsAndBookingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $plan = 'professional'): array
    {
        $company = Company::create([
            'name' => 'Limits Co',
            'email' => 'limits@test.local',
            'status' => 'active',
        ]);
        CompanySetting::create(['company_id' => $company->id]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => $plan,
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

    public function test_download_limit_blocks_after_max_uses(): void
    {
        Storage::fake('local');
        [$company] = $this->companyUser();

        $path = 'orders/1/digital/1/file.pdf';
        Storage::disk('local')->put($path, 'PDF');

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-DL-1',
            'customer_name' => 'Buyer',
            'customer_phone' => '254700000001',
            'total' => 10,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'name' => 'PDF Pack',
            'quantity' => 1,
            'price' => 10,
            'download_count' => 0,
            'fulfillment_data' => [
                'productType' => 'digital',
                'digitalFilePath' => $path,
                'digitalFileName' => 'file.pdf',
                'digitalFileMime' => 'application/pdf',
                'maxDownloads' => 2,
            ],
        ]);

        $realPath = 'orders/'.$order->id.'/digital/'.$line->id.'/file.pdf';
        Storage::disk('local')->put($realPath, 'PDF');
        $line->update([
            'fulfillment_data' => array_merge($line->fulfillment_data, [
                'digitalFilePath' => $realPath,
            ]),
        ]);

        $url = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);

        $this->get($url)->assertOk();
        $this->assertSame(1, $line->fresh()->download_count);

        $url2 = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);
        $this->get($url2)->assertOk();
        $this->assertSame(2, $line->fresh()->download_count);

        $url3 = URL::temporarySignedRoute('orders.digital-download', now()->addDay(), [
            'order' => $order->id,
            'orderProduct' => $line->id,
        ]);
        $this->get($url3)->assertForbidden();
    }

    public function test_booking_slots_and_create_flow(): void
    {
        [$company, $user] = $this->companyUser('professional');
        Sanctum::actingAs($user);

        $settings = app(BookingService::class)->ensureSettings($company);
        BookingAvailability::create([
            'company_id' => $company->id,
            'weekday' => now()->addDay()->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Strategy Call',
            'price' => 0,
            'category' => 'Services',
            'product_type' => 'service',
            'fulfillment_type' => 'booking',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'bookable' => true,
            'booking_duration_minutes' => 30,
            'stock' => 0,
            'status' => 'active',
        ]);

        $this->getJson('/api/company/bookings/settings')
            ->assertOk()
            ->assertJsonPath('settings.publicSlug', $settings->public_slug);

        $from = now()->addDay()->startOfDay();
        $to = now()->addDay()->endOfDay();
        $slots = app(BookingService::class)->availableSlots($company, $product, $from, $to);
        $this->assertNotEmpty($slots);

        $booking = app(BookingService::class)->createBooking($company, [
            'startsAt' => $slots[0]['start'],
            'customerName' => 'Alex',
            'customerEmail' => 'alex@example.com',
        ], $product);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
            'customer_name' => 'Alex',
        ]);

        $this->get('/book/'.$settings->public_slug)
            ->assertOk();

        $this->get('/book/'.$settings->public_slug.'/calendar.ics?token='.$settings->calendar_feed_token)
            ->assertOk();

        $remaining = app(BookingService::class)->availableSlots($company, $product, $from, $to);
        $this->assertNotEmpty($remaining, 'Expected at least one free slot after first booking');

        $this->post('/book/'.$settings->public_slug, [
            'startsAt' => $remaining[0]['start'],
            'productId' => $product->id,
            'customerName' => 'Public Booker',
            'customerEmail' => 'public@example.com',
            'customerPhone' => '254700111222',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'company_id' => $company->id,
            'customer_name' => 'Public Booker',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_starter_plan_blocks_bookings_api(): void
    {
        [, $user] = $this->companyUser('starter');
        Sanctum::actingAs($user);

        $this->getJson('/api/company/bookings/settings')
            ->assertStatus(403)
            ->assertJsonPath('code', 'bookings_required');
    }

    public function test_prepare_paid_order_snapshots_max_downloads(): void
    {
        Storage::fake('local');
        [$company] = $this->companyUser();
        $path = 'products/'.$company->id.'/digital/guide.pdf';
        Storage::disk('local')->put($path, 'BYTES');

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Guide',
            'price' => 5,
            'category' => 'Digital',
            'product_type' => 'digital',
            'fulfillment_type' => 'download',
            'track_inventory' => false,
            'requires_delivery_address' => false,
            'digital_file_path' => $path,
            'digital_file_name' => 'guide.pdf',
            'digital_file_mime' => 'application/pdf',
            'digital_file_size' => 5,
            'max_downloads' => 3,
            'access_expires_days' => 7,
            'license_key_mode' => 'none',
            'stock' => 0,
            'status' => 'active',
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-MAX-1',
            'customer_name' => 'Pat',
            'customer_phone' => '254700000002',
            'total' => 5,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $line = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => 1,
            'price' => 5,
            'fulfillment_data' => $product->fulfillmentSnapshot(),
        ]);

        app(DigitalAccessService::class)->preparePaidOrder($order->fresh(['orderProducts.product', 'company']));
        $line->refresh();
        $this->assertSame(3, $line->fulfillment_data['maxDownloads'] ?? null);
        $this->assertNotEmpty($line->fulfillment_data['accessExpiresAt'] ?? null);
    }
}
