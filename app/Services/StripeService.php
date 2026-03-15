<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Exception\SignatureVerificationException;
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
        $trialDays = (int) ($config['trial_days'] ?? 14);

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
                    'trial_period_days' => $trialDays,
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
            ]
        );
    }

    protected function inferPlanSlugFromStripe(StripeObject $stripeSub): string
    {
        $existing = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

        return $existing?->plan ?? 'starter';
    }
}
