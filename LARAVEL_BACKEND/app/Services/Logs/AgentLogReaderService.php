<?php

namespace App\Services\Logs;

use App\Models\AiRequestLog;
use App\Models\SystemLog;
use Carbon\Carbon;
use Throwable;

class AgentLogReaderService
{
    public const DEFAULT_LINES = 50;
    public const MAX_LINES = 500;

    /**
     * @var array<string, string>
     */
    private array $fileChannels = [];

    public function __construct()
    {
        $this->fileChannels = [
            'laravel'  => storage_path('logs/laravel.log'),
            'whatsapp' => storage_path('logs/whatsapp_debug.log'),
            'agent'    => storage_path('logs/agent-debug.log'),
            'deploy'   => storage_path('logs/deploy.log'),
            'migrate'  => storage_path('logs/migrate-cron.log'),
        ];
    }

    /**
     * Query and return structured log entries according to filters.
     *
     * @return array{channel: string, count: int, total_available: int, logs: array<int, array<string, mixed>>}
     */
    public function read(
        string $channel = 'laravel',
        ?string $level = null,
        int $lines = self::DEFAULT_LINES,
        ?string $grep = null,
        ?string $since = null
    ): array {
        $lines = max(1, min(self::MAX_LINES, $lines));
        $level = ! empty($level) && $level !== 'all' ? strtolower(trim($level)) : null;
        $grep  = ! empty($grep) ? trim($grep) : null;
        $sinceTimestamp = $this->parseSinceTimestamp($since);

        if ($channel === 'system_db') {
            return $this->readSystemDatabaseLogs($lines, $level, $grep, $since);
        }

        if ($channel === 'ai_requests') {
            return $this->readAiRequestLogs($lines, $level, $grep, $since);
        }

        if ($channel === 'all') {
            return $this->readAllChannels($lines, $level, $grep, $sinceTimestamp);
        }

        $filePath = $this->fileChannels[$channel] ?? $this->fileChannels['laravel'];
        $actualChannel = isset($this->fileChannels[$channel]) ? $channel : 'laravel';

        return $this->tailFile($filePath, $lines, $level, $grep, $sinceTimestamp, $actualChannel);
    }

    /**
     * Reverse-seek a log file from end-of-file to avoid memory exhaustion on large files.
     *
     * @return array{channel: string, count: int, total_available: int, logs: array<int, array<string, mixed>>}
     */
    public function tailFile(
        string $filePath,
        int $lines = self::DEFAULT_LINES,
        ?string $level = null,
        ?string $grep = null,
        ?int $sinceTimestamp = null,
        string $channelName = 'laravel'
    ): array {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return [
                'channel'         => $channelName,
                'count'           => 0,
                'total_available' => 0,
                'logs'            => [],
            ];
        }

