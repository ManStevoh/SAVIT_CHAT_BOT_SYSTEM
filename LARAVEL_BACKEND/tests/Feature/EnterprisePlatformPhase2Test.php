<?php

namespace Tests\Feature;

use App\Models\BillingPayment;
use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\CompanyPolicyRule;
use App\Models\CompanySetting;
use App\Models\DomainEvent;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageMeter;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Platform\ApiKeyService;
use App\Services\Platform\BillingLedgerService;
use App\Services\Platform\DomainEventDispatcher;
use App\Services\Platform\EntitlementService;
use App\Services\Platform\UsageMeterService;
use App\Services\Platform\WebhookDeliveryService;
use App\Services\PlanLimitService;
use Database\Seeders\EnterprisePlatformSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnterprisePlatformPhase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->seed(EnterprisePlatformSeeder::class);
    }

    private function platformCompany(): array
    {
        $company = Company::create([
            'name' => 'Platform Co',
            'email' => 'platform@test.local',
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
        CompanySetting::create(['company_id' => $company->id]);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_owner',
            'email_verified_at' => now(),
        ]);

        return ['company' => $company, 'owner' => $owner];
    }

    public function test_entitlements_loaded_from_plan_db(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $this->assertNotNull($plan?->entitlements);
        $this->assertSame(50000, $plan->entitlements['messages']);

        ['company' => $company] = $this->platformCompany();
        $limits = app(EntitlementService::class)->limitsForCompany($company);
        $this->assertSame(50000, $limits['messages']);
        $this->assertSame(50000, PlanLimitService::getMessageLimitForPlan('professional'));
    }

    public function test_usage_meter_increments(): void
    {
        ['company' => $company] = $this->platformCompany();
        $meter = app(UsageMeterService::class);
        $meter->increment($company, 'messages', 3);

        $this->assertSame(3, $meter->consumed($company, 'messages'));
        $this->assertTrue($meter->isWithinLimit($company, 'messages'));
        $this->assertSame(1, UsageMeter::where('company_id', $company->id)->count());
    }

    public function test_billing_ledger_idempotent(): void
    {
        ['company' => $company] = $this->platformCompany();
        $ledger = app(BillingLedgerService::class);

        $payment = $ledger->record('stripe', 'evt_123', 99.0, $company->id, null, 'USD');
        $dup = $ledger->record('stripe', 'evt_123', 99.0, $company->id, null, 'USD');

        $this->assertSame($payment->id, $dup->id);
        $this->assertSame(1, BillingPayment::count());
        $this->assertSame(1, DomainEvent::where('event_type', 'payment.received')->count());
    }

    public function test_notification_templates_seeded(): void
    {
        $this->assertGreaterThanOrEqual(6, NotificationTemplate::count());
        $this->assertNotNull(NotificationTemplate::where('key', 'payment.received')->first());
    }

    public function test_policy_rules_crud_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->platformCompany();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/company/policy-rules', [
            'action_type' => 'issue_order_refund',
            'requires_role' => 'company_owner',
            'max_amount' => 50000,
        ]);
        $create->assertCreated();

        $list = $this->getJson('/api/company/policy-rules');
        $list->assertOk();
        $list->assertJsonCount(1, 'rules');

        $id = $create->json('rule.id');
        $this->patchJson('/api/company/policy-rules/'.$id, ['max_amount' => 100000])->assertOk();
        $this->assertSame(100000.0, (float) CompanyPolicyRule::find($id)->max_amount);
    }

    public function test_api_key_auth_v1_orders(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->platformCompany();
        Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-API-1',
            'customer_phone' => '254700000001',
            'customer_name' => 'API Buyer',
            'total' => 1500,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $result = app(ApiKeyService::class)->create($company, $owner, 'Test key', ['read']);
        $plain = $result['plain_text'];

        $health = $this->getJson('/api/v1/company/health', [
            'Authorization' => 'Bearer '.$plain,
        ]);
        $health->assertOk();
        $health->assertJsonPath('version', 'v1');

        $orders = $this->getJson('/api/v1/company/orders', [
            'Authorization' => 'Bearer '.$plain,
        ]);
        $orders->assertOk();
        $orders->assertJsonCount(1, 'orders');
    }

    public function test_webhook_endpoint_queues_delivery_on_domain_event(): void
    {
        ['company' => $company] = $this->platformCompany();
        WebhookEndpoint::create([
            'company_id' => $company->id,
            'url' => 'https://example.com/hook',
            'secret' => 'testsecret',
            'events' => ['payment.received'],
            'is_active' => true,
        ]);

        app(DomainEventDispatcher::class)->dispatch('payment.received', ['amount' => 99], $company->id);
        app(DomainEventDispatcher::class)->processPending();

        $this->assertSame(1, WebhookDelivery::where('company_id', $company->id)->count());
    }

    public function test_api_platform_billing_history_endpoint(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->platformCompany();
        Sanctum::actingAs($owner);

        app(BillingLedgerService::class)->record('mpesa', 'mpesa_evt_1', 2900, $company->id, null, 'KES');

        $response = $this->getJson('/api/company/api-platform/billing-history');
        $response->assertOk();
        $response->assertJsonCount(1, 'payments');
    }

    public function test_create_and_revoke_api_key_via_api(): void
    {
        ['company' => $company, 'owner' => $owner] = $this->platformCompany();
        Sanctum::actingAs($owner);

        $create = $this->postJson('/api/company/api-platform/keys', ['name' => 'Integration']);
        $create->assertCreated();
        $this->assertNotEmpty($create->json('plainText'));

        $id = $create->json('apiKey.id');
        $this->deleteJson('/api/company/api-platform/keys/'.$id)->assertOk();
        $this->assertNotNull(CompanyApiKey::find($id)->revoked_at);
    }
}
