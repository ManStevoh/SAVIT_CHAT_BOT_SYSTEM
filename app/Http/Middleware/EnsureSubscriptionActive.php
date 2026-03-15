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
     */
    protected array $allowedRoutes = [
        'GET company/subscription',
        'GET company/subscription/invoices',
        'GET company/subscription/usage',
        'POST company/checkout',
        'POST company/billing-portal',
        'POST company/mpesa/initiate',
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

        $subscription = Subscription::where('company_id', $user->company_id)
            ->orderByDesc('end_date')
            ->first();

        if (! $subscription) {
            return $next($request);
        }

        $isExpired = $subscription->end_date->isPast();
        $isCancelled = $subscription->status === 'cancelled';

        if ($isExpired || $isCancelled) {
            return response()->json([
                'message' => 'Your subscription has expired or was cancelled. Please renew to continue using the service.',
                'code' => 'subscription_expired',
            ], 403);
        }

        return $next($request);
    }
}
