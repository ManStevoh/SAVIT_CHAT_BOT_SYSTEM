<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyNotification;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionReminderLog;
use App\Models\User;
use App\Services\SubscriptionLifecycleService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionOffersAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_paystack_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        Mail::fake();

        PaymentGateway::query()->where('slug', 'paystack')->delete();
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => 'pk_test_x',
                'secret_key' => $this->secret,
                'currency' => 'kes',
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');
    }

    /**
     * @return array{company: Company, owner: User, plan: Plan, admin: User}
     */
    private function setupActors(): array
    {
        $company = Company::create([
            'name' => 'Offer Co',
            'email' => 'billing@offer-co.test',
            'status' => 'active',
        ]);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'trial',
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(12),
            'amount' => 0,
            'billing_cycle' => 'monthly',
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $plan = Plan::where('slug', 'professional')->firstOrFail();

        return compact('company', 'owner', 'plan', 'admin');
    }

    public function test_admin_can_crud_subscription_offers(): void
    {
        ['admin' => $admin, 'plan' => $plan] = $this->setupActors();
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Spring Sale',
            'code' => 'spring20',
            'discountType' => 'percent',
            'discountValue' => 20,
            'planId' => $plan->id,
            'maxPerCompany' => 1,
            'isActive' => true,
        ])->assertCreated()->json('offer');

        $this->assertSame('SPRING20', $create['code']);
        $this->assertTrue($create['isCurrentlyValid']);

        $this->getJson('/api/admin/subscription-offers')
            ->assertOk()
            ->assertJsonFragment(['code' => 'SPRING20']);

        $this->putJson('/api/admin/subscription-offers/'.$create['id'], [
            'name' => 'Spring Sale Updated',
            'code' => 'SPRING20',
            'discountType' => 'percent',
            'discountValue' => 25,
            'isActive' => true,
        ])->assertOk()->assertJsonPath('offer.discountValue', 25);

        $this->deleteJson('/api/admin/subscription-offers/'.$create['id'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('subscription_offers', ['id' => $create['id']]);
    }

    public function test_company_can_preview_and_checkout_with_coupon(): void
    {
        ['company' => $company, 'owner' => $owner, 'plan' => $plan, 'admin' => $admin] = $this->setupActors();

        Sanctum::actingAs($admin);
        $offer = $this->postJson('/api/admin/subscription-offers', [
            'name' => 'Save 10',
            'code' => 'SAVE10',
            'discountType' => 'percent',
            'discountValue' => 10,
            'isActive' => true,
        ])->assertCreated()->json('offer');

        Sanctum::actingAs($owner);

        $original = (float) $plan->price_amount;
        $expectedFinal = round($original * 0.9, 2);

        $this->postJson('/api/company/coupon/preview', [
            'planId' => (string) $plan->id,
            'couponCode' => 'save10',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('finalAmount', $expectedFinal);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'access',
                    'reference' => 'essem_sub_'.$company->id.'_coupon',
                ],
            ], 200),
        ]);

        $init = $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
            'couponCode' => 'SAVE10',
            'callbackUrl' => 'http://localhost/dashboard/subscription?checkout=success',
        ])->assertOk();

        $init->assertJsonPath('amount', $expectedFinal)
            ->assertJsonPath('coupon', 'SAVE10')
            ->assertJsonPath('discountAmount', round($original - $expectedFinal, 2));

        $reference = $init->json('reference');
        $this->assertDatabaseHas('coupon_redemptions', [
            'payment_reference' => $reference,
            'status' => 'pending',
            'subscription_offer_id' => $offer['id'],
        ]);
        $this->assertDatabaseHas('billing_payments', [
            'external_payment_id' => $reference,
            'amount' => $expectedFinal,
            'status' => 'pending',
        ]);

        $subunit = (int) round($expectedFinal * 100);
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 991122,
                'reference' => $reference,
                'amount' => $subunit,
                'currency' => 'KES',
                'status' => 'success',
                'paid_at' => now()->toIso8601String(),
                'metadata' => [
                    'company_id' => $company->id,
                    'plan_slug' => $plan->slug,
                    'type' => 'subscription',
                ],
            ],
        ]);

        $this->call(
            'POST',
            '/api/paystack/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_x-paystack-signature' => hash_hmac('sha512', $payload, $this->secret),
            ],
            $payload
        )->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'external_payment_id' => $reference,
        ]);
        $this->assertDatabaseHas('coupon_redemptions', [
            'payment_reference' => $reference,
            'status' => 'applied',
        ]);
        $this->assertDatabaseHas('subscription_offers', [
            'id' => $offer['id'],
            'redemption_count' => 1,
        ]);
        $this->assertDatabaseHas('company_notifications', [
            'company_id' => $company->id,
            'title' => 'Subscription confirmed',
        ]);
    }

    public function test_invalid_coupon_is_rejected_at_preview_and_checkout(): void
    {
        ['owner' => $owner, 'plan' => $plan] = $this->setupActors();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/coupon/preview', [
            'planId' => (string) $plan->id,
            'couponCode' => 'NOPE',
        ])->assertStatus(422);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
            'couponCode' => 'NOPE',
        ])->assertStatus(422);
    }

    public function test_subscription_show_includes_plan_name_and_days_remaining(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->setupActors();
        Subscription::where('company_id', $company->id)->update([
            'plan' => 'professional',
            'status' => 'active',
            'end_date' => now()->addDays(5)->toDateString(),
            'amount' => 99,
        ]);
        Sanctum::actingAs($owner);

        $this->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('plan', 'professional')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('daysRemaining', 5)
            ->assertJsonPath('isExpiringSoon', true)
            ->assertJsonPath('accessEndsLabel', 'Renews on')
            ->assertJsonStructure(['planName']);
    }

    public function test_expiry_reminders_send_email_and_in_app_idempotently(): void
    {
        ['company' => $company] = $this->setupActors();
        $sub = Subscription::where('company_id', $company->id)->firstOrFail();
        $sub->update([
            'status' => 'active',
            'plan' => 'professional',
            'end_date' => now()->addDays(3)->toDateString(),
            'amount' => 50,
        ]);

        $this->artisan('subscription:expiry-reminders', ['--days' => '7,3,1'])
            ->assertSuccessful();

        $this->assertDatabaseHas('subscription_reminder_logs', [
            'subscription_id' => $sub->id,
            'days_before' => 3,
            'channel' => 'lifecycle',
        ]);
        $this->assertDatabaseHas('company_notifications', [
            'company_id' => $company->id,
            'title' => 'Subscription expiring soon',
        ]);

        $logsBefore = SubscriptionReminderLog::count();
        $notifBefore = CompanyNotification::where('company_id', $company->id)->count();

        $this->artisan('subscription:expiry-reminders', ['--days' => '7,3,1'])
            ->assertSuccessful();

        $this->assertSame($logsBefore, SubscriptionReminderLog::count());
        $this->assertSame($notifBefore, CompanyNotification::where('company_id', $company->id)->count());
    }

    public function test_expire_flag_marks_ended_subscriptions_and_notifies(): void
    {
        ['company' => $company] = $this->setupActors();
        $sub = Subscription::where('company_id', $company->id)->firstOrFail();
        $sub->update([
            'status' => 'active',
            'plan' => 'professional',
            'end_date' => now()->subDay()->toDateString(),
            'amount' => 50,
        ]);

        $this->artisan('subscription:expiry-reminders', ['--expire' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'status' => 'expired',
        ]);
        $this->assertDatabaseHas('company_notifications', [
            'company_id' => $company->id,
            'title' => 'Subscription expired',
        ]);
    }

    public function test_lifecycle_service_includes_one_day_reminder(): void
    {
        ['company' => $company] = $this->setupActors();
        $sub = Subscription::where('company_id', $company->id)->firstOrFail();
        $sub->update([
            'status' => 'cancelled',
            'plan' => 'professional',
            'end_date' => now()->addDay()->toDateString(),
        ]);

        $result = app(SubscriptionLifecycleService::class)->sendExpiryReminders([7, 3, 1]);
        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseHas('subscription_reminder_logs', [
            'subscription_id' => $sub->id,
            'days_before' => 1,
        ]);
    }
}