        $fp = @fopen($filePath, 'rb');
        if (! $fp) {
            return [
                'channel'         => $channelName,
                'count'           => 0,
                'total_available' => 0,
                'logs'            => [],
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            fclose($fp);
            return [
                'channel'         => $channelName,
                'count'           => 0,
                'total_available' => 0,
                'logs'            => [],
            ];
        }

        $chunkSize = 8192;
        $pos = $fileSize;
        $buffer = '';
        $matchedEntries = [];
        $totalParsed = 0;

        while ($pos > 0 && count($matchedEntries) < $lines) {
            $readLength = min($chunkSize, $pos);
            $pos -= $readLength;
            fseek($fp, $pos);
            $chunk = fread($fp, $readLength);
            $buffer = $chunk . $buffer;

            $rawLines = explode("\n", $buffer);
            // Keep the first (likely incomplete) segment for the next chunk iteration
            $buffer = array_shift($rawLines);

            // Process lines in reverse (newest first)
            for ($i = count($rawLines) - 1; $i >= 0; $i--) {
                $rawLine = trim($rawLines[$i]);
                if ($rawLine === '') {
                    continue;
                }

                $totalParsed++;
                $parsed = $this->parseLogLine($rawLine, $channelName);
                if (! $parsed) {
                    continue;
                }

                if ($sinceTimestamp !== null && $parsed['timestamp_unix'] < $sinceTimestamp) {
                    // Reached entries older than requested window
                    $pos = 0; // stop reading further backwards
                    break;
                }

                if ($level !== null && strtolower($parsed['level']) !== $level) {
                    continue;
                }

                if ($grep !== null && ! $this->matchesGrep($rawLine, $grep)) {
                    continue;
                }

                $parsed['message'] = LogDataScrubber::scrub($parsed['message']);
                $matchedEntries[] = $parsed;

                if (count($matchedEntries) >= $lines) {
                    break;
                }
            }
        }

        // Process leftover buffer if any and quota not reached
        if (! empty($buffer) && count($matchedEntries) < $lines) {
            $parsed = $this->parseLogLine(trim($buffer), $channelName);
            if ($parsed && ($sinceTimestamp === null || $parsed['timestamp_unix'] >= $sinceTimestamp)) {
                $levelMatches = ($level === null || strtolower($parsed['level']) === $level);
                $grepMatches  = ($grep === null || $this->matchesGrep($buffer, $grep));
                if ($levelMatches && $grepMatches) {
                    $parsed['message'] = LogDataScrubber::scrub($parsed['message']);
                    $matchedEntries[] = $parsed;
                }
            }
        }

        fclose($fp);

        return [
            'channel'         => $channelName,
            'count'           => count($matchedEntries),
            'total_available' => $totalParsed,
            'logs'            => $matchedEntries,
        ];
    }

