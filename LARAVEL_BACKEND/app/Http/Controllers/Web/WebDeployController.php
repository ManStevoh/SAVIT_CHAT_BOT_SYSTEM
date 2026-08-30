<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Deploy\DeployAuthService;
use App\Services\Deploy\DeployExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebDeployController extends Controller
{
    public function __construct(
        private readonly DeployAuthService     $authService,
        private readonly DeployExecutionService $execution,
    ) {}

    /**
     * Agent Navigation Mode API trigger.
     * Allows automated/agent deployment execution via X-Deploy-Agent-Key or Bearer token.
     */
    public function agentTrigger(Request $request): StreamedResponse|JsonResponse
    {
        $key = (string) (
            $request->header('X-Deploy-Agent-Key')
            ?: $request->bearerToken()
            ?: $request->input('key')
            ?: $request->input('token')
            ?: ''
        );

        if (! $this->authService->validateAgentKey($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid deploy agent key.',
            ], 401);
        }

        if ($this->execution->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'A deployment is already running on the server. Please wait.',
            ], 409);
        }

        $branch = (string) ($request->input('custom_branch') ?: $request->input('branch', 'main'));
        $cleanBranch = $this->sanitiseBranch($branch);

        $wantsStream = $request->hasHeader('Accept') && str_contains($request->header('Accept'), 'text/event-stream');

        if ($wantsStream || (bool) $request->input('stream', true)) {
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

                $sendEvent([
                    'type'    => 'start',
                    'branch'  => $cleanBranch,
                    'message' => "🤖 [Agent Mode] Live deployment stream initiated for [{$cleanBranch}]...",
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
                        'message'  => ($result['success'] ?? true) ? 'Agent deployment completed successfully.' : 'Agent deployment failed.',
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

        try {
            $logs = [];
            $result = $this->execution->runStreamed($cleanBranch, function (string $line) use (&$logs) {
                $logs[] = $line;
            });

            return response()->json([
                'success'  => $result['success'] ?? true,
                'status'   => $result['status'] ?? 'complete',
                'branch'   => $cleanBranch,
                'duration' => $result['duration'] ?? 0,
                'logs'     => $logs,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status'  => 'failed',
                'message' => 'Agent deployment execution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sanitise a branch name: strip anything that is not alphanumeric, dash, underscore, or slash.
     */
    private function sanitiseBranch(string $branch): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $branch) ?: 'main';
    }
}

