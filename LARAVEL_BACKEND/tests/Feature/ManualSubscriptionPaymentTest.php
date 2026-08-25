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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualSubscriptionPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    protected function seedManualGateway(bool $withDetails = true): void
    {
        PaymentGateway::query()->where('slug', 'manual')->delete();
        PaymentGateway::create([
            'slug' => 'manual',
            'name' => 'Bank Transfer / Invoice',
            'is_enabled' => true,
            'config' => $withDetails
                ? [
                    'bank_name' => 'KCB',
                    'account_name' => 'RelayIQ Platform',
                    'account_number' => '1234567890',
                    'instructions' => 'Transfer and use the invoice reference.',
                    'currency' => 'kes',
                ]
                : [
                    'bank_name' => '',
                    'account_name' => '',
                    'account_number' => '',
                    'instructions' => '',
                    'currency' => 'kes',
                ],
        ]);
        PaymentGateway::clearConfigCache('manual');
    }

    /**
     * @return array{company: Company, owner: User, plan: Plan}
     */
    protected function companyWithTrial(): array
    {
        $company = Company::create([
            'name' => 'Manual Co',
            'email' => 'billing@manual-co.test',
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

    public function test_empty_db_secret_does_not_block_env_paystack_availability(): void
    {
        PaymentGateway::query()->where('slug', 'paystack')->delete();
        PaymentGateway::create([
            'slug' => 'paystack',
            'name' => 'Paystack',
            'is_enabled' => true,
            'config' => [
                'public_key' => '',
                'secret_key' => '',
                'currency' => 'kes',
            ],
        ]);
        PaymentGateway::clearConfigCache('paystack');

        putenv('PAYSTACK_SECRET_KEY=sk_test_from_env');
        $_ENV['PAYSTACK_SECRET_KEY'] = 'sk_test_from_env';
        $_SERVER['PAYSTACK_SECRET_KEY'] = 'sk_test_from_env';

        $cfg = PaymentGateway::getConfig('paystack');
        $this->assertSame('sk_test_from_env', $cfg['secret_key']);
        $this->assertTrue(PaymentGateway::isReady('paystack'));
    }

    public function test_manual_checkout_proof_and_admin_approve_activates_subscription(): void
    {
        Storage::fake('local');
        $this->seedManualGateway();
        ['company' => $company, 'owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();

        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
            'email_verified_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $checkout = $this->postJson('/api/company/subscription/checkout', [
            'plan' => $plan->slug,
            'gateway' => 'manual',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('gateway', 'manual');

        $reference = $checkout->json('invoice_reference');
        $this->assertNotEmpty($reference);

        $this->assertDatabaseHas('billing_payments', [
            'gateway' => 'manual',
            'company_id' => $company->id,
            'status' => 'pending',
            'external_payment_id' => $reference,
        ]);

        $file = UploadedFile::fake()->image('receipt.jpg');
        $this->post('/api/company/subscription/manual-payments/proof', [
            'reference' => $reference,
            'note' => 'Paid via KCB',
            'proof' => $file,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $payment = BillingPayment::where('external_payment_id', $reference)->first();
        $this->assertNotNull($payment);
        $this->assertSame('awaiting_review', $payment->status);
        $this->assertNotEmpty($payment->metadata['proof_path'] ?? null);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/manual-payments')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/admin/manual-payments/'.$payment->id.'/approve')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('subscriptions', [
            'company_id' => $company->id,
            'plan' => $plan->slug,
            'status' => 'active',
            'payment_method' => 'manual',
            'external_payment_id' => $reference,
        ]);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_manual_not_available_without_bank_details(): void
    {
        $this->seedManualGateway(false);
        ['owner' => $owner, 'plan' => $plan] = $this->companyWithTrial();

        Sanctum::actingAs($owner);
        $this->postJson('/api/company/subscription/checkout', [
            'plan' => $plan->slug,
            'gateway' => 'manual',
        ])->assertStatus(422);
    }
}
