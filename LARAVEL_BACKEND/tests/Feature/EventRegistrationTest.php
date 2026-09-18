<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $email = 'events@test.local'): array
    {
        $company = Company::create([
            'name' => 'Heal to Lead',
            'email' => $email,
            'status' => 'active',
            'store_slug' => 'heal-to-lead-'.substr(md5($email), 0, 6),
            'storefront_enabled' => true,
        ]);
        CompanySetting::create([
            'company_id' => $company->id,
            'enable_events' => true,
            'display_currency' => 'KES',
            'order_payment_manual_instructions' => "Bank: Equity\nAcc: 0123456789",
        ]);
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

    /**
     * @return array{event: Event, sessionId: int, ticketId: int}
     */
    private function seedPublishedEvent(Company $company, array $overrides = []): array
    {
        $events = app(EventService::class);
        $event = $events->createEvent($company, [
            'title' => $overrides['title'] ?? 'Heal to Lead Bootcamp',
            'visibility' => Event::VISIBILITY_UNLISTED,
            'format' => Event::FORMAT_IN_PERSON,
        ]);
        $session = $events->createSession($event, [
            'title' => '1st Session',
            'startsAt' => '2026-10-25 19:30:00',
            'endsAt' => '2026-10-25 21:30:00',
            'capacity' => $overrides['capacity'] ?? 20,
        ]);
        $ticket = $events->createTicketType($event, [
            'name' => 'Session registration',
            'price' => $overrides['price'] ?? 1000,
            'sessionId' => $session->id,
            'quantityTotal' => $overrides['quantityTotal'] ?? null,
        ]);
        $events->publish($event->fresh(['sessions', 'ticketTypes']));

        return [
            'event' => $event->fresh(['sessions', 'ticketTypes', 'company']),
            'sessionId' => $session->id,
            'ticketId' => $ticket->id,
        ];
    }

    public function test_company_can_create_and_list_events(): void
    {
        [$company, $user] = $this->companyUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/company/events', [
            'title' => 'Heal to Lead',
            'session' => [
                'title' => '1st Session',
                'startsAt' => '2026-10-25T19:30',
                'endsAt' => '2026-10-25T21:30',
                'capacity' => 40,
            ],
            'ticket' => [
                'name' => 'General',
                'price' => 1000,
            ],
        ])->assertCreated()->assertJsonPath('event.title', 'Heal to Lead');

        $this->postJson('/api/company/events/'.Event::where('company_id', $company->id)->value('id').'/publish')
            ->assertOk()
            ->assertJsonPath('event.status', 'published');

        $this->getJson('/api/company/events')
            ->assertOk()
            ->assertJsonPath('events.0.title', 'Heal to Lead');
    }

    public function test_events_are_isolated_by_company(): void
    {
        [, $userA] = $this->companyUser('a-events@test.local');
        [$companyB, $userB] = $this->companyUser('b-events@test.local');
        $seeded = $this->seedPublishedEvent($companyB);

        Sanctum::actingAs($userA);
        $this->getJson('/api/company/events/'.$seeded['event']->id)
            ->assertForbidden();
        $this->getJson('/api/company/events')
            ->assertOk()
            ->assertJsonCount(0, 'events');
    }

    public function test_public_event_page_shows_payment_details(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company);

        $this->get('/s/'.$company->store_slug.'/e/'.$seeded['event']->slug)
            ->assertOk()
            ->assertSee('Heal to Lead Bootcamp', false)
            ->assertSee('paymentDetails', false)
            ->assertSee('Equity', false);
    }

    public function test_public_registration_creates_pending_order_and_hold(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company, ['capacity' => 2]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
            ->post('/s/'.$company->store_slug.'/e/'.$seeded['event']->slug.'/register', [
                'sessionId' => $seeded['sessionId'],
                'ticketTypeId' => $seeded['ticketId'],
                'quantity' => 1,
                'buyerName' => 'Jostinah',
                'buyerPhone' => '254700111222',
                'buyerEmail' => 'jostinah@example.com',
            ])->assertRedirect();

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $seeded['event']->id,
            'buyer_name' => 'Jostinah',
            'status' => EventRegistration::STATUS_PENDING_PAYMENT,
        ]);
        $this->assertDatabaseHas('orders', [
            'company_id' => $company->id,
            'source' => 'event',
            'payment_status' => 'pending',
            'total' => 1000,
        ]);
    }

    public function test_capacity_hold_blocks_oversell(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company, ['capacity' => 1]);
        $registrations = app(EventRegistrationService::class);

        $registrations->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'First',
            'buyerPhone' => '254700000001',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $registrations->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'Second',
            'buyerPhone' => '254700000002',
        ]);
    }

    public function test_expired_hold_releases_capacity(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company, ['capacity' => 1]);
        $registrations = app(EventRegistrationService::class);

        $first = $registrations->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'Stale hold',
            'buyerPhone' => '254700000003',
        ]);
        $first['registration']->update(['hold_expires_at' => now()->subHour()]);

        $second = $registrations->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'New buyer',
            'buyerPhone' => '254700000004',
        ]);

        $this->assertSame(EventRegistration::STATUS_PENDING_PAYMENT, $second['registration']->status);
        $this->assertSame('New buyer', $second['registration']->buyer_name);
    }

    public function test_mark_order_paid_issues_ticket(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company);
        $result = app(EventRegistrationService::class)->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'Paid Guest',
            'buyerEmail' => 'guest@example.com',
            'buyerPhone' => '254700000005',
        ]);

        app(OrderPaymentService::class)->markOrderPaid($result['order'], 1000, 'manual');

        $this->assertSame('confirmed', $result['registration']->fresh()->status);
        $this->assertDatabaseHas('event_attendees', [
            'event_registration_id' => $result['registration']->id,
            'name' => 'Paid Guest',
            'status' => EventAttendee::STATUS_VALID,
        ]);

        $code = EventAttendee::where('event_registration_id', $result['registration']->id)->value('ticket_code');
        $this->get('/ticket/'.$code)->assertOk()->assertSee('Paid Guest', false);
    }

    public function test_free_registration_issues_ticket_immediately(): void
    {
        [$company] = $this->companyUser();
        $seeded = $this->seedPublishedEvent($company, ['price' => 0]);
        $result = app(EventRegistrationService::class)->register($company, $seeded['event'], [
            'sessionId' => $seeded['sessionId'],
            'ticketTypeId' => $seeded['ticketId'],
            'buyerName' => 'Free Guest',
            'buyerEmail' => 'free@example.com',
        ]);

        $this->assertTrue($result['free']);
        $this->assertSame(EventRegistration::STATUS_CONFIRMED, $result['registration']->status);
        $this->assertNotEmpty($result['registration']->attendees);
    }

    public function test_share_event_link_tool_returns_registration_url(): void
    {
        [$company] = $this->companyUser();
        $this->seedPublishedEvent($company);

        $tool = app(\App\Services\Agent\Tools\ShareEventLinkTool::class);
        $chat = \App\Models\Chat::create([
            'company_id' => $company->id,
            'customer_name' => 'Steve',
            'customer_phone' => '254700999888',
        ]);
        $context = new \App\Services\Agent\AgentToolContext(
            company: $company->fresh(['settings']),
            chat: $chat,
            customerPhone: '254700999888',
            customerName: 'Steve',
            incomingMessage: 'I need a registration link',
        );
        $out = $tool->execute($context, ['query' => 'Heal']);
        $this->assertTrue($out['success']);
        $this->assertNotEmpty($out['events']);
        $this->assertStringContainsString('/e/', $out['events'][0]['url']);
        $this->assertNotEmpty($out['payment_details']);
    }
}
