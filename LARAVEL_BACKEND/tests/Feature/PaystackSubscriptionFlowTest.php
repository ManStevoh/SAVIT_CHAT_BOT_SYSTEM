<?php

namespace Tests\Feature;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaystackSubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'sk_test_paystack_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->enablePaystack();
    }

    private function enablePaystack(string $currency = 'kes'): void
    {
        PaymentGateway::query()->where('slug', 'paystack')->delete();
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => 'pk_test_x',
                'secret_key' => $this->secret,
                'currency' => $currency,
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');
    }

    /**
     * @return array{company: Company, owner: User, plan: Plan}
     */
    private function companyWithTrial(): array
    {
        $company = Company::create([
            'name' => 'Paystack Co',
            'email' => 'billing@paystack-co.test',
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
            'payment_method' => null,
        ]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);
        $plan = Plan::where('slug', 'professional')->firstOrFail();

        return compact('company', 'owner', 'plan');
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, $this->secret);
    }

    private function postWebhook(array $event): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $this->assertIsString($payload);

        return $this->call(
            'POST',
            '/api/paystack/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_x-paystack-signature' => $this->sign($payload),
            ],
            $payload
        );
    }

    public function test_admin_can_create_and_update_paid_plan(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/plans', [
            'name' => 'Pro Plus',
            'slug' => 'pro_plus',
            'priceDisplay' => 'KES 4,999',
            'priceAmount' => 4999,
            'description' => 'Paystack plan',
            'features' => ['Feature A'],
            'popular' => true,
            'isFree' => false,
            'hasTrial' => true,
            'trialDays' => 7,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('plan.slug', 'pro_plus')
            ->assertJsonPath('plan.priceAmount', 4999);

        $planId = $create->json('plan.id');

        $this->putJson('/api/admin/plans/'.$planId, [
            'priceAmount' => 5999,
            'priceDisplay' => 'KES 5,999',
        ])->assertOk()
            ->assertJsonPath('plan.priceAmount', 5999);

        $this->getJson('/api/plans')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'pro_plus'])
            ->assertJsonFragment(['paystackCurrency' => 'KES']);
    }

    public function test_initialize_creates_pending_payment_and_returns_authorization_url(): void
    {
        ['company' => $company, 'owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-auth',
                    'reference' => 'essem_sub_'.$company->id.'_fixedref',
                ],
            ], 200),
        ]);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
            'callbackUrl' => 'http://localhost/dashboard/subscription?checkout=success',
        ])->assertOk()
            ->assertJsonPath('authorizationUrl', 'https://checkout.paystack.com/test-auth')
            ->assertJsonPath('reference', 'essem_sub_'.$company->id.'_fixedref')
            ->assertJsonPath('currency', 'KES');

        $this->assertDatabaseHas('billing_payments', [
            'company_id' => $company->id,
            'gateway' => 'paystack',
            'external_payment_id' => 'essem_sub_'.$company->id.'_fixedref',
            'status' => 'pending',
            'amount' => 99,
            'currency' => 'KES',
        ]);
    }

    public function test_initialize_rejects_external_callback_url(): void
    {
        ['owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
            'callbackUrl' => 'https://evil.example/steal',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid callback URL.');
    }

    public function test_initialize_rejects_free_or_zero_plans(): void
    {
        ['owner' => $owner] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        $free = Plan::create([
            'name' => 'Free',
            'slug' => 'free_plan',
            'price_display' => 'Free',
            'price_amount' => 0,
            'is_free' => true,
            'features' => [],
        ]);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $free->id,
        ])->assertStatus(422);
    }

    public function test_webhook_activates_subscription_cancels_trial_and_records_ledger(): void
    {
        ['company' => $company, 'owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        $reference = 'essem_sub_'.$company->id.'_wh1';
        Cache::put('paystack_pending:'.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
        ], now()->addHour());

        BillingPayment::create([
            'company_id' => $company->id,
            'gateway' => 'paystack',
            'external_event_id' => 'paystack_pending:'.$reference,
            'external_payment_id' => $reference,
            'amount' => 99,
            'currency' => 'KES',
            'status' => 'pending',
            'payment_type' => 'subscription',
            'metadata' => ['company_id' => $company->id, 'plan_slug' => $plan->slug],
        ]);

        $this->postWebhook([
            'event' => 'charge.success',
            'data' => [
                'id' => 555001,
                'reference' => $reference,
                'amount' => 9900,
                'currency' => 'KES',
                'status' => 'success',
                'metadata' => [
                    'type' => 'subscription',
                    'company_id' => $company->id,
                    'plan_slug' => $plan->slug,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'payment_method' => 'paystack',
            'external_payment_id' => $reference,
            'amount' => 99,
        ]);

        $this->assertSame(
            0,
            Subscription::where('company_id', $company->id)->where('status', 'trial')->count()
        );

        $this->assertDatabaseHas('billing_payments', [
            'gateway' => 'paystack',
            'external_event_id' => '555001',
            'status' => 'paid',
            'company_id' => $company->id,
        ]);

        $this->getJson('/api/company/subscription')
            ->assertOk()
            ->assertJsonPath('plan', 'professional')
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('paymentMethod', 'paystack');

        $this->getJson('/api/company/subscription/invoices')
            ->assertOk()
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['gateway' => 'paystack']);
    }

    public function test_webhook_is_idempotent_for_duplicate_charge_success(): void
    {
        ['company' => $company, 'plan' => $plan] = $this->companyWithTrial();
        $reference = 'essem_sub_'.$company->id.'_dup';
        Cache::put('paystack_pending:'.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
        ], now()->addHour());

        $event = [
            'event' => 'charge.success',
            'data' => [
                'id' => 555002,
                'reference' => $reference,
                'amount' => 9900,
                'currency' => 'KES',
                'metadata' => [
                    'type' => 'subscription',
                    'company_id' => $company->id,
                    'plan_slug' => $plan->slug,
                ],
            ],
        ];

        $this->postWebhook($event)->assertOk();
        $this->postWebhook($event)->assertOk();

        $this->assertSame(1, Subscription::where('external_payment_id', $reference)->count());
        $this->assertSame(1, BillingPayment::where('external_event_id', '555002')->count());
    }

    public function test_webhook_rejects_amount_mismatch(): void
    {
        ['company' => $company, 'plan' => $plan] = $this->companyWithTrial();
        $reference = 'essem_sub_'.$company->id.'_mismatch';
        Cache::put('paystack_pending:'.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $plan->slug,
        ], now()->addHour());

        $this->postWebhook([
            'event' => 'charge.success',
            'data' => [
                'id' => 555003,
                'reference' => $reference,
                'amount' => 100, // 1.00 instead of 99.00
                'currency' => 'KES',
                'metadata' => [
                    'type' => 'subscription',
                    'company_id' => $company->id,
                    'plan_slug' => $plan->slug,
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('subscriptions', [
            'external_payment_id' => $reference,
            'status' => 'active',
        ]);
    }

    public function test_webhook_activates_from_durable_pending_when_cache_missing(): void
    {
        ['company' => $company, 'plan' => $plan] = $this->companyWithTrial();
        $reference = 'essem_sub_'.$company->id.'_durable';

        BillingPayment::create([
            'company_id' => $company->id,
            'gateway' => 'paystack',
            'external_event_id' => 'paystack_pending:'.$reference,
            'external_payment_id' => $reference,
            'amount' => 99,
            'currency' => 'KES',
            'status' => 'pending',
            'payment_type' => 'subscription',
            'metadata' => ['company_id' => $company->id, 'plan_slug' => $plan->slug],
        ]);

        $this->postWebhook([
            'event' => 'charge.success',
            'data' => [
                'id' => 555004,
                'reference' => $reference,
                'amount' => 9900,
                'currency' => 'KES',
                'metadata' => ['type' => 'subscription'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'external_payment_id' => $reference,
            'status' => 'active',
        ]);
    }

    public function test_verify_endpoint_activates_when_webhook_delayed(): void
    {
        ['company' => $company, 'owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        $reference = 'essem_sub_'.$company->id.'_verify';
        BillingPayment::create([
            'company_id' => $company->id,
            'gateway' => 'paystack',
            'external_event_id' => 'paystack_pending:'.$reference,
            'external_payment_id' => $reference,
            'amount' => 99,
            'currency' => 'KES',
            'status' => 'pending',
            'payment_type' => 'subscription',
            'metadata' => ['company_id' => $company->id, 'plan_slug' => $plan->slug],
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'id' => 555005,
                    'reference' => $reference,
                    'amount' => 9900,
                    'currency' => 'KES',
                    'status' => 'success',
                    'metadata' => [
                        'type' => 'subscription',
                        'company_id' => $company->id,
                        'plan_slug' => $plan->slug,
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/company/paystack/verify', ['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('subscription.plan', 'professional')
            ->assertJsonPath('subscription.status', 'active');
    }

    public function test_upgrade_via_paystack_cancels_previous_active(): void
    {
        ['company' => $company, 'plan' => $growth] = $this->companyWithTrial();
        Subscription::where('company_id', $company->id)->update(['status' => 'cancelled']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'status' => 'active',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(20),
            'amount' => 29,
            'billing_cycle' => 'monthly',
            'payment_method' => 'paystack',
            'external_payment_id' => 'old_ref',
        ]);

        $reference = 'essem_sub_'.$company->id.'_upgrade';
        Cache::put('paystack_pending:'.$reference, [
            'company_id' => $company->id,
            'plan_slug' => $growth->slug,
        ], now()->addHour());

        $this->postWebhook([
            'event' => 'charge.success',
            'data' => [
                'id' => 555006,
                'reference' => $reference,
                'amount' => 9900,
                'currency' => 'KES',
                'metadata' => [
                    'type' => 'subscription',
                    'company_id' => $company->id,
                    'plan_slug' => $growth->slug,
                ],
            ],
        ])->assertOk();

        $this->assertSame(
            1,
            Subscription::where('company_id', $company->id)->where('status', 'active')->count()
        );
        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'external_payment_id' => 'old_ref',
            'status' => 'cancelled',
        ]);
    }

    public function test_company_can_cancel_paystack_subscription(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->companyWithTrial();
        Subscription::where('company_id', $company->id)->update(['status' => 'cancelled']);
        Subscription::create([
            'company_id' => $company->id,
            'plan' => 'professional',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'amount' => 99,
            'billing_cycle' => 'monthly',
            'payment_method' => 'paystack',
            'external_payment_id' => 'cancel_ref',
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/company/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('subscription.status', 'cancelled');
    }

    public function test_expired_company_can_still_initialize_paystack(): void
    {
        ['company' => $company, 'owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Subscription::where('company_id', $company->id)->update([
            'status' => 'expired',
            'end_date' => now()->subDay(),
        ]);
        Sanctum::actingAs($owner);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/renew',
                    'reference' => 'essem_sub_'.$company->id.'_renew',
                ],
            ], 200),
        ]);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
        ])->assertOk();
    }

    public function test_paystack_disabled_returns_503(): void
    {
        PaymentGateway::where('slug', 'paystack')->update(['is_enabled' => false]);
        PaymentGateway::clearConfigCache('paystack');

        ['owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();
        Sanctum::actingAs($owner);

        $this->postJson('/api/company/paystack/initialize', [
            'planId' => (string) $plan->id,
        ])->assertStatus(503);
    }
}
