<?php

namespace App\Console\Commands;

use App\Services\Deploy\DeployAuthService;
use App\Services\Logs\AgentLogReaderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LogsAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:agent
                            {channel=laravel : Channel to inspect (laravel, whatsapp, agent, deploy, migrate, system_db, ai_requests, all)}
                            {--level= : Filter by log level (error, warning, info, critical, debug)}
                            {--lines=50 : Number of lines/records to retrieve (default: 50, max: 500)}
                            {--grep= : Keyword or regex filter}
                            {--since= : Time window (e.g. 15m, 1h, 24h, 7d)}
                            {--tail : Live stream log output (SSE / continuous tail)}
                            {--remote= : Remote RelayIQ base URL (e.g. https://relayiq.app)}
                            {--json : Output raw JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect or stream server logs via Agent Navigation Gateway';

    public function __construct(
        private readonly DeployAuthService     $authService,
        private readonly AgentLogReaderService $logReader,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $channel   = (string) $this->argument('channel');
        $level     = $this->option('level') ? (string) $this->option('level') : null;
        $lines     = max(1, min(AgentLogReaderService::MAX_LINES, (int) $this->option('lines')));
        $grep      = $this->option('grep') ? (string) $this->option('grep') : null;
        $since     = $this->option('since') ? (string) $this->option('since') : null;
        $isTail    = (bool) $this->option('tail');
        $isJson    = (bool) $this->option('json');
        $remoteUrl = (string) ($this->option('remote') ?: config('deploy.remote_url'));

        if (! empty($remoteUrl)) {
            return $this->handleRemoteLogs(rtrim($remoteUrl, '/'), $channel, $level, $lines, $grep, $since, $isTail, $isJson);
        }

        return $this->handleLocalLogs($channel, $level, $lines, $grep, $since, $isTail, $isJson);
    }

    private function handleLocalLogs(
        string $channel,
        ?string $level,
        int $lines,
        ?string $grep,
        ?string $since,
        bool $isTail,
        bool $isJson
    ): int {
        if ($isTail) {
            $this->info("🤖 [Agent Log Gateway] Live tailing channel [{$channel}] for 120s... (Ctrl+C to stop)");
            $this->logReader->tailStream(
                $channel,
                function (array $entry) use ($isJson) {
                    $this->renderEntry($entry, $isJson);
                },
                durationSeconds: 120,
                level: $level,
                grep: $grep
            );
            return self::SUCCESS;
        }

        $result = $this->logReader->read($channel, $level, $lines, $grep, $since);

        if ($isJson) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->renderHeader($result['channel'], $result['count'], $result['total_available'], $level, $grep, $since);

        // Display logs in chronological order
        $chronologicalLogs = array_reverse($result['logs']);
        foreach ($chronologicalLogs as $entry) {
            $this->renderEntry($entry, false);
        }

        $this->newLine();
        $this->info("✅ Retrieved {$result['count']} entries from [{$result['channel']}].");

        return self::SUCCESS;
    }

    private function handleRemoteLogs(
        string $remoteUrl,
        string $channel,
        ?string $level,
        int $lines,
        ?string $grep,
        ?string $since,
        bool $isTail,
        bool $isJson
    ): int {
        $agentKey = (string) (config('deploy.agent_key') ?: config('deploy.secret'));

        if (empty($agentKey)) {
            $this->error('❌ [CONFIG_ERROR] Neither DEPLOY_AGENT_KEY nor DEPLOY_SECRET is configured in .env.');
            return self::FAILURE;
        }

        $endpoint = "{$remoteUrl}/logs/agent";

        $params = [
            'channel' => $channel,
            'level'   => $level,
            'lines'   => $lines,
            'grep'    => $grep,
            'since'   => $since,
            'format'  => $isTail ? 'stream' : 'json',
        ];

        try {
            if ($isTail) {
                $this->info("🌐 [Agent Log Gateway] Connecting to remote live stream: {$endpoint}...");
                $response = Http::withHeaders([
                    'X-Deploy-Agent-Key' => $agentKey,
                    'Accept'             => 'text/event-stream',
                ])->timeout(130)->post($endpoint, $params);

                foreach (explode("\n", $response->body()) as $rawLine) {
                    $trimmed = trim($rawLine);
                    if (str_starts_with($trimmed, 'data:')) {
                        $jsonStr = trim(substr($trimmed, 5));
                        $data = json_decode($jsonStr, true);
                        if (is_array($data)) {
                            if (($data['type'] ?? '') === 'log' && isset($data['data'])) {
                                $this->renderEntry($data['data'], $isJson);
                            } elseif (($data['type'] ?? '') === 'start') {
                                $this->comment("⚡ " . ($data['message'] ?? 'Stream started'));
                            } elseif (($data['type'] ?? '') === 'end') {
                                $this->info("⏹️ " . ($data['message'] ?? 'Stream ended'));
                            }
                        }
                    }
                }
                return self::SUCCESS;
            }

            $response = Http::withHeaders([
                'X-Deploy-Agent-Key' => $agentKey,
                'Accept'             => 'application/json',
            ])->timeout(30)->post($endpoint, $params);

            if ($response->status() === 401 || $response->status() === 403) {
                $this->error('❌ [AUTH_ERROR] Remote server rejected deploy key. Verify DEPLOY_AGENT_KEY in .env.');
                return self::FAILURE;
            }

            $data = $response->json();

            if (! is_array($data) || ! ($data['success'] ?? false)) {
                $this->error('❌ [API_ERROR] ' . ($data['message'] ?? 'Failed to retrieve logs.'));
                return self::FAILURE;
            }

            if ($isJson) {
                $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                return self::SUCCESS;
            }

            $logs = $data['logs'] ?? [];
            $this->renderHeader($data['channel'] ?? $channel, count($logs), count($logs), $level, $grep, $since);

            $chronologicalLogs = array_reverse($logs);
            foreach ($chronologicalLogs as $entry) {
                $this->renderEntry($entry, false);
            }

            $this->newLine();
            $this->info("✅ Retrieved " . count($logs) . " entries from [{$channel}] on {$remoteUrl}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ [NETWORK_ERROR] Could not connect to remote log gateway: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function renderHeader(string $channel, int $count, int $total, ?string $level, ?string $grep, ?string $since): void
    {
        $this->newLine();
        $this->info("┌──────────────────────────────────────────────────────────────┐");
        $this->info(sprintf("│  🤖 RELAYIQ AGENT LOG GATEWAY: %-30s│", strtoupper($channel)));
        $this->info("├──────────────────────────────────────────────────────────────┤");
        $this->line(sprintf("│  Showing: %-15s Filter Level: %-21s│", "{$count} / {$total}", $level ?: 'ALL'));
        if ($grep || $since) {
            $this->line(sprintf("│  Grep: %-18s Since: %-27s│", $grep ?: 'NONE', $since ?: 'ALL'));
        }
        $this->info("└──────────────────────────────────────────────────────────────┘");
        $this->newLine();
    }

    private function renderEntry(array $entry, bool $isJson): void
    {
        if ($isJson) {
            $this->line(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        $level = strtoupper($entry['level'] ?? 'INFO');
        $time  = $entry['timestamp'] ?? '';
        $msg   = $entry['message'] ?? ($entry['raw'] ?? '');
        $ch    = $entry['channel'] ?? '';

        $badge = match ($level) {
            'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY' => "<error> {$level} </error>",
            'WARNING', 'WARN'                         => "<comment> {$level} </comment>",
            'NOTICE', 'INFO'                          => "<info> {$level} </info>",
            default                                   => " [{$level}] ",
        };

        $this->line(sprintf("<fg=gray>[%s]</> %s <fg=cyan>[%s]</> %s", $time, $badge, $ch, $msg));
    }
}
