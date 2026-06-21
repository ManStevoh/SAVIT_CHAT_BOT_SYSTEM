<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\MailService;
use App\Services\OrderPaymentService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Stripe\StripeObject;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripe,
        protected MailService $mailService,
        protected OrderPaymentService $orderPaymentService
    ) {}

    /**
     * Handle Stripe webhook. No auth; signature is verified with STRIPE_WEBHOOK_SECRET.
     */
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        $event = $this->stripe->constructWebhookEvent($payload, $signature);
        if (! $event) {
            return response('Invalid signature', 400);
        }

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;
                    $orderId = $session->metadata->order_id ?? $session->metadata['order_id'] ?? null;
                    if ($orderId) {
                        $this->stripe->handleOrderPaymentCompleted($session, $this->orderPaymentService);
                    } else {
                        $this->stripe->handleCheckoutSessionCompleted($session);
                        $this->sendSubscriptionConfirmedEmail($session);
                    }
                    break;
                case 'customer.subscription.updated':
                    $this->stripe->handleSubscriptionUpdated($event->data->object);
                    break;
                case 'customer.subscription.deleted':
                    $this->stripe->handleSubscriptionDeleted($event->data->object);
                    break;
                case 'invoice.paid':
                    $this->stripe->handleInvoicePaid($event->data->object, $this->mailService);
                    break;
                default:
                    Log::info('Stripe webhook unhandled type: '.$event->type);
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook handler error: '.$e->getMessage(), ['event' => $event->type]);

            return response('Webhook handler error', 500);
        }

        return response('OK', 200);
    }

    /**
     * Send subscription confirmed email after checkout (company email, plan name, end date).
     */
    protected function sendSubscriptionConfirmedEmail(StripeObject $session): void
    {
        $companyId = $session->metadata->company_id ?? $session->metadata['company_id'] ?? null;
        $planSlug = $session->metadata->plan_slug ?? $session->metadata['plan_slug'] ?? null;
        if (! $companyId) {
            return;
        }

        $subscription = Subscription::where('company_id', $companyId)->orderByDesc('end_date')->first();
        if (! $subscription) {
            return;
        }

        $company = $subscription->company;
        if (! $company?->email) {
            return;
        }

        $planName = Plan::where('slug', $planSlug ?? $subscription->plan)->first()?->name ?? ucfirst($planSlug ?? $subscription->plan);
        $endDate = $subscription->end_date->format('F j, Y');

        $this->mailService->sendSubscriptionConfirmed($company->email, $planName, $endDate);
    }
}
