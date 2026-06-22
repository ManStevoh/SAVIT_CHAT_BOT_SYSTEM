<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (($user->status ?? 'active') === 'inactive') {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
                'code' => 'account_inactive',
            ], 403);
        }

        if ($user->company_id) {
            $company = $user->company;
            if (! $company || $company->status === 'suspended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your company account is not active.',
                    'code' => 'company_inactive',
                ], 403);
            }
        }

        return $next($request);
    }
}
