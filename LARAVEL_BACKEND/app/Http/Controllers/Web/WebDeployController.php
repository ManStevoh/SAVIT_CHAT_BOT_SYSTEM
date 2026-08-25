<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Deploy\DeployAuditLogger;
use App\Services\Deploy\DeployAuthService;
use App\Services\Deploy\DeployExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class WebDeployController extends Controller
{
    public function __construct(
        private readonly DeployAuthService     $authService,
        private readonly DeployExecutionService $execution,
        private readonly DeployAuditLogger      $audit,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Page
    // ─────────────────────────────────────────────────────────────

    /**
     * Render the deploy console UI.
     */
    public function index(Request $request): Response|\Illuminate\Contracts\View\View
    {
        // Optional IP allowlist check
        $allowedIps = (array) config('deploy.allowed_ips', []);
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
            abort(403, 'Access denied.');
        }

        // Fail fast + visibly if the secret is not configured
        if (empty(config('deploy.secret'))) {
            return response()->view('deploy.unconfigured', [], 503);
        }

        return response()->view('deploy.console', [
            'history'          => $this->audit->recent(10),
            'backgroundMode'   => (bool) config('deploy.background', true),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Auth
    // ─────────────────────────────────────────────────────────────

    /**
     * Validate the deploy secret and issue a session token + branch list.
     * Also probes whether background process spawning is available.
     */
    public function auth(Request $request): JsonResponse
    {
        $secret = (string) $request->input('secret', '');

        try {
            if (! $this->authService->validate($secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid deployment password.',
                ], 403);
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deploy console is not configured on this server.',
            ], 503);
        }

        $token             = $this->authService->issueToken();
        $branches          = $this->getAvailableBranches();
        $backgroundEnabled = (bool) config('deploy.background', true);
        $canBackground     = $backgroundEnabled && $this->authService->canSpawnBackground();

        return response()->json([
            'success'    => true,
            'message'    => 'Authenticated successfully.',
            'token'      => $token,
            'branches'   => $branches,
            'background' => $canBackground,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Deploy (background — polling mode)
    // ─────────────────────────────────────────────────────────────

    /**
     * Kick off a deploy in the background.
     * Returns a deploy_token the client polls via /deploy/status/{token}.
     */
    public function start(Request $request): JsonResponse
    {
        $authToken = (string) ($request->input('token') ?: $request->header('X-Deploy-Token', ''));
        $branch    = (string) ($request->input('custom_branch') ?: $request->input('branch', 'main'));

        if (! $this->authService->hasValidToken($authToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired or invalid. Please re-authenticate.',
            ], 401);
        }

        if ($this->execution->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'A deployment is already in progress. Please wait.',
            ], 409);
        }

        $cleanBranch = $this->sanitiseBranch($branch);

        // If background spawning is not supported or enabled on this host, automatically run synchronously
        if (! $this->authService->canSpawnBackground()) {
            @set_time_limit(0);
            @ignore_user_abort(true);

            try {
                $result = $this->execution->runSynchronous($cleanBranch);
                return response()->json(array_merge($result, [
                    'success'     => ($result['status'] ?? '') === 'complete',
                    'synchronous' => true,
                ]));
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'status'  => 'failed',
                    'message' => 'Deployment execution failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        try {
            $deployToken = $this->execution->startBackground($cleanBranch);

            return response()->json([
                'success'      => true,
                'deploy_token' => $deployToken,
                'branch'       => $cleanBranch,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize deployment process: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream deployment logs in real-time via Server-Sent Events (SSE).
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $authToken = (string) ($request->input('token') ?: $request->header('X-Deploy-Token', ''));
        $branch    = (string) ($request->input('custom_branch') ?: $request->input('branch', 'main'));

        if (! $this->authService->hasValidToken($authToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired or invalid. Please re-authenticate.',
            ], 401);
        }

        if ($this->execution->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'A deployment is already in progress. Please wait.',
            ], 409);
        }

        $cleanBranch = $this->sanitiseBranch($branch);

        return response()->stream(function () use ($cleanBranch) {
            @set_time_limit(0);
            @ignore_user_abort(true);

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            ob_implicit_flush(true);

            $sendEvent = function (array $payload) {
                echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            };

            // Send initial connection event
            $sendEvent([
                'type'    => 'start',
                'branch'  => $cleanBranch,
                'message' => "Starting live deployment stream for [{$cleanBranch}]...",
            ]);

            try {
                $result = $this->execution->runStreamed($cleanBranch, function (string $line) use ($sendEvent) {
                    $sendEvent([
                        'type' => 'log',
                        'line' => $line,
                    ]);
                });

                $sendEvent([
                    'type'     => 'done',
                    'success'  => $result['success'] ?? true,
                    'status'   => $result['status'] ?? 'complete',
                    'duration' => $result['duration'] ?? 0,
                    'message'  => ($result['success'] ?? true) ? 'Deployment completed successfully.' : 'Deployment failed.',
                ]);
            } catch (\Throwable $e) {
                $sendEvent([
                    'type'    => 'error',
                    'success' => false,
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Poll the status of a running or completed deploy.
     */
    public function status(Request $request, string $deployToken): JsonResponse
    {
        // Guard against path traversal / garbage input
        if (! preg_match('/^[a-zA-Z0-9]{32}$/', $deployToken)) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($this->execution->getStatus($deployToken));
    }

    // ─────────────────────────────────────────────────────────────
    // Deploy (synchronous — fallback mode)
    // ─────────────────────────────────────────────────────────────

    /**
     * Run the deploy synchronously (used when background spawning is unavailable).
     * Sets no PHP time limit so long deploys are not killed mid-flight.
     */
    public function run(Request $request): JsonResponse
    {
        $authToken = (string) ($request->input('token') ?: $request->header('X-Deploy-Token', ''));
        $branch    = (string) ($request->input('custom_branch') ?: $request->input('branch', 'main'));

        if (! $this->authService->hasValidToken($authToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired or invalid. Please re-authenticate.',
            ], 401);
        }

        if ($this->execution->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'A deployment is already in progress. Please wait.',
            ], 409);
        }

        // Override PHP execution time limit for the duration of this request
        @set_time_limit(0);
        @ignore_user_abort(true);

        $cleanBranch = $this->sanitiseBranch($branch);

        try {
            $result = $this->execution->runSynchronous($cleanBranch);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status'  => 'failed',
                'message' => 'Deployment execution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Sanitise a branch name: strip anything that is not alphanumeric, dash, underscore, or slash.
     */
    private function sanitiseBranch(string $branch): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $branch) ?: 'main';
    }

    /**
     * Return the list of deployable branches.
     * Respects DEPLOY_ALLOWED_BRANCHES override, then discovers from git (cached 5 min).
     *
     * @return string[]
     */
    private function getAvailableBranches(): array
    {
        // Hard config override
        $configured = (array) config('deploy.allowed_branches', []);
        if (! empty($configured)) {
            return array_values($configured);
        }

        $branches = [
            'main',
            'feature/mobile-companion-ken-merge',
            'feature/mobile-companion-v1',
            'feature/inertia-unified',
            'backend',
            'ken',
            'monorepo',
        ];

        try {
            // Cache git discovery for 5 minutes so auth requests don't block on git
            $discovered = cache()->remember('deploy.remote_branches', 300, function () {
                $repoRoot = base_path('..');
                if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                    $repoRoot = base_path();
                }

                $output = @shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git branch -r 2>&1');
                $found  = [];

                if ($output) {
                    foreach (explode("\n", $output) as $line) {
                        $clean = trim($line);
                        if (str_contains($clean, '->') || ! str_starts_with($clean, 'origin/')) {
                            continue;
                        }
                        $name = str_replace('origin/', '', $clean);
                        if ($name) {
                            $found[] = $name;
                        }
                    }
                }

                return $found;
            });

            foreach ($discovered as $name) {
                if (! in_array($name, $branches, true)) {
                    $branches[] = $name;
                }
            }
        } catch (\Throwable) {}

        usort($branches, static fn ($a, $b) => $a === 'main' ? -1 : ($b === 'main' ? 1 : strcmp($a, $b)));

        return array_values(array_unique($branches));
    }
}
