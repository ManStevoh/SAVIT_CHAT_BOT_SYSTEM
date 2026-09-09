<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderCommission;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Billing\BillingResolutionService;
use App\Services\Billing\CommissionCalculationService;
use App\Services\OrderPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionBillingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $user->markEmailAsVerified();
        $user->save();

        return $user;
    }

    public function test_billing_resolution_service_resolves_tenant_override(): void
    {
        $company = Company::create([
            'name' => 'Jostinah Bookshop',
            'email' => 'wjostinah@test.local',
            'status' => 'active',
            'billing_model' => 'commission',
            'commission_rate' => 5.00,
            'commission_basis' => 'total',
            'waive_subscription_fee' => true,
        ]);

        $resolver = app(BillingResolutionService::class);
        $resolution = $resolver->resolveForCompany($company);

        $this->assertSame('commission', $resolution['billing_model']);
        $this->assertSame(5.0, $resolution['commission_rate']);
        $this->assertSame('total', $resolution['commission_basis']);
        $this->assertTrue($resolution['waive_subscription_fee']);
        $this->assertSame('tenant_override', $resolution['source']);
    }

    public function test_billing_resolution_falls_back_to_platform_default(): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create([
            'default_billing_model' => 'subscription',
            'default_commission_rate' => 0.00,
            'allow_public_commission_signup' => false,
        ]);

        $company = Company::create([
            'name' => 'Standard Merchant',
            'email' => 'merchant@test.local',
            'status' => 'active',
            'billing_model' => null,
            'commission_rate' => null,
        ]);

        $resolver = app(BillingResolutionService::class);
        $resolution = $resolver->resolveForCompany($company);

        $this->assertSame('subscription', $resolution['billing_model']);
        $this->assertSame(0.0, $resolution['commission_rate']);
        $this->assertFalse($resolution['is_commission_active']);
    }

    public function test_commission_calculation_and_order_payment_hook_manual(): void
    {
        $company = Company::create([
            'name' => 'Commission Merchant',
            'email' => 'comm@test.local',
            'status' => 'active',
            'billing_model' => 'commission',
            'commission_rate' => 5.00,
            'commission_basis' => 'total',
            'commission_balance_due' => 0.00,
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1001',
            'customer_name' => 'Customer A',
            'customer_phone' => '+254700000000',
            'subtotal' => 1000.00,
            'tax_amount' => 0.00,
            'shipping_amount' => 0.00,
            'discount_amount' => 0.00,
            'total' => 1000.00,
            'currency' => 'KES',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $paymentService = app(OrderPaymentService::class);
        $paidOrder = $paymentService->markOrderPaid(
            $order,
            1000.00,
            'manual',
            'MAN-REF-001',
            ['notes' => 'Direct cash on delivery']
        );

        $this->assertSame('paid', $paidOrder->payment_status);

        // Verify OrderCommission record was created
        $commission = OrderCommission::where('order_id', $order->id)->first();
        $this->assertNotNull($commission);
        $this->assertSame(5.0, (float) $commission->commission_rate);
        $this->assertSame(50.0, (float) $commission->commission_amount);
        $this->assertSame('accrual', $commission->deduction_method);
        $this->assertSame('pending', $commission->status);

        // Verify company balance due increased by $50.00
        $company->refresh();
        $this->assertSame(50.0, (float) $company->commission_balance_due);
    }

    public function test_commission_calculation_and_order_payment_hook_gateway_settled(): void
    {
        $company = Company::create([
            'name' => 'Gateway Merchant',
            'email' => 'gw@test.local',
            'status' => 'active',
            'billing_model' => 'commission',
            'commission_rate' => 5.00,
            'commission_basis' => 'total',
            'commission_balance_due' => 0.00,
        ]);

        $order = Order::create([
            'company_id' => $company->id,
            'order_number' => 'ORD-1002',
            'customer_name' => 'Customer B',
            'customer_phone' => '+254700000001',
            'subtotal' => 2000.00,
            'tax_amount' => 0.00,
            'shipping_amount' => 0.00,
            'discount_amount' => 0.00,
            'total' => 2000.00,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $paymentService = app(OrderPaymentService::class);
        $paymentService->markOrderPaid(
            $order,
            2000.00,
            'stripe',
            'ch_test_123',
            ['gateway' => 'stripe']
        );

        $commission = OrderCommission::where('order_id', $order->id)->first();
        $this->assertNotNull($commission);
        $this->assertSame(100.0, (float) $commission->commission_amount);
        $this->assertSame('gateway_split', $commission->deduction_method);
        $this->assertSame('settled', $commission->status);
        $this->assertNotNull($commission->settled_at);

        // Gateway split does NOT increase balance due
        $company->refresh();
        $this->assertSame(0.0, (float) $company->commission_balance_due);
    }

    public function test_admin_can_update_company_commission_settings(): void
    {
        $company = Company::create([
            'name' => 'Book Store',
            'email' => 'books@test.local',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        Sanctum::actingAs($this->admin());

        $response = $this->putJson("/api/admin/companies/{$company->id}", [
            'name' => 'Book Store Updated',
            'email' => 'books@test.local',
            'plan' => 'starter',
            'status' => 'active',
            'billingModel' => 'commission',
            'commissionRate' => 5.0,
            'commissionBasis' => 'total',
            'waiveSubscriptionFee' => true,
            'commissionInvoiceThreshold' => 50.0,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame('commission', $response->json('company.billingModel'));
        $this->assertSame(5.0, (float) $response->json('company.commissionRate'));

        $company->refresh();
        $this->assertSame('commission', $company->billing_model);
        $this->assertSame(5.0, (float) $company->commission_rate);
        $this->assertSame('total', $company->commission_basis);
        $this->assertTrue((bool) $company->waive_subscription_fee);
        $this->assertSame(50.0, (float) $company->commission_invoice_threshold);
    }
}
