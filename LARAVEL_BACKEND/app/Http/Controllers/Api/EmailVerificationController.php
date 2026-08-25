<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles GET /api/auth/verify-email (signed URL from welcome email).
 * Validates signature and hash, marks user verified, redirects to frontend.
 */
class EmailVerificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->redirectToFrontend('/login', ['error' => 'invalid_signature']);
        }

        $user = User::find($request->query('id'));
        if (! $user) {
            return $this->redirectToFrontend('/login', ['error' => 'user_not_found']);
        }

        $hash = $request->query('hash');
        if (! hash_equals((string) $hash, sha1($user->email))) {
            return $this->redirectToFrontend('/login', ['error' => 'invalid_hash']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $this->redirectToFrontend('/login', $this->postVerifyQuery($request, $user));
    }

    /**
     * @return array<string, string>
     */
    private function postVerifyQuery(Request $request, User $user): array
    {
        $query = ['verified' => '1'];

        $plan = $request->query('plan') ?: ($user->wants_immediate_payment ? $user->selected_plan_id : null);
        $pay = $request->query('pay') === '1'
            || ($user->wants_immediate_payment && $user->selected_plan_id);

        if ($plan) {
            $query['plan'] = (string) $plan;
        }
        if ($pay && $plan) {
            $query['pay'] = '1';
        }

        return $query;
    }

    private function redirectToFrontend(string $path, array $query = []): RedirectResponse
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $url = $base . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return redirect()->away($url);
    }
}
