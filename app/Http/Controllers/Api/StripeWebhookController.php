<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripe
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
                    $this->stripe->handleCheckoutSessionCompleted($event->data->object);
                    break;
                case 'customer.subscription.updated':
                    $this->stripe->handleSubscriptionUpdated($event->data->object);
                    break;
                case 'customer.subscription.deleted':
                    $this->stripe->handleSubscriptionDeleted($event->data->object);
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
}
