<?php

namespace App\Services\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DeployExecutionService
{
    public function __construct(
        private readonly DeployAuditLogger $audit,
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Lock management
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if a deploy is currently running.
     * Stale locks (>10 minutes old) are automatically released.
     */
    public function isLocked(): bool
    {
        $lockPath = (string) config('deploy.lock_path');

        if (! file_exists($lockPath)) {
            return false;
        }

        // Release stale lock
        if (filemtime($lockPath) < (time() - 600)) {
            @unlink($lockPath);
            return false;
        }

        return true;
    }

    private function acquireLock(string $deployToken, string $branch): void
    {
        $lockPath = (string) config('deploy.lock_path');
        $dir = dirname($lockPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($lockPath, json_encode([
            'token'      => $deployToken,
            'branch'     => $branch,
            'pid'        => getmypid(),
            'started_at' => now()->toIso8601String(),
        ]));
    }

    private function releaseLock(): void
    {
        @unlink((string) config('deploy.lock_path'));
    }

    // ─────────────────────────────────────────────────────────────
    // Status file helpers
    // ─────────────────────────────────────────────────────────────

    private function statusFile(string $deployToken): string
    {
        return rtrim((string) config('deploy.status_dir'), '/') . "/{$deployToken}.json";
    }

    private function ensureStatusDir(): void
    {
        $dir = (string) config('deploy.status_dir');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function writeStatus(string $file, string $status, string $branch, array $logs, ?float $duration = null): void
    {
        $existing = [];
        if (file_exists($file)) {
            $existing = json_decode((string) file_get_contents($file), true) ?? [];
        }

        $payload = array_merge($existing, [
            'status'     => $status,
            'branch'     => $branch,
            'logs'       => $logs,
            'updated_at' => now()->toIso8601String(),
        ]);

        if ($duration !== null) {
            $payload['duration'] = $duration;
        }

        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ─────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────

    /**
     * Spawn a deploy in the background (polling mode).
     * Returns a deploy token the client uses to poll /deploy/status/{token}.
     */
    public function startBackground(string $branch): string
    {
        $deployToken = Str::random(32);

        $this->ensureStatusDir();

        $this->writeStatus($this->statusFile($deployToken), 'pending', $branch, [
            '⏳ Deploy queued for branch: [' . $branch . '] — spawning background process…',
        ]);

        $phpBin  = DeployAuthService::findPhpBinary();
        $artisan = base_path('artisan');
        $cmd     = 'nohup ' . escapeshellarg($phpBin)
            . ' ' . escapeshellarg($artisan)
            . ' deploy:run '
            . escapeshellarg($deployToken)
            . ' ' . escapeshellarg($branch)
            . ' > /dev/null 2>&1 &';

        if (function_exists('shell_exec')) {
            @shell_exec($cmd);
        } elseif (function_exists('exec')) {
            @exec($cmd);
        } else {
            throw new \RuntimeException('Background process spawning is not supported on this host (shell_exec/exec is disabled in PHP configuration).');
        }

        return $deployToken;
    }

    /**
     * Run the deploy synchronously (fallback mode).
     * Returns the final status array directly.
     *
     * @return array<string, mixed>
     */
    public function runSynchronous(string $branch): array
    {
        $deployToken = Str::random(32);
        $this->ensureStatusDir();

        $statusFile = $this->statusFile($deployToken);
        $this->writeStatus($statusFile, 'running', $branch, []);
        $this->acquireLock($deployToken, $branch);

        $logs      = [];
        $startTime = microtime(true);
        $success   = false;
        $duration  = 0.0;

        try {
            $this->runPipeline($branch, $logs, $statusFile);
            $duration = round(microtime(true) - $startTime, 2);
            $logs[]   = "✅ [SUCCESS] Deployment completed in {$duration}s!";
            $success  = true;
            $this->writeStatus($statusFile, 'complete', $branch, $logs, $duration);
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $logs[]   = '❌ [EXCEPTION] ' . $e->getMessage();
            $this->writeStatus($statusFile, 'failed', $branch, $logs, $duration);
        } finally {
            $this->releaseLock();
            $this->audit->log([
                'token'      => $deployToken,
                'branch'     => $branch,
                'status'     => $success ? 'success' : 'failed',
                'duration'   => $duration,
                'ip'         => request()->ip(),
                'timestamp'  => now()->toIso8601String(),
            ]);
        }

        return $this->getStatus($deployToken);
    }

    /**
     * Execute the deploy pipeline (called by artisan deploy:run in background mode).
     */
    public function execute(string $deployToken, string $branch): void
    {
        $statusFile = $this->statusFile($deployToken);

        if (! file_exists($statusFile)) {
            return; // Token was cleaned up or invalid
        }

        $this->acquireLock($deployToken, $branch);

        $logs      = ['🚀 [' . date('Y-m-d H:i:s') . "] Starting deployment for branch: [{$branch}]"];
        $startTime = microtime(true);
        $success   = false;
        $duration  = 0.0;

        try {
            $this->writeStatus($statusFile, 'running', $branch, $logs);
            $this->runPipeline($branch, $logs, $statusFile);
            $duration = round(microtime(true) - $startTime, 2);
            $logs[]   = "✅ [SUCCESS] Deployment completed in {$duration}s!";
            $success  = true;
            $this->writeStatus($statusFile, 'complete', $branch, $logs, $duration);
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $logs[]   = '❌ [EXCEPTION] ' . $e->getMessage();
            $this->writeStatus($statusFile, 'failed', $branch, $logs, $duration);
        } finally {
            $this->releaseLock();
            $this->audit->log([
                'token'     => $deployToken,
                'branch'    => $branch,
                'status'    => $success ? 'success' : 'failed',
                'duration'  => $duration,
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    /**
     * Return the current deploy status for a given deploy token.
     *
     * @return array<string, mixed>
     */
    public function getStatus(string $deployToken): array
    {
        $statusFile = $this->statusFile($deployToken);

        if (! file_exists($statusFile)) {
            return ['status' => 'not_found', 'logs' => []];
        }

        $data = json_decode((string) file_get_contents($statusFile), true);

        return is_array($data) ? $data : ['status' => 'error', 'logs' => ['❌ Failed to read status file.']];
    }

    // ─────────────────────────────────────────────────────────────
    // Pipeline internals
    // ─────────────────────────────────────────────────────────────

    /**
     * Core deploy pipeline. Mutates $logs and flushes them line-by-line to $statusFile.
     *
     * @param  string[]  $logs
     */
    /**
     * Core deploy pipeline. Mutates $logs and flushes them line-by-line to $statusFile.
     *
     * @param  string[]  $logs
     */
    private function runPipeline(string $branch, array &$logs, string $statusFile, ?callable $onLine = null): void
    {
        $deployScript = (string) config('deploy.script_path');

        $emitLine = function (string $line) use (&$logs, $statusFile, $branch, $onLine) {
            $logs[] = $line;
            $this->flushLogs($statusFile, 'running', $branch, $logs);
            if ($onLine !== null) {
                $onLine($line);
            }
        };

        if (is_file($deployScript) && is_executable($deployScript)) {
            // ── cPanel shell script path ──────────────────────────────
            $emitLine("⚡ Running deploy pipeline: {$deployScript}");

            $this->runStreamingCommand($deployScript . ' ' . escapeshellarg($branch) . ' 2>&1', $logs, $statusFile, $branch, $onLine);
        } else {
            // ── Built-in git + artisan fallback ───────────────────────
            $repoRoot = base_path('..');
            if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                $repoRoot = base_path();
            }

            $emitLine("📂 Project root: {$repoRoot}");

            // Step 1: Git fetch
            $emitLine("📥 [git fetch] Fetching branch [{$branch}]...");
            $this->runStreamingCommand('cd ' . escapeshellarg($repoRoot) . ' && git fetch origin ' . escapeshellarg($branch) . ' 2>&1', $logs, $statusFile, $branch, $onLine);

            // Step 2: Git reset
            $emitLine("🔄 [git reset] Resetting to origin/{$branch}...");
            $this->runStreamingCommand('cd ' . escapeshellarg($repoRoot) . ' && git reset --hard origin/' . escapeshellarg($branch) . ' 2>&1', $logs, $statusFile, $branch, $onLine);

            // Step 3: Database migrations
            $emitLine("🗄️  [migrate] Running database migrations...");
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrateOut = trim(Artisan::output());
                if ($migrateOut !== '') {
                    foreach (explode("\n", $migrateOut) as $mLine) {
                        $mTrim = trim($mLine);
                        if ($mTrim !== '') {
                            $emitLine($mTrim);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $emitLine('⚠️  [migrate]: ' . $e->getMessage());
            }

            // Step 4: Storage link
            try {
                Artisan::call('storage:link');
                $emitLine('🔗 [storage:link]: Storage symlink active');
            } catch (\Throwable) {
                $emitLine('🔗 [storage:link]: Already linked');
            }

            // Step 5: Cache optimisation
            try {
                Artisan::call('optimize:clear');
                $emitLine('🧹 [optimize:clear]: Cleared configuration, routes, and views');

                Artisan::call('optimize');
                $emitLine('⚡ [optimize]: Compiled bootstrap cache');

                Artisan::call('view:cache');
                $emitLine('👁️  [view:cache]: Blade views compiled');

                Artisan::call('event:cache');
                $emitLine('📡 [event:cache]: Events and listeners cached');
            } catch (\Throwable $e) {
                $emitLine('⚠️  [cache]: ' . $e->getMessage());
            }
        }
    }

    /**
     * Run the deploy with real-time callback for each log line (for HTTP Server-Sent Events).
     *
     * @return array<string, mixed>
     */
    public function runStreamed(string $branch, callable $onLine): array
    {
        $deployToken = Str::random(32);
        $this->ensureStatusDir();

        $statusFile = $this->statusFile($deployToken);
        $this->writeStatus($statusFile, 'running', $branch, []);
        $this->acquireLock($deployToken, $branch);

        $logs      = [];
        $startTime = microtime(true);
        $success   = false;
        $duration  = 0.0;

        try {
            $this->runPipeline($branch, $logs, $statusFile, $onLine);
            $duration = round(microtime(true) - $startTime, 2);
            $successLine = "✅ [SUCCESS] Deployment completed in {$duration}s!";
            $logs[]   = $successLine;
            $onLine($successLine);
            $success  = true;
            $this->writeStatus($statusFile, 'complete', $branch, $logs, $duration);
        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $errLine = '❌ [EXCEPTION] ' . $e->getMessage();
            $logs[]  = $errLine;
            $onLine($errLine);
            $this->writeStatus($statusFile, 'failed', $branch, $logs, $duration);
        } finally {
            $this->releaseLock();
            $this->audit->log([
                'token'      => $deployToken,
                'branch'     => $branch,
                'status'     => $success ? 'success' : 'failed',
                'duration'   => $duration,
                'ip'         => request()->ip(),
                'timestamp'  => now()->toIso8601String(),
            ]);
        }

        return [
            'success'  => $success,
            'status'   => $success ? 'complete' : 'failed',
            'duration' => $duration,
            'logs'     => $logs,
        ];
    }

    /**
     * Write current log state to the status file without changing status.
     *
     * @param  string[]  $logs
     */
    private function flushLogs(string $file, string $status, string $branch, array $logs): void
    {
        if (! file_exists($file)) {
            return;
        }
        $this->writeStatus($file, $status, $branch, $logs);
    }

    /**
     * Run a command and stream its output lines in real-time into $logs and $statusFile.
     *
     * @param  string[]  $logs
     */
    private function runStreamingCommand(string $cmd, array &$logs, string $statusFile, string $branch, ?callable $onLine = null): void
    {
        $buffer = '';
        $onOutput = function (string $chunk) use (&$logs, &$buffer, $statusFile, $branch, $onLine) {
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines) ?? '';
            $flushed = false;
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $logs[] = $trimmed;
                    $flushed = true;
                    if ($onLine !== null) {
                        $onLine($trimmed);
                    }
                }
            }
            if ($flushed) {
                $this->flushLogs($statusFile, 'running', $branch, $logs);
            }
        };

        // Method 1: Symfony Process with live output callback
        if (class_exists(Process::class)) {
            try {
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(null);
                $process->run(function ($type, $chunk) use ($onOutput) {
                    $onOutput($chunk);
                });
                if (trim($buffer) !== '') {
                    $trimmed = trim($buffer);
                    $logs[] = $trimmed;
                    if ($onLine !== null) {
                        $onLine($trimmed);
                    }
                    $this->flushLogs($statusFile, 'running', $branch, $logs);
                }
                return;
            } catch (\Throwable) {}
        }

        // Method 2: proc_open with non-blocking stream reads
        if (function_exists('proc_open')) {
            try {
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $proc = proc_open($cmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);

                    while (true) {
                        $read = [$pipes[1], $pipes[2]];
                        $write = null;
                        $except = null;
                        $changed = stream_select($read, $write, $except, 0, 50000);
                        if ($changed > 0) {
                            foreach ($read as $pipe) {
                                $chunk = fread($pipe, 4096);
                                if ($chunk !== false && strlen($chunk) > 0) {
                                    $onOutput($chunk);
                                }
                            }
                        }

                        $procStatus = proc_get_status($proc);
                        if (! $procStatus['running']) {
                            $rem1 = stream_get_contents($pipes[1]);
                            if ($rem1) $onOutput($rem1);
                            $rem2 = stream_get_contents($pipes[2]);
                            if ($rem2) $onOutput($rem2);
                            break;
                        }
                    }

                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($proc);

                    if (trim($buffer) !== '') {
                        $trimmed = trim($buffer);
                        $logs[] = $trimmed;
                        if ($onLine !== null) {
                            $onLine($trimmed);
                        }
                        $this->flushLogs($statusFile, 'running', $branch, $logs);
                    }
                    return;
                }
            } catch (\Throwable) {}
        }

        // Method 3: shell_exec / exec / system fallback
        $out = '';
        if (function_exists('shell_exec')) {
            $out = (string) @shell_exec($cmd);
        } elseif (function_exists('exec')) {
            $lines = [];
            @exec($cmd, $lines);
            $out = implode("\n", $lines);
        } elseif (function_exists('system')) {
            ob_start();
            @system($cmd);
            $out = (string) ob_get_clean();
        } elseif (function_exists('passthru')) {
            ob_start();
            @passthru($cmd);
            $out = (string) ob_get_clean();
        }

        if ($out !== '') {
            foreach (explode("\n", trim($out)) as $l) {
                $lTrim = trim($l);
                if ($lTrim !== '') {
                    $logs[] = $lTrim;
                    if ($onLine !== null) {
                        $onLine($lTrim);
                    }
                }
            }
            $this->flushLogs($statusFile, 'running', $branch, $logs);
        }
    }
}
