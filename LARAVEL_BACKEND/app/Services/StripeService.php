<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\StripeObject;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeService
{
    protected function stripeConfig(): array
    {
        if (PaymentGateway::isEnabled('stripe')) {
            return PaymentGateway::getConfig('stripe');
        }

        return config('stripe', []);
    }

    public function __construct()
    {
        $config = $this->stripeConfig();
        Stripe::setApiKey($config['secret'] ?? '');
    }

    public static function isEnabled(): bool
    {
        return PaymentGateway::isEnabled('stripe');
    }

    /**
     * Create or return existing Stripe Customer for company.
     */
    public function getOrCreateCustomer(Company $company): ?string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        try {
            $customer = Customer::create([
                'email' => $company->email,
                'name' => $company->name,
                'metadata' => [
                    'company_id' => (string) $company->id,
                ],
            ]);
            $company->update(['stripe_customer_id' => $customer->id]);

            return $customer->id;
        } catch (\Throwable $e) {
            Log::error('Stripe create customer failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create Checkout Session for subscription. Returns session URL or null.
     *
     * @param  array{success_url: string, cancel_url: string}  $urls
     */
    public function createCheckoutSession(Company $company, Plan $plan, array $urls): ?string
    {
        if (! $plan->stripe_price_id) {
            return null;
        }

        $customerId = $this->getOrCreateCustomer($company);
        $config = $this->stripeConfig();
        $trialDays = 0;
        if ($plan->has_trial) {
            $trialDays = (int) ($plan->trial_days ?: ($config['trial_days'] ?? 14));
        }

        try {
            $params = [
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $plan->stripe_price_id,
                        'quantity' => 1,
                    ],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'company_id' => (string) $company->id,
                        'plan_slug' => $plan->slug,
                    ],
                ],
                'success_url' => $urls['success_url'],
                'cancel_url' => $urls['cancel_url'],
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'plan_slug' => $plan->slug,
                ],
            ];

            if ($trialDays > 0) {
                $params['subscription_data']['trial_period_days'] = $trialDays;
            }

            if ($customerId) {
                $params['customer'] = $customerId;
            } else {
                $params['customer_email'] = $company->email;
            }

            $session = StripeSession::create($params);

            return $session->url;
        } catch (\Throwable $e) {
            Log::error('Stripe checkout session failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create one-time Checkout Session for a customer order. Returns payment URL or null.
     * Webhook checkout.session.completed will mark order paid when metadata contains order_id.
     *
     * @param  array{secret: string, currency?: string}|null  $companyStripeConfig  When set, use company's Stripe secret (and optional currency) instead of platform config.
     */
    public function createOneTimePaymentSessionForOrder(Order $order, ?array $companyStripeConfig = null): ?string
    {
        $config = $companyStripeConfig ?? $this->stripeConfig();
        $secret = $config['secret'] ?? '';
        if ($secret !== '') {
            Stripe::setApiKey($secret);
        }
        $currency = $config['currency'] ?? $config['order_currency'] ?? 'usd';
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $amountCents = (int) round((float) $order->total * 100);
        if ($amountCents <= 0) {
            return null;
        }

        try {
            $session = StripeSession::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower((string) $currency),
                            'product_data' => [
                                'name' => 'Order ' . $order->order_number,
                                'description' => 'Payment for order from ' . ($order->company->name ?? 'Store'),
                            ],
                            'unit_amount' => $amountCents,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $frontendUrl . '/order-paid?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/order-paid?cancelled=1',
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

            return $session->url;
        } catch (\Throwable $e) {
            Log::error('Stripe one-time session failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Handle one-time order payment (checkout.session.completed with mode=payment and order_id in metadata).
     */
    public function handleOrderPaymentCompleted(StripeObject $session, OrderPaymentService $orderPaymentService): void
    {
        $orderId = $session->metadata->order_id ?? $session->metadata['order_id'] ?? null;
        if (! $orderId) {
            return;
        }

        $order = Order::find($orderId);
        if (! $order || $order->payment_status === 'paid') {
            return;
        }

        $orderPaymentService->markOrderPaid($order);
    }

    /**
     * Create Billing Portal session for managing subscription. Returns URL or null.
     */
    public function createBillingPortalSession(Company $company, string $returnUrl): ?string
    {
        $customerId = $company->stripe_customer_id;
        if (! $customerId) {
            return null;
        }

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customerId,
                'return_url' => $returnUrl,
            ]);

            return $session->url;
        } catch (\Throwable $e) {
            Log::error('Stripe billing portal session failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Verify webhook signature and build event. Returns Stripe event or null.
     */
    public function constructWebhookEvent(string $payload, string $signature): ?StripeObject
    {
        $config = $this->stripeConfig();
        $secret = $config['webhook_secret'] ?? '';
        if (! $secret) {
            Log::warning('Stripe webhook secret not set');

            return null;
        }

        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());

            return null;
        } catch (\Throwable $e) {
            Log::error('Stripe webhook construct failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Handle checkout.session.completed: create or update local Subscription.
     */
    public function handleCheckoutSessionCompleted(StripeObject $session): void
    {
        $subscriptionId = $session->subscription ?? null;
        if (! $subscriptionId) {
            return;
        }

        $companyId = $session->metadata->company_id ?? $session->metadata['company_id'] ?? null;
        $planSlug = $session->metadata->plan_slug ?? $session->metadata['plan_slug'] ?? null;
        if (! $companyId || ! $planSlug) {
            Log::warning('Stripe checkout session missing company_id or plan_slug in metadata');

            return;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return;
        }

        // Ensure customer is stored
        if ($session->customer && ! $company->stripe_customer_id) {
            $company->update(['stripe_customer_id' => $session->customer]);
        }

        try {
            $stripeSubscription = StripeSubscription::retrieve($subscriptionId);
        } catch (\Throwable $e) {
            Log::error('Stripe retrieve subscription failed: '.$e->getMessage());

            return;
        }

        $this->syncSubscriptionFromStripe($company, $stripeSubscription, $planSlug);
    }

    /**
     * Handle customer.subscription.updated.
     */
    public function handleSubscriptionUpdated(StripeObject $stripeSubscription): void
    {
        $companyId = $stripeSubscription->metadata->company_id ?? $stripeSubscription->metadata['company_id'] ?? null;
        $planSlug = $stripeSubscription->metadata->plan_slug ?? $stripeSubscription->metadata['plan_slug'] ?? null;

        if (! $companyId) {
            $sub = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
            $companyId = $sub?->company_id;
            $planSlug = $planSlug ?? $sub?->plan;
        }

        if (! $companyId) {
            return;
        }

        $company = Company::find($companyId);
        if (! $company) {
            return;
        }

        $this->syncSubscriptionFromStripe($company, $stripeSubscription, $planSlug);
    }

    /**
     * Handle customer.subscription.deleted.
     */
    public function handleSubscriptionDeleted(StripeObject $stripeSubscription): void
    {
        $local = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if (! $local) {
            return;
        }

        $local->update([
            'status' => 'cancelled',
            'end_date' => now(),
        ]);
    }

    /**
     * Create or update local Subscription from Stripe subscription object.
     */
    protected function syncSubscriptionFromStripe(Company $company, StripeObject $stripeSub, ?string $planSlug = null): void
    {
        $planSlug = $planSlug ?? $this->inferPlanSlugFromStripe($stripeSub);
        $amount = 0;
        $interval = 'monthly';

        if (isset($stripeSub->items->data[0])) {
            $item = $stripeSub->items->data[0];
            if (isset($item->price->unit_amount)) {
                $amount = $item->price->unit_amount / 100;
            }
            if (isset($item->price->recurring->interval)) {
                $interval = $item->price->recurring->interval === 'year' ? 'yearly' : 'monthly';
            }
        }

        $startDate = isset($stripeSub->current_period_start)
            ? date('Y-m-d', $stripeSub->current_period_start)
            : now()->format('Y-m-d');
        $endDate = isset($stripeSub->current_period_end)
            ? date('Y-m-d', $stripeSub->current_period_end)
            : now()->addMonth()->format('Y-m-d');

        $status = 'active';
        if (isset($stripeSub->status)) {
            $status = match ($stripeSub->status) {
                'trialing' => 'trial',
                'active' => 'active',
                'past_due' => 'active',
                'canceled', 'unpaid' => 'cancelled',
                default => 'active',
            };
        }

        Subscription::updateOrCreate(
            [
                'stripe_subscription_id' => $stripeSub->id,
            ],
            [
                'company_id' => $company->id,
                'plan' => $planSlug,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount' => $amount,
                'billing_cycle' => $interval,
                'payment_method' => 'stripe',
            ]
        );
    }

    protected function inferPlanSlugFromStripe(StripeObject $stripeSub): string
    {
        $existing = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

        return $existing?->plan ?? 'starter';
    }

    /**
     * List invoices for a Stripe customer. Returns array of [id, date, amount, status, invoicePdf].
     *
     * @return array<int, array{id: string, date: string, amount: string, status: string, invoicePdf: string|null}>
     */
    public function listInvoicesForCustomer(string $stripeCustomerId, int $limit = 50): array
    {
        try {
            $invoices = Invoice::all([
                'customer' => $stripeCustomerId,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe list invoices failed: '.$e->getMessage());

            return [];
        }

        $result = [];
        foreach ($invoices->data as $inv) {
            $amount = isset($inv->amount_paid) ? $inv->amount_paid / 100 : 0;
            $result[] = [
                'id' => $inv->number ?? $inv->id,
                'date' => isset($inv->created) ? date('Y-m-d', $inv->created) : now()->format('Y-m-d'),
                'amount' => '$'.number_format($amount, 2),
                'status' => $inv->status ?? 'unknown',
                'invoicePdf' => $inv->invoice_pdf ?? $inv->hosted_invoice_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * Handle invoice.paid: send payment received email to company.
     */
    public function handleInvoicePaid(StripeObject $invoice, MailService $mailService): void
    {
        $customerId = $invoice->customer ?? null;
        if (! $customerId) {
            return;
        }

        $company = Company::where('stripe_customer_id', $customerId)->first();
        $email = $company?->email ?? $invoice->customer_email ?? null;
        if (! $email) {
            return;
        }

        $amount = isset($invoice->amount_paid) ? $invoice->amount_paid / 100 : 0;
        $invoiceId = $invoice->number ?? $invoice->id;
        $date = isset($invoice->created) ? date('Y-m-d', $invoice->created) : now()->format('Y-m-d');

        $mailService->sendInvoicePaid($email, (string) $invoiceId, $amount, $date);
    }
}
