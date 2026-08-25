<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deploy Console — RelayIQ</title>
    <style>
        /* ── Reset & tokens ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:            #080c14;
            --surface:       #0f172a;
            --surface-hi:    #1e293b;
            --border:        #1e293b;
            --border-sub:    #334155;
            --primary:       #3b82f6;
            --primary-h:     #2563eb;
            --primary-glow:  rgba(59, 130, 246, 0.25);
            --success:       #22c55e;
            --success-dim:   rgba(34, 197, 94, 0.12);
            --danger:        #ef4444;
            --danger-dim:    rgba(239, 68, 68, 0.12);
            --warn:          #f59e0b;
            --warn-dim:      rgba(245, 158, 11, 0.12);
            --text:          #f8fafc;
            --text-muted:    #94a3b8;
            --text-dim:      #475569;
            --mono:          ui-monospace, 'Cascadia Code', 'Fira Code', Consolas, monospace;
            --sans:          ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            --radius:        12px;
            --radius-sm:     8px;
            --radius-xs:     6px;
        }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Layout ─────────────────────────────────────────────── */
        .app-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 28px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .app-header-brand { display: flex; align-items: center; gap: 10px; }
        .app-header-icon {
            width: 36px; height: 36px;
            background: rgba(59,130,246,.15);
            border: 1px solid rgba(59,130,246,.25);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .app-header-title { font-size: 16px; font-weight: 700; letter-spacing: -.01em; }
        .app-header-sub   { font-size: 12px; color: var(--text-muted); }
        .app-header-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }

        /* Status chip */
        .status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px; font-weight: 600;
            border: 1px solid transparent;
            transition: all .2s;
        }
        .status-chip.locked   { background: var(--danger-dim);  border-color: rgba(239,68,68,.25);  color: var(--danger); }
        .status-chip.ready    { background: var(--success-dim); border-color: rgba(34,197,94,.25); color: var(--success); }
        .status-chip.deploying{ background: var(--warn-dim);    border-color: rgba(245,158,11,.25); color: var(--warn); }
        .status-chip.done     { background: var(--success-dim); border-color: rgba(34,197,94,.25); color: var(--success); }
        .status-chip.failed   { background: var(--danger-dim);  border-color: rgba(239,68,68,.25);  color: var(--danger); }
        .pulse {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
            animation: pulse 1.8s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.75)} }

        /* Main body */
        .app-body {
            flex: 1;
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 0;
            min-height: 0;
        }

        .panel-left {
            border-right: 1px solid var(--border);
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel-right {
            padding: 28px;
            overflow-y: auto;
        }

        /* ── Steps indicator ─────────────────────────────────────── */
        .steps {
            display: flex;
            gap: 0;
            margin-bottom: 4px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dim);
            flex: 1;
        }
        .step:not(:last-child)::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-sub);
            margin: 0 6px;
        }
        .step-dot {
            width: 24px; height: 24px;
            border-radius: 50%;
            border: 2px solid var(--border-sub);
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
            flex-shrink: 0;
            transition: all .2s;
        }
        .step.active .step-dot    { border-color: var(--primary); background: var(--primary); color: #fff; }
        .step.active              { color: var(--text); }
        .step.complete .step-dot  { border-color: var(--success); background: var(--success); color: #fff; }
        .step.complete            { color: var(--text-muted); }

        /* ── Section heading ─────────────────────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 12px;
        }

        /* ── Form controls ───────────────────────────────────────── */
        .field { display: flex; flex-direction: column; gap: 6px; }
        label  { font-size: 13px; font-weight: 600; color: var(--text-muted); }

        input, select {
            width: 100%;
            background: rgba(0,0,0,.4);
            border: 1px solid var(--border-sub);
            color: var(--text);
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--sans);
            outline: none;
            transition: border .15s, box-shadow .15s;
            appearance: none;
        }
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
        select option { background: #1e293b; color: var(--text); }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 20px;
            border: none; border-radius: var(--radius-sm);
            font-size: 14px; font-weight: 600; font-family: var(--sans);
            cursor: pointer;
            transition: all .15s;
            width: 100%;
        }
        .btn-primary  { background: var(--primary); color: #fff; }
        .btn-primary:hover:not(:disabled) { background: var(--primary-h); transform: translateY(-1px); box-shadow: 0 4px 16px var(--primary-glow); }
        .btn-ghost    { background: var(--surface-hi); color: var(--text-muted); border: 1px solid var(--border-sub); }
        .btn-ghost:hover:not(:disabled) { color: var(--text); border-color: var(--border-sub); }
        .btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .btn-danger   { background: var(--danger); color: #fff; }
        .btn-danger:hover:not(:disabled) { opacity: .9; }

        /* ── Warning banner (production branch) ──────────────────── */
        .prod-warning {
            display: none;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: var(--warn-dim);
            border: 1px solid rgba(245,158,11,.25);
            border-radius: var(--radius-sm);
            font-size: 13px;
            color: var(--warn);
            line-height: 1.5;
        }
        .prod-warning.visible { display: flex; }
        .prod-warning-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

        /* ── Error/info callout ──────────────────────────────────── */
        .callout {
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            line-height: 1.5;
            display: none;
        }
        .callout.error   { background: var(--danger-dim); border: 1px solid rgba(239,68,68,.25); color: var(--danger); display: block; }
        .callout.success { background: var(--success-dim); border: 1px solid rgba(34,197,94,.25); color: var(--success); display: block; }
        .callout.info    { background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.25); color: var(--primary); display: block; }

        /* ── Deploy history (right panel) ────────────────────────── */
        .history-empty {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 10px;
            padding: 60px 20px;
            color: var(--text-dim);
            text-align: center;
            font-size: 13px;
            border: 1px dashed var(--border);
            border-radius: var(--radius);
        }
        .history-empty-icon { font-size: 32px; }

        .history-list { display: flex; flex-direction: column; gap: 10px; }
        .history-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: border-color .15s;
        }
        .history-item:hover { border-color: var(--border-sub); }
        .history-branch {
            font-size: 13px; font-weight: 600;
            color: var(--text);
            font-family: var(--mono);
        }
        .history-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .history-badge {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px; font-weight: 700;
        }
        .badge-success { background: var(--success-dim); color: var(--success); border: 1px solid rgba(34,197,94,.25); }
        .badge-failed  { background: var(--danger-dim);  color: var(--danger);  border: 1px solid rgba(239,68,68,.25); }
        .badge-running { background: var(--warn-dim);    color: var(--warn);    border: 1px solid rgba(245,158,11,.25); }

        /* ── Terminal (full width, below both panels) ─────────────── */
        .terminal-wrapper {
            border-top: 1px solid var(--border);
            display: none;
        }
        .terminal-wrapper.visible { display: block; }
        .terminal-header {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }
        .terminal-title { font-size: 12px; font-weight: 700; color: var(--text-muted); letter-spacing: .05em; text-transform: uppercase; }
        .terminal-actions { margin-left: auto; display: flex; align-items: center; gap: 6px; }
        .term-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 10px;
            background: var(--surface-hi);
            border: 1px solid var(--border-sub);
            border-radius: var(--radius-xs);
            font-size: 11px; font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s;
        }
        .term-btn:hover { color: var(--text); border-color: var(--text-dim); }

        .terminal-body {
            background: #040710;
            padding: 18px 22px;
            font-family: var(--mono);
            font-size: 12.5px;
            line-height: 1.65;
            color: #cbd5e1;
            max-height: 340px;
            overflow-y: auto;
            transition: max-height .3s ease;
            scrollbar-width: thin;
            scrollbar-color: var(--border-sub) transparent;
        }
        .terminal-body.expanded { max-height: 600px; }

        .log-line { word-break: break-all; padding: 1px 0; }
        .log-line.log-success { color: var(--success); font-weight: 600; }
        .log-line.log-error   { color: var(--danger);  font-weight: 600; }
        .log-line.log-warn    { color: var(--warn); }

        /* Post-deploy quick actions */
        .post-actions {
            display: none;
            gap: 8px;
            padding: 14px 22px;
            background: var(--surface);
            border-top: 1px solid var(--border);
        }
        .post-actions.visible { display: flex; flex-wrap: wrap; }
        .post-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px;
            background: var(--surface-hi);
            border: 1px solid var(--border-sub);
            border-radius: var(--radius-xs);
            font-size: 13px; font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
        }
        .post-btn:hover { color: var(--text); border-color: var(--border-sub); }
        .post-btn.primary { background: var(--success-dim); border-color: rgba(34,197,94,.3); color: var(--success); }

        /* ── Confirmation modal ──────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 50;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: var(--surface);
            border: 1px solid var(--border-sub);
            border-radius: var(--radius);
            padding: 30px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 24px 60px rgba(0,0,0,.6);
        }
        .modal-icon  { font-size: 36px; margin-bottom: 16px; }
        .modal h2    { font-size: 18px; font-weight: 700; margin-bottom: 10px; }
        .modal p     { font-size: 14px; color: var(--text-muted); line-height: 1.65; margin-bottom: 20px; }
        .modal-field { margin-bottom: 20px; }
        .modal-field label { font-size: 13px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 6px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions .btn { flex: 1; }

        /* ── Hidden utility ──────────────────────────────────────── */
        .hidden { display: none !important; }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 768px) {
            .app-body { grid-template-columns: 1fr; }
            .panel-left { border-right: none; border-bottom: 1px solid var(--border); }
            .app-header-sub { display: none; }
        }

        /* ── Light mode (system colour scheme) ───────────────────── */
        @media (prefers-color-scheme: light) {
            :root {
                --bg:           #f1f5f9;
                --surface:      #ffffff;
                --surface-hi:   #f8fafc;
                --border:       #e2e8f0;
                --border-sub:   #cbd5e1;
                --text:         #0f172a;
                --text-muted:   #475569;
                --text-dim:     #94a3b8;
                --primary-glow: rgba(59, 130, 246, 0.18);
                --success-dim:  rgba(34, 197, 94, 0.10);
                --danger-dim:   rgba(239, 68, 68, 0.10);
                --warn-dim:     rgba(245, 158, 11, 0.10);
            }
            input, select {
                background: #f8fafc;
                border-color: var(--border-sub);
            }
            select option { background: #ffffff; color: var(--text); }
            .modal {
                box-shadow: 0 24px 60px rgba(0,0,0,.10);
            }
            /* ── Terminal always stays dark ── */
            .terminal-wrapper  { background: #0d1117; }
            .terminal-header   { background: #161b22; border-color: #30363d; }
            .terminal-title    { color: #8b949e; }
            .terminal-body     { background: #040710; color: #cbd5e1; }
            .term-btn          { background: #21262d; border-color: #30363d; color: #8b949e; }
            .term-btn:hover    { color: #f0f6fc; border-color: #8b949e; }
            .post-actions      { background: #161b22; border-color: #30363d; }
            .post-btn          { background: #21262d; border-color: #30363d; color: #8b949e; }
            .post-btn:hover    { color: #f0f6fc; }
            .post-btn.primary  { background: var(--success-dim); border-color: rgba(34,197,94,.3); color: var(--success); }
        }

        /* ── Spinner ─────────────────────────────────────────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .6s linear infinite;
        }
    </style>
</head>
<body>

<!-- ── Header ────────────────────────────────────────────────── -->
<header class="app-header">
    <div class="app-header-brand">
        <div class="app-header-icon">🚀</div>
        <div>
            <div class="app-header-title">RelayIQ Deploy Console</div>
            <div class="app-header-sub" id="headerSub">Enter credentials to access</div>
        </div>
    </div>
    <div class="app-header-right">
        <div class="status-chip locked" id="statusChip">
            <div class="pulse"></div>
            <span id="statusText">Locked</span>
        </div>
    </div>
</header>

<!-- ── Main body ─────────────────────────────────────────────── -->
<div class="app-body">

    <!-- Left panel: steps + forms -->
    <div class="panel-left">

        <!-- Steps -->
        <div class="steps">
            <div class="step active" id="step1">
                <div class="step-dot">1</div>
                <span>Auth</span>
            </div>
            <div class="step" id="step2">
                <div class="step-dot">2</div>
                <span>Branch</span>
            </div>
            <div class="step" id="step3">
                <div class="step-dot">3</div>
                <span>Deploy</span>
            </div>
        </div>

        <!-- Step 1: Auth form -->
        <div id="authSection">
            <p class="section-title">Authentication</p>
            <div class="field" style="margin-bottom:14px">
                <label for="authSecret">Deployment Password</label>
                <input type="password" id="authSecret"
                       placeholder="Enter your deploy password"
                       autocomplete="current-password"
                       autofocus required />
            </div>
            <div id="authCallout"></div>
            <br>
            <button class="btn btn-primary" id="authBtn" onclick="handleAuth()">
                <span>🔓 Unlock Console</span>
            </button>
        </div>

        <!-- Step 2+3: Deploy form (hidden until auth) -->
        <div id="deploySection" class="hidden">
            <p class="section-title">Target Branch</p>
            <div class="field" style="margin-bottom:12px">
                <label for="branchSelect">Select Branch</label>
                <select id="branchSelect" onchange="onBranchChange()">
                    <!-- Populated after auth -->
                </select>
            </div>

            <div id="customBranchField" class="field hidden" style="margin-bottom:12px">
                <label for="customBranch">Custom Branch Name</label>
                <input type="text" id="customBranch" placeholder="e.g. feature/my-branch" />
            </div>

            <div class="prod-warning" id="prodWarning">
                <span class="prod-warning-icon">⚠️</span>
                <span>You are deploying to <strong>production</strong>. A confirmation is required.</span>
            </div>

            <div id="deployCallout" style="margin-bottom:10px"></div>

            <button class="btn btn-primary" id="deployBtn" onclick="handleDeploy()">
                <span>⚡ Deploy to Live Site</span>
            </button>

            <button class="btn btn-ghost" id="logoutBtn"
                    onclick="handleLogout()" style="margin-top:10px; font-size:12px; padding:9px;">
                🔒 Lock Console
            </button>
        </div>
    </div>

    <!-- Right panel: deploy history -->
    <div class="panel-right">
        <p class="section-title">Deploy History</p>

        @if(count($history) === 0)
            <div class="history-empty">
                <div class="history-empty-icon">📋</div>
                <div>No deployments recorded yet.<br>Run your first deploy to see history here.</div>
            </div>
        @else
            <div class="history-list" id="historyList">
                @foreach($history as $entry)
                    <div class="history-item">
                        <div>
                            <div class="history-branch">{{ $entry['branch'] ?? '—' }}</div>
                            <div class="history-meta">
                                {{ isset($entry['timestamp']) ? \Carbon\Carbon::parse($entry['timestamp'])->diffForHumans() : '—' }}
                                @if(isset($entry['duration']))
                                    &nbsp;·&nbsp; {{ $entry['duration'] }}s
                                @endif
                                @if(isset($entry['ip']))
                                    &nbsp;·&nbsp; {{ $entry['ip'] }}
                                @endif
                            </div>
                        </div>
                        @php $s = $entry['status'] ?? ''; @endphp
                        <span class="history-badge {{ $s === 'success' ? 'badge-success' : ($s === 'failed' ? 'badge-failed' : 'badge-running') }}">
                            {{ $s === 'success' ? '✅ success' : ($s === 'failed' ? '❌ failed' : '⏳ ' . $s) }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

<!-- ── Terminal ───────────────────────────────────────────────── -->
<div class="terminal-wrapper" id="terminalWrapper">
    <div class="terminal-header">
        <span class="terminal-title">Terminal Output</span>
        <div class="terminal-actions">
            <button class="term-btn" id="copyBtn" onclick="copyLogs()">📋 Copy</button>
            <button class="term-btn" onclick="clearTerminal()">🗑 Clear</button>
            <button class="term-btn" id="expandBtn" onclick="toggleExpand()">⤢ Expand</button>
        </div>
    </div>
    <div class="terminal-body" id="terminalBody"></div>
    <div class="post-actions" id="postActions">
        <a href="/" target="_blank" class="post-btn primary">🌐 Open Site</a>
        <button class="post-btn" onclick="deployAgain()">🔄 Deploy Again</button>
        <button class="post-btn" onclick="clearTerminal()">🗑 Clear Terminal</button>
    </div>
</div>

<!-- ── Confirmation modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon">⚠️</div>
        <h2>Deploy to Production?</h2>
        <p>
            You are about to deploy <strong id="confirmBranch" style="color:var(--warn)">main</strong> to the live site.
            This will reset the server codebase, run migrations, and flush all caches.
            <br><br>
            Type <strong style="color:var(--text);font-family:var(--mono)">deploy</strong> below to confirm.
        </p>
        <div class="modal-field">
            <label for="confirmInput">Confirmation</label>
            <input type="text" id="confirmInput" placeholder='Type "deploy" to proceed'
                   oninput="onConfirmInput()" autocomplete="off" />
        </div>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button class="btn btn-danger" id="confirmDeployBtn" disabled onclick="confirmDeploy()">
                ⚡ Deploy to Live
            </button>
        </div>
    </div>
</div>

<script>
    // ── State ────────────────────────────────────────────────────
    let authToken      = sessionStorage.getItem('deploy_auth_token') || '';
    let pendingBranch  = '';
    let pollTimer      = null;
    let pollToken      = '';
    let renderedLogs   = 0;

    // ── Init ─────────────────────────────────────────────────────
    (function init() {
        if (authToken) {
            // Attempt to restore session silently
            showDeploySection();
        }
    })();

    // ── Auth ─────────────────────────────────────────────────────
    async function handleAuth() {
        const btn    = document.getElementById('authBtn');
        const secret = document.getElementById('authSecret').value;
        const callout = document.getElementById('authCallout');

        if (!secret) { showCallout(callout, 'error', 'Please enter the deployment password.'); return; }

        setBtn(btn, true, '<div class="spinner"></div> Verifying…');

        try {
            const res  = await post('/deploy/auth', { secret });
            const data = await res.json();

            if (data.success && data.token) {
                authToken = data.token;
                sessionStorage.setItem('deploy_auth_token', authToken);
                populateBranches(data.branches || []);
                showDeploySection();
                clearCallout(callout);
            } else {
                showCallout(callout, 'error', data.message || 'Invalid password.');
                setBtn(btn, false, '🔓 Unlock Console');
            }
        } catch (err) {
            showCallout(callout, 'error', 'Network error: ' + err.message);
            setBtn(btn, false, '🔓 Unlock Console');
        }
    }

    function showDeploySection() {
        document.getElementById('authSection').classList.add('hidden');
        document.getElementById('deploySection').classList.remove('hidden');
        setStep(2);
        setStatus('ready', '🟢 Authenticated');
        document.getElementById('headerSub').textContent = 'Authenticated — ready to deploy';
        document.getElementById('authSecret').value = '';
    }

    function handleLogout() {
        authToken = '';
        sessionStorage.removeItem('deploy_auth_token');
        document.getElementById('authSection').classList.remove('hidden');
        document.getElementById('deploySection').classList.add('hidden');
        setStep(1);
        setStatus('locked', 'Locked');
        document.getElementById('headerSub').textContent = 'Enter credentials to access';
        document.getElementById('authSecret').focus();
    }

    // ── Branches ─────────────────────────────────────────────────
    function populateBranches(branches) {
        const sel = document.getElementById('branchSelect');
        sel.innerHTML = '';
        branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b === 'main' ? 'main  (Production — Recommended)' : b;
            sel.appendChild(opt);
        });
        const custom = document.createElement('option');
        custom.value = '__custom__';
        custom.textContent = '+ Enter custom branch name…';
        sel.appendChild(custom);
        onBranchChange();
    }

    function onBranchChange() {
        const val     = document.getElementById('branchSelect').value;
        const isCustom = val === '__custom__';
        const isProd   = val === 'main';

        document.getElementById('customBranchField').classList.toggle('hidden', !isCustom);
        document.getElementById('prodWarning').classList.toggle('visible', isProd);
        if (isCustom) document.getElementById('customBranch').focus();
    }

    function getSelectedBranch() {
        const sel    = document.getElementById('branchSelect').value;
        const custom = document.getElementById('customBranch').value.trim();
        if (sel === '__custom__') return custom || 'main';
        return sel;
    }

    // ── Deploy trigger ───────────────────────────────────────────
    function handleDeploy() {
        const branch = getSelectedBranch();
        if (!branch) { return; }

        if (branch === 'main') {
            document.getElementById('confirmBranch').textContent = branch;
            document.getElementById('confirmInput').value = '';
            document.getElementById('confirmDeployBtn').disabled = true;
            document.getElementById('confirmModal').classList.add('open');
            document.getElementById('confirmInput').focus();
            pendingBranch = branch;
        } else {
            startDeploy(branch);
        }
    }

    function onConfirmInput() {
        const val = document.getElementById('confirmInput').value;
        document.getElementById('confirmDeployBtn').disabled = (val.toLowerCase() !== 'deploy');
    }

    function confirmDeploy() {
        closeModal();
        startDeploy(pendingBranch);
    }

    function closeModal() {
        document.getElementById('confirmModal').classList.remove('open');
        document.getElementById('confirmInput').value = '';
        document.getElementById('confirmDeployBtn').disabled = true;
    }

    // ── Deploy execution ─────────────────────────────────────────
    async function startDeploy(branch) {
        const btn     = document.getElementById('deployBtn');
        const callout = document.getElementById('deployCallout');
        clearCallout(callout);

        setBtn(btn, true, '<div class="spinner"></div> Starting deploy…');
        setStatus('deploying', '⏳ Deploying…');
        setStep(3);

        openTerminal();
        appendLog(`🚀 Initiating deploy to [${escapeHtml(branch)}]…`, 'log-line');

        await startBackgroundDeploy(branch, btn, callout);
    }

    // Background mode: POST /deploy/start → poll /deploy/status/{token}
    async function startBackgroundDeploy(branch, btn, callout) {
        try {
            const res  = await post('/deploy/start', { token: authToken, branch });
            const data = await res.json();

            if (res.status === 401) {
                handleSessionExpired(callout, btn);
                return;
            }

            if (res.status === 409) {
                showCallout(callout, 'error', '⛔ A deployment is already running. Please wait.');
                setBtn(btn, false, '⚡ Deploy to Live Site');
                setStatus('ready', '🟢 Authenticated');
                setStep(2);
                return;
            }

            if (!data.success) {
                showCallout(callout, 'error', data.message || 'Failed to start deploy.');
                setBtn(btn, false, '⚡ Deploy to Live Site');
                setStatus('ready', '🟢 Authenticated');
                setStep(2);
                return;
            }

            pollToken    = data.deploy_token;
            renderedLogs = 0;

            // Stale-spawn detection: if still pending after 8s, fall back to sync
            let pendingChecks = 0;
            pollTimer = setInterval(async () => {
                const statusData = await fetchStatus(pollToken);
                if (!statusData) return;

                renderNewLogs(statusData.logs || []);

                if (statusData.status === 'pending') {
                    pendingChecks++;
                    if (pendingChecks > 5) {
                        clearInterval(pollTimer);
                        appendLog('❌ Background deploy process did not start. Check server shell_exec / proc_open permissions.', 'log-line log-error');
                        setBtn(btn, false, '⚡ Deploy to Live Site');
                        setStatus('failed', '❌ Deploy failed');
                        setStep(2);
                    }
                    return;
                }

                pendingChecks = 0; // reset once running

                if (statusData.status === 'complete' || statusData.status === 'failed') {
                    clearInterval(pollTimer);
                    onDeployFinished(statusData, btn, callout, branch);
                }
            }, 1500);

        } catch (err) {
            showCallout(callout, 'error', 'Network error: ' + err.message);
            setBtn(btn, false, '⚡ Deploy to Live Site');
            setStatus('ready', '🟢 Authenticated');
        }
    }


    async function fetchStatus(token) {
        try {
            const res = await fetch('/deploy/status/' + token, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return null;
            return await res.json();
        } catch { return null; }
    }

    function onDeployFinished(data, btn, callout, branch) {
        const success = data.status === 'complete' || data.success === true;

        if (success) {
            setStatus('done', '✅ Deployed');
            setBtn(btn, false, '✅ Deploy Complete');
            document.getElementById('postActions').classList.add('visible');
            setTimeout(() => setBtn(btn, false, '⚡ Deploy to Live Site'), 5000);
            setStep(4); // mark all 3 steps complete
        } else {
            setStatus('failed', '❌ Deploy failed');
            showCallout(callout, 'error', data.message || 'Deployment failed — check the terminal for details.');
            setBtn(btn, false, '⚡ Deploy to Live Site');
            setStep(2); // return to branch/deploy config for retry
        }
    }

    function deployAgain() {
        document.getElementById('postActions').classList.remove('visible');
        clearTerminal();
        document.getElementById('deployCallout').innerHTML = '';
        setStep(2);
    }

    function handleSessionExpired(callout, btn) {
        handleLogout();
        showCallout(document.getElementById('authCallout'), 'info', 'Your session expired. Please re-authenticate.');
        setBtn(btn, false, '⚡ Deploy to Live Site');
        setStatus('locked', 'Locked');
    }

    // ── Terminal helpers ─────────────────────────────────────────
    function openTerminal() {
        const wrapper = document.getElementById('terminalWrapper');
        wrapper.classList.add('visible');
        document.getElementById('terminalBody').innerHTML = '';
        renderedLogs = 0;
    }

    function renderNewLogs(logs) {
        const body  = document.getElementById('terminalBody');
        const slice = logs.slice(renderedLogs);
        slice.forEach(log => appendLog(log));
        renderedLogs = logs.length;
        body.scrollTop = body.scrollHeight;
    }

    function appendLog(msg, extraClass) {
        const body = document.getElementById('terminalBody');
        const line = document.createElement('div');

        const isSuccess = msg.includes('[SUCCESS]') || msg.includes('✅');
        const isError   = msg.includes('[AUTH_ERROR]') || msg.includes('[EXCEPTION]') || msg.includes('❌');
        const isWarn    = msg.includes('⚠️');

        const cls = extraClass || (isSuccess ? 'log-line log-success' : isError ? 'log-line log-error' : isWarn ? 'log-line log-warn' : 'log-line');
        line.className = cls;
        line.textContent = msg;
        body.appendChild(line);
        body.scrollTop = body.scrollHeight;
    }

    function clearTerminal() {
        document.getElementById('terminalBody').innerHTML = '';
        document.getElementById('terminalWrapper').classList.remove('visible');
        document.getElementById('postActions').classList.remove('visible');
        renderedLogs = 0;
    }

    function copyLogs() {
        const text = Array.from(document.querySelectorAll('.log-line')).map(l => l.textContent).join('\n');
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copyBtn');
            btn.textContent = '✅ Copied';
            setTimeout(() => btn.textContent = '📋 Copy', 1800);
        });
    }

    function toggleExpand() {
        const body = document.getElementById('terminalBody');
        const btn  = document.getElementById('expandBtn');
        const expanded = body.classList.toggle('expanded');
        btn.textContent = expanded ? '⤡ Collapse' : '⤢ Expand';
    }

    // ── Step indicator ───────────────────────────────────────────
    function setStep(active) {
        [1, 2, 3].forEach(i => {
            const el = document.getElementById('step' + i);
            el.classList.remove('active', 'complete');
            if (i < active)      el.classList.add('complete');
            else if (i === active) el.classList.add('active');
            if (i < active) el.querySelector('.step-dot').textContent = '✓';
            else             el.querySelector('.step-dot').textContent = String(i);
        });
    }

    // ── Status chip ──────────────────────────────────────────────
    function setStatus(type, label) {
        const chip = document.getElementById('statusChip');
        chip.className = 'status-chip ' + type;
        document.getElementById('statusText').textContent = label;
        const pulse = chip.querySelector('.pulse');
        if (type === 'deploying') {
            if (!pulse) { const d = document.createElement('div'); d.className='pulse'; chip.prepend(d); }
        } else {
            if (pulse) pulse.remove();
        }
    }

    // ── Callout helpers ──────────────────────────────────────────
    function showCallout(el, type, msg) {
        el.className = 'callout ' + type;
        el.textContent = msg;
    }
    function clearCallout(el) {
        el.className = 'callout';
        el.textContent = '';
    }

    // ── Button helper ────────────────────────────────────────────
    function setBtn(btn, disabled, html) {
        btn.disabled = disabled;
        btn.innerHTML = html;
    }

    // ── Fetch wrapper ────────────────────────────────────────────
    function post(url, body) {
        return fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body:    JSON.stringify(body),
        });
    }

    // ── XSS guard ────────────────────────────────────────────────
    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ── Keyboard shortcut: Enter on auth field ───────────────────
    document.getElementById('authSecret').addEventListener('keydown', e => {
        if (e.key === 'Enter') handleAuth();
    });
</script>
</body>
</html>
