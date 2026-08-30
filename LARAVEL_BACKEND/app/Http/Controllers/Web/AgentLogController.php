<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Deploy\DeployAuthService;
use App\Services\Logs\AgentLogReaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentLogController extends Controller
{
    public function __construct(
        private readonly DeployAuthService     $authService,
        private readonly AgentLogReaderService $logReader,
    ) {}

    /**
     * Handle Agent Log query & live stream requests.
     */
    public function handle(Request $request): Response|JsonResponse|StreamedResponse
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
                'message' => 'Unauthorized. Invalid or missing agent deploy key.',
            ], 401);
        }

        $channel = (string) $request->input('channel', 'laravel');
        $level   = $request->filled('level') ? (string) $request->input('level') : null;
        $lines   = max(1, min(AgentLogReaderService::MAX_LINES, (int) $request->input('lines', AgentLogReaderService::DEFAULT_LINES)));
        $grep    = $request->filled('grep') ? (string) $request->input('grep') : null;
        $since   = $request->filled('since') ? (string) $request->input('since') : null;
        $format  = strtolower((string) $request->input('format', 'json'));

        $wantsStream = $format === 'stream' || ($request->hasHeader('Accept') && str_contains((string) $request->header('Accept'), 'text/event-stream'));

        if ($wantsStream) {
            return $this->handleStream($channel, $level, $grep);
        }

        $result = $this->logReader->read($channel, $level, $lines, $grep, $since);

        if ($format === 'text') {
            $textLines = array_map(function ($entry) {
                return sprintf(
                    "[%s] [%s] [%s] %s",
                    $entry['timestamp'] ?? '',
                    $entry['channel'] ?? '',
                    $entry['level'] ?? 'INFO',
                    $entry['message'] ?? ''
                );
            }, $result['logs'] ?? []);

            return response(implode("\n", $textLines), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Log-Count'  => (string) $result['count'],
            ]);
        }

        return response()->json([
            'success'   => true,
            'channel'   => $result['channel'],
            'count'     => $result['count'],
            'filters'   => [
                'level' => $level ?? 'all',
                'lines' => $lines,
                'grep'  => $grep,
                'since' => $since,
            ],
            'logs'      => $result['logs'],
        ]);
    }

    /**
     * Stream live log events via SSE.
     */
    private function handleStream(string $channel, ?string $level, ?string $grep): StreamedResponse
    {
        return response()->stream(function () use ($channel, $level, $grep) {
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
                'channel' => $channel,
                'message' => "🤖 [Agent Log Stream] Live tail initiated for [{$channel}]...",
            ]);

            $this->logReader->tailStream(
                $channel,
                function (array $logEntry) use ($sendEvent) {
                    $sendEvent([
                        'type' => 'log',
                        'data' => $logEntry,
                    ]);
                },
                durationSeconds: 120,
                level: $level,
                grep: $grep
            );

            $sendEvent([
                'type'    => 'end',
                'message' => 'Agent log stream session closed after 120 seconds.',
            ]);
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
