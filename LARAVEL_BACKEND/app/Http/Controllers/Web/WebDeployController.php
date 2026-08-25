<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class WebDeployController extends Controller
{
    private const DEFAULT_SECRET = 'essem@2030';

    /**
     * Render the locked deployment login screen
     */
    public function index(Request $request): Response
    {
        return response($this->renderDeployHtml(), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Authenticate and return available branches ONLY to authenticated users
     */
    public function auth(Request $request): JsonResponse
    {
        $secret = (string) $request->input('secret', '');
        $expectedSecret = env('DEPLOY_SECRET', self::DEFAULT_SECRET);

        if (! hash_equals($expectedSecret, $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid deployment password.',
            ], 403);
        }

        $branches = $this->getAvailableBranches();

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully.',
            'branches' => $branches,
        ]);
    }

    /**
     * Handle deployment execution
     */
    public function deploy(Request $request): JsonResponse
    {
        $secret = (string) ($request->input('secret') ?: $request->header('X-Deploy-Secret', ''));
        $branch = (string) ($request->input('custom_branch') ?: $request->input('branch', 'main'));

        return $this->executeDeploy($secret, $branch);
    }

    private function getAvailableBranches(): array
    {
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
            $repoRoot = base_path('..');
            if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                $repoRoot = base_path();
            }

            $output = $this->runCmd("cd {$repoRoot} && git branch -r 2>&1");
            if ($output) {
                $lines = explode("\n", (string) $output);
                foreach ($lines as $line) {
                    $clean = trim($line);
                    if (str_contains($clean, '->') || ! str_starts_with($clean, 'origin/')) {
                        continue;
                    }
                    $name = str_replace('origin/', '', $clean);
                    if ($name && ! in_array($name, $branches, true)) {
                        $branches[] = $name;
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback list
        }

        usort($branches, function ($a, $b) {
            if ($a === 'main') return -1;
            if ($b === 'main') return 1;
            return strcmp($a, $b);
        });

        return array_values(array_unique($branches));
    }

    private function executeDeploy(string $secret, string $branch): JsonResponse
    {
        try {
            $expectedSecret = env('DEPLOY_SECRET', self::DEFAULT_SECRET);

            if (! hash_equals($expectedSecret, $secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Invalid deploy password.',
                    'logs' => ['[AUTH_ERROR] Invalid password provided.'],
                ], 403);
            }

            $cleanBranch = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $branch) ?: 'main';
            $logs = [];
            $startTime = microtime(true);

            $logs[] = '🚀 [' . date('Y-m-d H:i:s') . "] Starting deployment for branch: [{$cleanBranch}]";

            $deployScript = '/home/qkbghwib/deploy';
            if (is_file($deployScript) && is_executable($deployScript)) {
                $logs[] = "⚡ Running deploy pipeline: {$deployScript}";
                $output = $this->runCmd("{$deployScript} " . escapeshellarg($cleanBranch) . " 2>&1");
                if ($output) {
                    $lines = explode("\n", trim((string) $output));
                    foreach ($lines as $line) {
                        if (trim($line) !== '') {
                            $logs[] = trim($line);
                        }
                    }
                }
            } else {
                $repoRoot = base_path('..');
                if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                    $repoRoot = base_path();
                }

                $logs[] = "📂 Project root: {$repoRoot}";

                // Step 1: Git fetch
                $fetchOutput = $this->runCmd("cd {$repoRoot} && git fetch origin {$cleanBranch} 2>&1");
                $logs[] = '📥 [git fetch]: ' . trim((string) $fetchOutput);

                // Step 2: Git reset
                $resetOutput = $this->runCmd("cd {$repoRoot} && git reset --hard origin/{$cleanBranch} 2>&1");
                $logs[] = '🔄 [git reset]: ' . trim((string) $resetOutput);

                // Step 3: Database migrations
                try {
                    Artisan::call('migrate', ['--force' => true]);
                    $logs[] = '🗄️ [migrate]: ' . trim(Artisan::output());
                } catch (\Throwable $e) {
                    $logs[] = '⚠️ [migrate]: ' . $e->getMessage();
                }

                // Step 4: Storage link
                try {
                    Artisan::call('storage:link');
                    $logs[] = '🔗 [storage:link]: Link active';
                } catch (\Throwable) {
                    $logs[] = '🔗 [storage:link]: Link active';
                }

                // Step 5: Production caches
                try {
                    Artisan::call('optimize:clear');
                    $logs[] = '🧹 [optimize:clear]: ' . trim(Artisan::output());

                    Artisan::call('optimize');
                    $logs[] = '⚡ [optimize]: ' . trim(Artisan::output());

                    Artisan::call('view:cache');
                    $logs[] = '👁️ [view:cache]: Views compiled';

                    Artisan::call('event:cache');
                    $logs[] = '📡 [event:cache]: Events compiled';
                } catch (\Throwable $e) {
                    $logs[] = '⚠️ [cache]: ' . $e->getMessage();
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $logs[] = "✅ [SUCCESS] Deployment completed in {$duration}s!";

            return response()->json([
                'success' => true,
                'duration' => "{$duration}s",
                'branch' => $cleanBranch,
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deployment exception: ' . $e->getMessage(),
                'logs' => [
                    '❌ Error: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Robust command execution supporting Symfony Process, proc_open, exec, and popen
     */
    private function runCmd(string $cmd): string
    {
        // Method 1: Symfony Process
        try {
            if (class_exists(Process::class)) {
                $process = Process::fromShellCommandline($cmd);
                $process->setTimeout(60);
                $process->run();
                return $process->getOutput() . $process->getErrorOutput();
            }
        } catch (\Throwable) {}

        // Method 2: proc_open
        try {
            if (function_exists('proc_open')) {
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
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

        // Method 3: exec
        try {
            if (function_exists('exec')) {
                $out = [];
                @exec($cmd, $out);
                return implode("\n", $out);
            }
        } catch (\Throwable) {}

        // Method 4: popen
        try {
            if (function_exists('popen')) {
                $handle = @popen($cmd, 'r');
                if ($handle) {
                    $read = stream_get_contents($handle);
                    @pclose($handle);
                    return (string) $read;
                }
            }
        } catch (\Throwable) {}

        // Method 5: shell_exec (if allowed)
        try {
            if (function_exists('shell_exec')) {
                return (string) @shell_exec($cmd);
            }
        } catch (\Throwable) {}

        return '';
    }

    private function renderDeployHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>RelayIQ — Web Deploy Console</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,600,700|geist-mono:400,600&display=swap" rel="stylesheet" />
    <style>
        :root {
            --bg: #090d16;
            --card: #111827;
            --border: #1f293d;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --success: #10b981;
            --danger: #ef4444;
            --text: #f3f4f6;
            --text-muted: #9ca3af;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 620px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            overflow: hidden;
        }
        .header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: rgba(37, 99, 235, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .title { font-size: 18px; font-weight: 700; color: #fff; }
        .subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .body { padding: 24px; display: flex; flex-direction: column; gap: 18px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; font-weight: 600; color: var(--text-muted); }
        input, select {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border);
            color: #fff;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border 0.15s;
        }
        select option {
            background: #111827;
            color: #fff;
            padding: 8px;
        }
        input:focus, select:focus { border-color: var(--primary); }
        .btn {
            background: var(--primary);
            color: #fff;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s;
        }
        .btn:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .terminal {
            display: none;
            margin-top: 8px;
            background: #05070c;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            font-family: 'Geist Mono', monospace;
            font-size: 12.5px;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
            color: #d1d5db;
        }
        .terminal.active { display: block; }
        .log-line { margin-bottom: 4px; word-break: break-all; }
        .log-line.success { color: var(--success); font-weight: 600; }
        .log-line.error { color: var(--danger); font-weight: 600; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
            margin-left: auto;
        }
        .pulse-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--success); }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">🚀</div>
            <div>
                <h1 class="title">RelayIQ Web Deployer</h1>
                <p id="headerSubtitle" class="subtitle">Enter credentials to unlock console</p>
            </div>
            <div id="statusBadge" class="badge">
                <div class="pulse-dot"></div>
                <span>Locked</span>
            </div>
        </div>
        <div class="body">
            <!-- Step 1: Login Form -->
            <form id="authForm" onsubmit="handleAuth(event)">
                <div class="field" style="margin-bottom: 18px;">
                    <label for="authSecret">Deployment Password</label>
                    <input type="password" id="authSecret" placeholder="Enter password to unlock" required autofocus autocomplete="current-password" />
                </div>
                <button type="submit" id="authBtn" class="btn">
                    <span>🔓 Unlock Deployment Console</span>
                </button>
            </form>

            <!-- Step 2: Deployment Console (Revealed ONLY after login) -->
            <form id="deployForm" class="hidden" onsubmit="handleDeploy(event)">
                <div class="field" style="margin-bottom: 14px;">
                    <label for="branch">Select Target Branch</label>
                    <select id="branch" name="branch" onchange="toggleCustomBranch(this)">
                        <!-- Populated dynamically upon auth -->
                    </select>
                </div>
                <div id="customBranchField" class="field" style="margin-bottom: 14px; display: none;">
                    <label for="custom_branch">Custom Branch Name</label>
                    <input type="text" id="custom_branch" name="custom_branch" placeholder="e.g. feature/my-new-feature" />
                </div>
                <button type="submit" id="deployBtn" class="btn">
                    <span>⚡ Deploy to Live Site</span>
                </button>
            </form>

            <div id="terminal" class="terminal"></div>
        </div>
    </div>

    <script>
        let authenticatedSecret = '';

        async function handleAuth(e) {
            e.preventDefault();
            const btn = document.getElementById('authBtn');
            const secret = document.getElementById('authSecret').value;
            const terminal = document.getElementById('terminal');

            btn.disabled = true;
            btn.innerHTML = '<span>Verifying credentials...</span>';

            try {
                const response = await fetch('/deploy/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ secret: secret }),
                });

                const data = await response.json();

                if (data.success && data.branches) {
                    authenticatedSecret = secret;
                    document.getElementById('authForm').className = 'hidden';
                    document.getElementById('deployForm').className = '';
                    document.getElementById('headerSubtitle').innerText = 'Authenticated & Ready to Deploy';
                    document.getElementById('statusBadge').innerHTML = '<div class="pulse-dot"></div><span>Unlocked</span>';

                    // Populate branches
                    const select = document.getElementById('branch');
                    select.innerHTML = '';
                    data.branches.forEach(function(b) {
                        const opt = document.createElement('option');
                        opt.value = b;
                        opt.innerText = b === 'main' ? 'main (Production — Recommended)' : b;
                        select.appendChild(opt);
                    });

                    const customOpt = document.createElement('option');
                    customOpt.value = '__custom__';
                    customOpt.innerText = '+ Enter custom branch name...';
                    select.appendChild(customOpt);

                    terminal.className = 'terminal';
                } else {
                    terminal.className = 'terminal active';
                    terminal.innerHTML = '<div class="log-line error">❌ ' + escapeHtml(data.message || 'Invalid password.') + '</div>';
                    btn.disabled = false;
                    btn.innerHTML = '<span>🔓 Unlock Deployment Console</span>';
                }
            } catch (err) {
                terminal.className = 'terminal active';
                terminal.innerHTML = '<div class="log-line error">❌ Error: ' + escapeHtml(err.message) + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<span>🔓 Unlock Deployment Console</span>';
            }
        }

        function toggleCustomBranch(select) {
            const customField = document.getElementById('customBranchField');
            if (select.value === '__custom__') {
                customField.style.display = 'flex';
                document.getElementById('custom_branch').focus();
            } else {
                customField.style.display = 'none';
            }
        }

        async function handleDeploy(e) {
            e.preventDefault();
            const btn = document.getElementById('deployBtn');
            const terminal = document.getElementById('terminal');
            const branchSelect = document.getElementById('branch').value;
            const customBranch = document.getElementById('custom_branch').value.trim();
            const branch = (branchSelect === '__custom__' && customBranch) ? customBranch : (branchSelect === '__custom__' ? 'main' : branchSelect);

            btn.disabled = true;
            btn.innerHTML = '<span>⏳ Deploying ' + escapeHtml(branch) + '...</span>';
            terminal.className = 'terminal active';
            terminal.innerHTML = '<div class="log-line">🚀 Connecting to server deployment pipeline...</div>';

            try {
                const response = await fetch('/deploy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ secret: authenticatedSecret, branch: branch }),
                });

                const data = await response.json();

                if (data.logs && Array.isArray(data.logs)) {
                    terminal.innerHTML = data.logs.map(function(log) {
                        const isSuccess = log.indexOf('[SUCCESS]') !== -1 || log.indexOf('✅') !== -1;
                        const isError = log.indexOf('[AUTH_ERROR]') !== -1 || log.indexOf('Error') !== -1 || log.indexOf('Unauthorized') !== -1 || log.indexOf('❌') !== -1;
                        const cls = isSuccess ? 'log-line success' : (isError ? 'log-line error' : 'log-line');
                        return '<div class="' + cls + '">' + escapeHtml(log) + '</div>';
                    }).join('');
                } else if (data.message) {
                    terminal.innerHTML += '<div class="log-line error">❌ ' + escapeHtml(data.message) + '</div>';
                }

                if (data.success) {
                    btn.innerHTML = '<span>✅ Live Update Successful!</span>';
                    setTimeout(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<span>⚡ Deploy to Live Site</span>';
                    }, 4000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<span>⚡ Deploy to Live Site</span>';
                }
            } catch (err) {
                terminal.innerHTML += '<div class="log-line error">❌ Network error: ' + escapeHtml(err.message) + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<span>⚡ Deploy to Live Site</span>';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
HTML;
    }
}