    /**
     * Stream new log lines as they appear (Live SSE tailing).
     */
    public function tailStream(
        string $channel,
        callable $onLine,
        int $durationSeconds = 60,
        ?string $level = null,
        ?string $grep = null
    ): void {
        $filePath = $this->fileChannels[$channel] ?? $this->fileChannels['laravel'];
        if (! file_exists($filePath)) {
            @touch($filePath);
        }

        $fp = @fopen($filePath, 'rb');
        if (! $fp) {
            return;
        }

        // Start from end of file
        fseek($fp, 0, SEEK_END);
        $deadline = time() + $durationSeconds;

        while (time() < $deadline) {
            $line = fgets($fp);
            if ($line !== false) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $parsed = $this->parseLogLine($trimmed, $channel);
                    if ($parsed) {
                        if ($level !== null && strtolower($parsed['level']) !== strtolower($level)) {
                            continue;
                        }
                        if ($grep !== null && ! $this->matchesGrep($trimmed, $grep)) {
                            continue;
                        }
                        $parsed['message'] = LogDataScrubber::scrub($parsed['message']);
                        $onLine($parsed);
                    }
                }
            } else {
                usleep(250_000); // 250ms sleep between polls
            }
        }

        fclose($fp);
    }

    /**
     * Query `system_logs` database table.
     *
     * @return array{channel: string, count: int, total_available: int, logs: array<int, array<string, mixed>>}
     */
    private function readSystemDatabaseLogs(int $lines, ?string $level, ?string $grep, ?string $since): array
    {
        try {
            $query = SystemLog::query();

            if ($level !== null) {
                $query->where('type', $level);
            }

            if ($grep !== null) {
                $query->where(function ($q) use ($grep) {
                    $q->where('message', 'like', "%{$grep}%")
                      ->orWhere('source', 'like', "%{$grep}%")
                      ->orWhere('details', 'like', "%{$grep}%");
                });
            }

            if ($since !== null) {
                $sinceTime = $this->parseSinceCarbon($since);
                if ($sinceTime) {
                    $query->where('created_at', '>=', $sinceTime);
                }
            }

            $total = $query->count();
            $records = $query->orderByDesc('created_at')->limit($lines)->get();

            $logs = $records->map(function (SystemLog $log) {
                return [
                    'timestamp'      => $log->created_at?->toIso8601String() ?? '',
                    'timestamp_unix' => $log->created_at?->timestamp ?? 0,
                    'level'          => strtoupper($log->type ?: 'INFO'),
                    'channel'        => 'system_db',
                    'message'        => LogDataScrubber::scrub($log->message),
                    'source'         => $log->source,
                    'details'        => LogDataScrubber::scrub((string) $log->details),
                ];
            })->all();

            return [
                'channel'         => 'system_db',
                'count'           => count($logs),
                'total_available' => $total,
                'logs'            => $logs,
            ];
        } catch (Throwable $e) {
            return [
                'channel'         => 'system_db',
                'count'           => 0,
                'total_available' => 0,
                'logs'            => [],
                'error'           => $e->getMessage(),
            ];
        }
    }

    /**
     * Query `ai_request_logs` database table.
     *
     * @return array{channel: string, count: int, total_available: int, logs: array<int, array<string, mixed>>}
     */
    private function readAiRequestLogs(int $lines, ?string $level, ?string $grep, ?string $since): array
    {
        try {
            $query = AiRequestLog::query();

            if ($level === 'error') {
                $query->where('success', false);
            } elseif ($level === 'info') {
                $query->where('success', true);
            }

            if ($grep !== null) {
                $query->where(function ($q) use ($grep) {
                    $q->where('model', 'like', "%{$grep}%")
                      ->orWhere('use_case', 'like', "%{$grep}%")
                      ->orWhere('error_message', 'like', "%{$grep}%");
                });
            }

            if ($since !== null) {
                $sinceTime = $this->parseSinceCarbon($since);
                if ($sinceTime) {
                    $query->where('created_at', '>=', $sinceTime);
                }
            }

            $total = $query->count();
            $records = $query->orderByDesc('created_at')->limit($lines)->get();

            $logs = $records->map(function (AiRequestLog $req) {
                return [
                    'id'                => $req->id,
                    'timestamp'         => $req->created_at?->toIso8601String() ?? '',
                    'timestamp_unix'    => $req->created_at?->timestamp ?? 0,
                    'level'             => $req->success ? 'INFO' : 'ERROR',
                    'channel'           => 'ai_requests',
                    'model'             => $req->model,
                    'use_case'          => $req->use_case,
                    'latency_ms'        => $req->latency_ms,
                    'tokens'            => $req->total_tokens,
                    'cost_usd'          => $req->billed_cost_usd ?? $req->estimated_cost_usd,
                    'success'           => (bool) $req->success,
                    'http_status'       => $req->http_status,
                    'message'           => $req->success
                        ? "AI [{$req->model}] ({$req->use_case}) completed in {$req->latency_ms}ms"
                        : "AI [{$req->model}] failed ({$req->http_status}): " . LogDataScrubber::scrub((string) $req->error_message),
                ];
            })->all();

            return [
                'channel'         => 'ai_requests',
                'count'           => count($logs),
                'total_available' => $total,
                'logs'            => $logs,
            ];
        } catch (Throwable $e) {
            return [
                'channel'         => 'ai_requests',
                'count'           => 0,
                'total_available' => 0,
                'logs'            => [],
                'error'           => $e->getMessage(),
            ];
        }
    }

    /**
     * Read and merge recent logs across all file channels.
     *
     * @return array{channel: string, count: int, total_available: int, logs: array<int, array<string, mixed>>}
     */
    private function readAllChannels(int $lines, ?string $level, ?string $grep, ?int $sinceTimestamp): array
    {
        $allLogs = [];
        $totalParsed = 0;

        foreach ($this->fileChannels as $channelName => $filePath) {
            $result = $this->tailFile($filePath, $lines, $level, $grep, $sinceTimestamp, $channelName);
            $totalParsed += $result['total_available'];
            foreach ($result['logs'] as $log) {
                $allLogs[] = $log;
            }
        }

        // Sort unified entries descending by timestamp
        usort($allLogs, fn ($a, $b) => ($b['timestamp_unix'] ?? 0) <=> ($a['timestamp_unix'] ?? 0));
        $sliced = array_slice($allLogs, 0, $lines);

        return [
            'channel'         => 'all',
            'count'           => count($sliced),
            'total_available' => $totalParsed,
            'logs'            => $sliced,
        ];
    }

    /**
     * Parse various log line formats (Monolog, WhatsApp debug, JSON-L).
     *
     * @return array{timestamp: string, timestamp_unix: int, level: string, channel: string, message: string, raw: string}|null
     */
    public function parseLogLine(string $line, string $channelName): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return null;
        }

        // 1. JSON-L Format (e.g. deploy.log)
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $json = json_decode($trimmed, true);
            if (is_array($json)) {
                $timeStr = $json['started_at'] ?? $json['timestamp'] ?? $json['created_at'] ?? now()->toIso8601String();
                $timeUnix = strtotime($timeStr) ?: time();
                $level = strtoupper($json['status'] ?? $json['level'] ?? 'INFO');
                return [
                    'timestamp'      => date('c', $timeUnix),
                    'timestamp_unix' => $timeUnix,
                    'level'          => $level === 'SUCCESS' ? 'INFO' : ($level === 'FAILURE' ? 'ERROR' : $level),
                    'channel'        => $channelName,
                    'message'        => json_encode(LogDataScrubber::scrubArray($json), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'raw'            => $trimmed,
                ];
            }
        }

        // 2. WhatsApp Debug Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [STAGE] {context}
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+\[([A-Z]+)\]\s+\[(.*?)\]\s*(.*)$/', $trimmed, $m)) {
            $timeUnix = strtotime($m[1]) ?: time();
            return [
                'timestamp'      => date('c', $timeUnix),
                'timestamp_unix' => $timeUnix,
                'level'          => strtoupper($m[2]),
                'channel'        => $channelName,
                'stage'          => $m[3],
                'message'        => "[{$m[3]}] " . trim($m[4]),
                'raw'            => $trimmed,
            ];
        }

        // 3. Monolog Format: [YYYY-MM-DD HH:MM:SS] environment.LEVEL: message {context}
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_\-]+)\.([A-Z]+):\s+(.*)$/s', $trimmed, $m)) {
            $timeUnix = strtotime($m[1]) ?: time();
            return [
                'timestamp'      => date('c', $timeUnix),
                'timestamp_unix' => $timeUnix,
                'level'          => strtoupper($m[3]),
                'channel'        => $channelName,
                'message'        => trim($m[4]),
                'raw'            => $trimmed,
            ];
        }

        // 4. Fallback line
        return [
            'timestamp'      => now()->toIso8601String(),
            'timestamp_unix' => time(),
            'level'          => 'INFO',
            'channel'        => $channelName,
            'message'        => $trimmed,
            'raw'            => $trimmed,
        ];
    }

    private function matchesGrep(string $line, string $grep): bool
    {
        if (str_starts_with($grep, '/') && str_ends_with($grep, '/')) {
            return (bool) @preg_match($grep, $line);
        }

        return stripos($line, $grep) !== false;
    }

    private function parseSinceTimestamp(?string $since): ?int
    {
        if (empty($since)) {
            return null;
        }

        $carbon = $this->parseSinceCarbon($since);
        return $carbon?->timestamp;
    }

    private function parseSinceCarbon(?string $since): ?Carbon
    {
        if (empty($since)) {
            return null;
        }

        $since = trim($since);

        // Relative shorthand: 15m, 1h, 24h, 7d
        if (preg_match('/^(\d+)\s*([mhdw])$/i', $since, $m)) {
            $val = (int) $m[1];
            $unit = strtolower($m[2]);
            return match ($unit) {
                'm' => now()->subMinutes($val),
                'h' => now()->subHours($val),
                'd' => now()->subDays($val),
                'w' => now()->subWeeks($val),
                default => null,
            };
        }

        try {
            return Carbon::parse($since);
        } catch (Throwable) {
            return null;
        }
    }
}
