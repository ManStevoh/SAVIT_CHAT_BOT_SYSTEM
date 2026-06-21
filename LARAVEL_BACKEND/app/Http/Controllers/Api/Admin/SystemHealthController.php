<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function index(): JsonResponse
    {
        $pending = (int) DB::table('jobs')->count();
        $failed = (int) DB::table('failed_jobs')->count();

        $expiringTokens = SocialAccount::where('status', 'connected')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<', now()->addDays(7))
            ->count();

        return response()->json([
            'queue' => [
                'pending' => $pending,
                'failed' => $failed,
                'healthy' => $failed === 0 && $pending < 100,
            ],
            'integrations' => [
                'metaOAuthConfigured' => (bool) config('growth.oauth.meta.client_id'),
                'expiringTokens' => $expiringTokens,
            ],
            'alerts' => array_values(array_filter([
                $failed > 0 ? "{$failed} failed job(s) — run php artisan queue:retry all" : null,
                $pending > 100 ? "Queue backlog: {$pending} jobs pending" : null,
                $expiringTokens > 0 ? "{$expiringTokens} social token(s) expiring within 7 days" : null,
            ])),
        ]);
    }
}
