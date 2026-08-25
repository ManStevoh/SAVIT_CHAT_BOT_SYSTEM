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
     * Discover the CLI PHP binary path for background artisan commands.
     */
    public static function findPhpBinary(): string
    {
        $configured = (string) config('deploy.php_path', env('PHP_CLI_PATH', ''));
        if (! empty($configured)) {
            return $configured;
        }

        if (PHP_SAPI === 'cli' && ! empty(PHP_BINARY) && is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $candidates = [
            '/usr/local/bin/php',
            '/usr/bin/php',
            '/bin/php',
            '/opt/cpanel/ea-php83/root/usr/bin/php',
            '/opt/cpanel/ea-php82/root/usr/bin/php',
            '/opt/cpanel/ea-php81/root/usr/bin/php',
            '/opt/cpanel/ea-php80/root/usr/bin/php',
            '/opt/alt/php83/usr/bin/php',
            '/opt/alt/php82/usr/bin/php',
            '/opt/alt/php81/usr/bin/php',
            '/opt/alt/php80/usr/bin/php',
        ];

        foreach ($candidates as $candidate) {
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }

        if (function_exists('exec')) {
            $output = [];
            @exec('which php 2>/dev/null', $output);
            if (! empty($output[0]) && @is_file($output[0]) && @is_executable($output[0])) {
                return $output[0];
            }
        }

        return PHP_BINARY ?: 'php';
    }

    /**
     * Test whether the current PHP environment can spawn background processes.
     * Used by the auth endpoint so the frontend can choose polling vs synchronous mode.
     */
    public function canSpawnBackground(): bool
    {
        if (! function_exists('shell_exec') && ! function_exists('exec')) {
            return false;
        }

        try {
            $testFile = storage_path('framework/deploy_bg_test_' . Str::random(8) . '.txt');
            $cmd = 'nohup echo 1 > ' . escapeshellarg($testFile) . ' 2>&1 &';

            if (function_exists('shell_exec')) {
                @shell_exec($cmd);
            } elseif (function_exists('exec')) {
                @exec($cmd);
            }

            // Give the OS up to 800ms to write the test marker file
            $deadline = microtime(true) + 0.8;
            while (microtime(true) < $deadline) {
                if (file_exists($testFile)) {
                    @unlink($testFile);
                    return true;
                }
                usleep(25_000);
            }
        } catch (\Throwable) {}

        return false;
    }
}
