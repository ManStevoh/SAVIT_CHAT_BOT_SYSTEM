<?php

namespace App\Services\Deploy;

use Illuminate\Support\Str;
use RuntimeException;

class DeployAuthService
{
    /**
     * Validate a plaintext secret against the configured DEPLOY_SECRET.
     *
     * @throws RuntimeException when DEPLOY_SECRET is not configured.
     */
    public function validate(string $secret): bool
    {
        $configured = config('deploy.secret');

        if (empty($configured)) {
            throw new RuntimeException('DEPLOY_SECRET is not set. Configure it in your .env file.');
        }

        return hash_equals((string) $configured, $secret);
    }

    /**
     * Issue a short-lived, opaque deploy auth token.
     * Stored in the application cache with a configurable TTL.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $ttl   = (int) config('deploy.token_ttl', 30);

        cache()->put(
            "deploy_auth_token:{$token}",
            ['issued_at' => now()->toIso8601String()],
            now()->addMinutes($ttl)
        );

        return $token;
    }

    /**
     * Check if an auth token is still valid (not expired).
     */
    public function hasValidToken(string $token): bool
    {
        if (empty($token) || strlen($token) < 40) {
            return false;
        }

        return (bool) cache()->get("deploy_auth_token:{$token}");
    }

    /**
     * Invalidate an auth token immediately.
     * Call this on logout or after the user is done deploying.
     */
    public function revokeToken(string $token): void
    {
        cache()->forget("deploy_auth_token:{$token}");
    }

    /**
     * Test whether the current PHP environment can spawn background processes.
     * Used by the auth endpoint so the frontend can choose polling vs synchronous mode.
     */
    public function canSpawnBackground(): bool
    {
        if (! function_exists('shell_exec')) {
            return false;
        }

        try {
            $testFile = storage_path('framework/deploy_bg_test_' . Str::random(8) . '.txt');
            @shell_exec('echo 1 > ' . escapeshellarg($testFile) . ' 2>/dev/null &');

            // Give the OS up to 300ms to write the file
            $deadline = microtime(true) + 0.3;
            while (microtime(true) < $deadline) {
                if (file_exists($testFile)) {
                    @unlink($testFile);
                    return true;
                }
                usleep(20_000);
            }
        } catch (\Throwable) {}

        return false;
    }
}
