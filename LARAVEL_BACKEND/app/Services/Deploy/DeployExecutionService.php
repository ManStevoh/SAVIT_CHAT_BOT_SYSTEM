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

        $phpBin  = PHP_BINARY;
        $artisan = base_path('artisan');
        $cmd     = escapeshellarg($phpBin)
            . ' ' . escapeshellarg($artisan)
            . ' deploy:run '
            . escapeshellarg($deployToken)
            . ' ' . escapeshellarg($branch)
            . ' > /dev/null 2>&1 &';

        @shell_exec($cmd);

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
     * Core deploy pipeline. Mutates $logs and periodically flushes them to $statusFile.
     *
     * @param  string[]  $logs
     */
    private function runPipeline(string $branch, array &$logs, string $statusFile): void
    {
        $deployScript = (string) config('deploy.script_path');

        if (is_file($deployScript) && is_executable($deployScript)) {
            // ── cPanel shell script path ──────────────────────────────
            $logs[] = "⚡ Running deploy pipeline: {$deployScript}";
            $this->flushLogs($statusFile, 'running', $branch, $logs);

            $output = $this->runCmd($deployScript . ' ' . escapeshellarg($branch) . ' 2>&1');

            foreach (explode("\n", trim((string) $output)) as $line) {
                if (trim($line) !== '') {
                    $logs[] = trim($line);
                }
            }
        } else {
            // ── Built-in git + artisan fallback ───────────────────────
            $repoRoot = base_path('..');
            if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                $repoRoot = base_path();
            }

            $logs[] = "📂 Project root: {$repoRoot}";
            $this->flushLogs($statusFile, 'running', $branch, $logs);

            // Step 1: Git fetch — SAFE: branch is sanitised by caller
            $out    = $this->runCmd('cd ' . escapeshellarg($repoRoot) . ' && git fetch origin ' . escapeshellarg($branch) . ' 2>&1');
            $logs[] = '📥 [git fetch]: ' . trim((string) $out);
            $this->flushLogs($statusFile, 'running', $branch, $logs);

            // Step 2: Git reset — SAFE: branch is sanitised by caller
            $out    = $this->runCmd('cd ' . escapeshellarg($repoRoot) . ' && git reset --hard origin/' . escapeshellarg($branch) . ' 2>&1');
            $logs[] = '🔄 [git reset]: ' . trim((string) $out);
            $this->flushLogs($statusFile, 'running', $branch, $logs);

            // Step 3: Database migrations
            try {
                Artisan::call('migrate', ['--force' => true]);
                $logs[] = '🗄️  [migrate]: ' . trim(Artisan::output());
            } catch (\Throwable $e) {
                $logs[] = '⚠️  [migrate]: ' . $e->getMessage();
            }
            $this->flushLogs($statusFile, 'running', $branch, $logs);

            // Step 4: Storage link
            try {
                Artisan::call('storage:link');
                $logs[] = '🔗 [storage:link]: Link active';
            } catch (\Throwable) {
                $logs[] = '🔗 [storage:link]: Already linked';
            }

            // Step 5: Cache optimisation
            try {
                Artisan::call('optimize:clear');
                $logs[] = '🧹 [optimize:clear]: Cleared';

                Artisan::call('optimize');
                $logs[] = '⚡ [optimize]: Bootstraps compiled';

                Artisan::call('view:cache');
                $logs[] = '👁️  [view:cache]: Views compiled';

                Artisan::call('event:cache');
                $logs[] = '📡 [event:cache]: Events compiled';
            } catch (\Throwable $e) {
                $logs[] = '⚠️  [cache]: ' . $e->getMessage();
            }
            $this->flushLogs($statusFile, 'running', $branch, $logs);
        }
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
     * Robust command runner: Symfony Process → proc_open → shell_exec.
     */
    private function runCmd(string $cmd): string
    {
        // Method 1: Symfony Process (preferred — no timeout)
        try {
            if (class_exists(Process::class)) {
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(null);
                $process->run();
                return $process->getOutput() . $process->getErrorOutput();
            }
        } catch (\Throwable) {}

        // Method 2: proc_open
        try {
            if (function_exists('proc_open')) {
                $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                $proc = proc_open($cmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $out = stream_get_contents($pipes[1]);
                    $err = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    proc_close($proc);
                    return (string) ($out . $err);
                }
            }
        } catch (\Throwable) {}

        // Method 3: shell_exec (last resort)
        return (string) @shell_exec($cmd);
    }
}
