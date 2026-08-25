<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAvailability;
use App\Models\BookingSetting;
use App\Models\Chat;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DineInTable;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Agent\AgentToolContext;
use App\Services\Agent\Tools\CheckCalendarAvailabilityTool;
use App\Services\Agent\Tools\CreateBookingTool;
use App\Services\OrderFlowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessOperatingModesAndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User}
     */
    private function setupCompany(string $plan = 'growth'): array
    {
        $company = Company::create([
            'name' => 'Salon & Bistro Co',
            'email' => 'business@test.local',
            'whatsapp_number' => '+254700000000',
            'status' => 'active',
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'whatsapp_number' => '+254700000000',
            'business_mode' => 'hybrid',
            'enable_products_catalog' => true,
            'enable_bookings' => true,
            'enable_dine_in' => true,
            'dine_in_qr_target' => 'whatsapp_chat',
            'dine_in_payment_timing' => 'open_tab',
        ]);
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

    public function test_settings_api_updates_business_mode_and_capabilities(): void
    {
        [$company, $user] = $this->setupCompany();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/company/settings', [
            'businessMode' => 'services',
            'enableProductsCatalog' => true,
            'enableBookings' => true,
            'enableDineIn' => false,
            'dineInQrTarget' => 'whatsapp_chat',
            'dineInPaymentTiming' => 'open_tab',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'business_mode' => 'services',
            'enable_bookings' => true,
            'enable_dine_in' => false,
            'dine_in_qr_target' => 'whatsapp_chat',
            'dine_in_payment_timing' => 'open_tab',
        ]);

        $getRes = $this->getJson('/api/company/settings');
        $getRes->assertOk()
            ->assertJsonPath('businessMode', 'services')
            ->assertJsonPath('enableBookings', true)
            ->assertJsonPath('enableDineIn', false)
            ->assertJsonPath('dineInQrTarget', 'whatsapp_chat')
            ->assertJsonPath('dineInPaymentTiming', 'open_tab');
    }

    public function test_booking_settings_api_updates_payment_requirement_and_whatsapp_mode(): void
    {
        [$company, $user] = $this->setupCompany();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/company/bookings/settings', [
            'timezone' => 'Africa/Nairobi',
            'paymentRequirement' => 'required',
            'whatsappBookingMode' => 'whatsapp_native',
            'defaultDurationMinutes' => 45,
            'isEnabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('settings.paymentRequirement', 'required')
            ->assertJsonPath('settings.whatsappBookingMode', 'whatsapp_native')
            ->assertJsonPath('settings.defaultDurationMinutes', 45);

        $this->assertDatabaseHas('booking_settings', [
            'company_id' => $company->id,
            'payment_requirement' => 'required',
            'whatsapp_booking_mode' => 'whatsapp_native',
        ]);
    }

    public function test_create_booking_tool_reserves_slot_conversationally(): void
    {
        [$company] = $this->setupCompany();

        BookingSetting::create([
            'company_id' => $company->id,
            'timezone' => 'UTC',
            'default_duration_minutes' => 30,
            'buffer_minutes' => 0,
            'min_notice_minutes' => 0,
            'max_days_ahead' => 30,
            'public_slug' => 'salon-test',
            'calendar_feed_token' => 'feedtoken123456789012345678901234567890',
            'is_enabled' => true,
            'payment_requirement' => 'at_venue',
            'whatsapp_booking_mode' => 'hybrid',
        ]);

        // Availability on all days
        for ($day = 0; $day <= 6; $day++) {
            BookingAvailability::create([
                'company_id' => $company->id,
                'weekday' => $day,
                'start_time' => '09:00',
                'end_time' => '18:00',
            ]);
        }

        $service = Product::create([
            'company_id' => $company->id,
            'name' => 'Haircut & Styling',
            'price' => 50.00,
            'product_type' => 'service',
            'bookable' => true,
            'booking_duration_minutes' => 30,
            'status' => 'active',
        ]);

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Alice Doe',
            'customer_phone' => '+254711223344',
            'channel' => 'whatsapp',
        ]);

        $context = new AgentToolContext(
            company: $company,
            chat: $chat,
            customerPhone: '+254711223344',
            customerName: 'Alice Doe',
            incomingMessage: 'I want to book an appointment for tomorrow at 10:00'
        );

        $targetDate = now()->addDay()->setTime(10, 0, 0);

        /** @var CreateBookingTool $tool */
        $tool = app(CreateBookingTool::class);
        $result = $tool->execute($context, [
            'starts_at' => $targetDate->format('Y-m-d H:i'),
            'product_id' => $service->id,
            'notes' => 'Prefers quiet session',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('Alice Doe', $result['customer_name']);
        $this->assertEquals('Haircut & Styling', $result['service']);
        $this->assertNotEmpty($result['google_calendar_url']);
        $this->assertNotEmpty($result['calendar_download_url']);

        $this->assertDatabaseHas('bookings', [
            'company_id' => $company->id,
            'product_id' => $service->id,
            'customer_name' => 'Alice Doe',
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    public function test_calendar_availability_tool_returns_booking_slots(): void
    {
        [$company] = $this->setupCompany();

        BookingSetting::create([
            'company_id' => $company->id,
            'timezone' => 'UTC',
            'default_duration_minutes' => 60,
            'public_slug' => 'slots-test',
            'calendar_feed_token' => 'feedtoken123456789012345678901234567891',
            'is_enabled' => true,
        ]);

        for ($day = 0; $day <= 6; $day++) {
            BookingAvailability::create([
                'company_id' => $company->id,
                'weekday' => $day,
                'start_time' => '10:00',
                'end_time' => '12:00',
            ]);
        }

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Bob',
            'customer_phone' => '+254700000001',
            'channel' => 'whatsapp',
        ]);

        $context = new AgentToolContext(
            company: $company,
            chat: $chat,
            customerPhone: '+254700000001',
            customerName: 'Bob',
            incomingMessage: 'What slots do you have?'
        );

        /** @var CheckCalendarAvailabilityTool $tool */
        $tool = app(CheckCalendarAvailabilityTool::class);
        $result = $tool->execute($context, ['days_ahead' => 3]);

        $this->assertArrayHasKey('slots', $result);
        $this->assertNotEmpty($result['slots']);
        $this->assertArrayHasKey('publicBookingUrl', $result);
    }

    public function test_dine_in_table_whatsapp_resolution_and_order_flow(): void
    {
        [$company] = $this->setupCompany();

        $table = DineInTable::create([
            'company_id' => $company->id,
            'name' => 'Table 4',
            'code' => 'T4',
            'seats' => 4,
            'is_active' => true,
        ]);

        $this->assertNotNull($table->whatsappOrderUrl());
        $this->assertStringContainsString('T4', $table->whatsappOrderUrl());

        $chat = Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Diner Charlie',
            'customer_phone' => '+254722334455',
            'channel' => 'whatsapp',
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Gourmet Burger',
            'price' => 15.00,
            'product_type' => 'physical',
            'status' => 'active',
        ]);

        /** @var OrderFlowService $orderFlow */
        $orderFlow = app(OrderFlowService::class);

        // Customer adds item
        $orderFlow->processMessage($chat, $company, '1 Gourmet Burger', 'Diner Charlie', '+254722334455');

        // Customer specifies table dine-in
        $reply = $orderFlow->processMessage($chat, $company, 'dine in Table 4 (Ref: T4)', 'Diner Charlie', '+254722334455');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Dine-in selected (Table 4)', $reply);

        $draft = (array) ($chat->fresh()->order_draft ?? []);
        $this->assertEquals('dine_in', $draft['fulfillment_type'] ?? null);
        $this->assertEquals($table->id, $draft['dine_in_table_id'] ?? null);
        $this->assertEquals('Table 4', $draft['dine_in_table_name'] ?? null);
    }
}
