<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebDeployController extends Controller
{
    private const DEFAULT_SECRET = 'essem@2030';

    /**
     * Show the web deploy console or handle one-click GET deployments with ?secret=
     */
    public function index(Request $request): Response|JsonResponse
    {
        $secret = $request->query('secret') ?: $request->input('secret');
        $branch = $request->query('branch', 'main');

        if ($secret) {
            return $this->executeDeploy((string) $secret, (string) $branch);
        }

        $branches = $this->getAvailableBranches();

        return response($this->renderDeployHtml($branches), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * Handle POST deploy trigger
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
                    'logs' => ['[AUTH_ERROR] Invalid password provided. Please verify password.'],
                ], 403);
            }

            $cleanBranch = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $branch) ?: 'main';
            $logs = [];
            $startTime = microtime(true);

            $logs[] = '🚀 [' . date('Y-m-d H:i:s') . "] Initializing deployment for branch: [{$cleanBranch}]";

            // If the executable deploy script exists on the server, execute it directly
            $deployScript = '/home/qkbghwib/deploy';
            if (is_executable($deployScript)) {
                $logs[] = "⚡ Executing server deployment pipeline: {$deployScript}";
                $cmd = sprintf('%s %s 2>&1', escapeshellarg($deployScript), escapeshellarg($cleanBranch));
                $output = shell_exec($cmd);

                if ($output) {
                    $lines = explode("\n", trim((string) $output));
                    foreach ($lines as $line) {
                        if (trim($line) !== '') {
                            $logs[] = trim($line);
                        }
                    }
                }
            } else {
                // Fallback: Run commands in sequence
                $repoRoot = base_path('..');
                if (! is_dir($repoRoot . '/.git') && is_dir(base_path('.git'))) {
                    $repoRoot = base_path();
                }

                $logs[] = "📂 Project root: {$repoRoot}";

                // Git fetch
                $fetchOutput = shell_exec(sprintf('cd %s && git fetch origin %s 2>&1', escapeshellarg($repoRoot), escapeshellarg($cleanBranch)));
                $logs[] = "📥 [git fetch]: " . trim((string) $fetchOutput);

                // Git reset
                $resetOutput = shell_exec(sprintf('cd %s && git reset --hard origin/%s 2>&1', escapeshellarg($repoRoot), escapeshellarg($cleanBranch)));
                $logs[] = "🔄 [git reset]: " . trim((string) $resetOutput);

                // Laravel artisan optimize & migrate
                $artisanPath = base_path('artisan');
                $phpBin = PHP_BINARY ?: 'php';
                $artisanCmd = sprintf('cd %s && %s %s migrate --force 2>&1 && %s %s optimize:clear 2>&1 && %s %s optimize 2>&1 && %s %s view:cache 2>&1',
                    escapeshellarg(base_path()),
                    escapeshellarg($phpBin), escapeshellarg($artisanPath),
                    escapeshellarg($phpBin), escapeshellarg($artisanPath),
                    escapeshellarg($phpBin), escapeshellarg($artisanPath),
                    escapeshellarg($phpBin), escapeshellarg($artisanPath)
                );
                $artisanOutput = shell_exec($artisanCmd);
                $logs[] = "⚡ [artisan]: " . trim((string) $artisanOutput);
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
                    '❌ Error occurred: ' . $e->getMessage(),
                    'Location: ' . $e->getFile() . ':' . $e->getLine(),
                ],
            ], 500);
        }
    }

    private function renderDeployHtml(array $branches = ['main']): string
    {
        $optionsHtml = '';
        foreach ($branches as $b) {
            $label = $b === 'main' ? "main (Production — Recommended)" : $b;
            $selected = $b === 'main' ? 'selected' : '';
            $optionsHtml .= '<option value="' . htmlspecialchars($b, ENT_QUOTES) . '" ' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        $optionsHtml .= '<option value="__custom__">+ Enter custom branch name...</option>';

        $template = <<<'HTML'
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon">🚀</div>
            <div>
                <h1 class="title">RelayIQ Web Deployer</h1>
                <p class="subtitle">Protected One-Click Update Console</p>
            </div>
            <div class="badge">
                <div class="pulse-dot"></div>
                Online
            </div>
        </div>
        <div class="body">
            <form id="deployForm" onsubmit="handleDeploy(event)">
                <div class="field" style="margin-bottom: 14px;">
                    <label for="secret">Deployment Password</label>
                    <input type="password" id="secret" name="secret" placeholder="Enter deploy password" required autofocus autocomplete="current-password" />
                </div>
                <div class="field" style="margin-bottom: 14px;">
                    <label for="branch">Select Branch to Deploy</label>
                    <select id="branch" name="branch" onchange="toggleCustomBranch(this)">
                        {{OPTIONS_HTML}}
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
            const secret = document.getElementById('secret').value;
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
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ secret: secret, branch: branch }),
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

        return str_replace('{{OPTIONS_HTML}}', $optionsHtml, $template);
    }
}
