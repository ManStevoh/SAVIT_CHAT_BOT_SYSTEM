<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    /**
     * Routes that are always allowed even when subscription is expired (so user can resubscribe).
     * Dashboard shell endpoints (e.g. notifications) must be allowed so the renew page does not
     * trip a client-side redirect loop on every navbar fetch.
     */
    protected array $allowedRoutes = [
        'GET company/subscription',
        'GET company/subscription/invoices',
        'GET company/subscription/usage',
        'GET company/subscription/payment-methods',
        'GET company/subscription/manual-payments',
        'GET company/notifications',
        'POST company/subscription/checkout',
        'POST company/subscription/manual-payments/proof',
        'POST company/checkout',
        'POST company/billing-portal',
        'POST company/mpesa/initiate',
        'POST company/paystack/initialize',
        'POST company/paystack/verify',
        'POST company/subscription/cancel',
        'POST company/coupon/preview',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->company_id) {
            return $next($request);
        }

        // Super admin and admins are never blocked by subscription (they use /admin routes; this is a safeguard)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        $method = strtoupper($request->method());
        $path = trim($request->path(), '/');
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }
        $routeKey = $method . ' ' . $path;
        if (in_array($routeKey, $this->allowedRoutes, true)) {
            return $next($request);
        }

        $hasActiveSubscription = Subscription::where('company_id', $user->company_id)
            ->whereIn('status', ['active', 'trial'])
            ->where('end_date', '>=', now()->toDateString())
            ->exists();

        if (! $hasActiveSubscription) {
            return response()->json([
                'message' => 'Your subscription has expired or was cancelled. Please renew to continue using the service.',
                'code' => 'subscription_expired',
            ], 403);
        }

        return $next($request);
    }
}
