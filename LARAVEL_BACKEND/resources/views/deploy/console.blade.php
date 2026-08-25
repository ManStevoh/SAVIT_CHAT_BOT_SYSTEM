<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deploy Console — RelayIQ</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|geist-mono:400,500,600&display=swap" rel="stylesheet" />
    <style>
        /* ── Reset & tokens (Light Mode — RelayIQ Theme) ─────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:            #f8fafc;
            --surface:       #ffffff;
            --surface-subtle:#f1f5f9;
            --surface-hi:    #f8fafc;
            --border:        #e2e8f0;
            --border-sub:    #cbd5e1;
            --primary:       #2563eb;
            --primary-h:     #1d4ed8;
            --primary-glow:  rgba(37, 99, 235, 0.15);
            --success:       #16a34a;
            --success-dim:   #f0fdf4;
            --success-border:#bbf7d0;
            --danger:        #dc2626;
            --danger-dim:    #fef2f2;
            --danger-border: #fecaca;
            --warn:          #d97706;
            --warn-dim:      #fffbeb;
            --warn-border:   #fde68a;
            --text:          #0f172a;
            --text-muted:    #475569;
            --text-dim:      #94a3b8;
            --mono:          'Geist Mono', ui-monospace, 'Cascadia Code', Consolas, monospace;
            --sans:          'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            --radius-lg:     14px;
            --radius:        12px;
            --radius-sm:     8px;
            --radius-xs:     6px;
            --shadow-sm:     0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md:     0 4px 12px 0 rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-lg:     0 12px 28px -4px rgba(0, 0, 0, 0.08), 0 4px 8px -2px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ─────────────────────────────────────────────── */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 28px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: var(--shadow-sm);
        }
        .app-header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .app-header-logo-img {
            height: 32px;
            width: auto;
            object-fit: contain;
        }
        .app-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .app-header-fallback {
            display: none;
            align-items: center;
            gap: 10px;
        }
        .app-header-icon {
            width: 36px; height: 36px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }
        .app-header-title { font-size: 16px; font-weight: 700; color: var(--text); }
        .app-header-sub   { font-size: 12px; color: var(--text-muted); }
        .app-header-right { display: flex; align-items: center; gap: 12px; }

        /* ── Status chip ─────────────────────────────────────────── */
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
            transition: all .2s;
        }
        .status-chip.locked    { background: var(--danger-dim);  border-color: var(--danger-border);  color: var(--danger); }
        .status-chip.ready     { background: var(--success-dim); border-color: var(--success-border); color: var(--success); }
        .status-chip.deploying { background: var(--warn-dim);    border-color: var(--warn-border);    color: var(--warn); }
        .status-chip.done      { background: var(--success-dim); border-color: var(--success-border); color: var(--success); }
        .status-chip.failed    { background: var(--danger-dim);  border-color: var(--danger-border);  color: var(--danger); }

        .pulse {
            width: 7px; height: 7px; border-radius: 50%;
            background: currentColor;
            animation: pulse 1.8s ease-in-out infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.75)} }

        /* ── Main Layout ─────────────────────────────────────────── */
        .app-body {
            flex: 1;
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 24px;
            padding: 24px 28px;
            max-width: 1440px;
            margin: 0 auto;
            width: 100%;
        }

        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .panel-left {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel-right {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-height: 380px;
        }

        /* ── Steps indicator ─────────────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dim);
            transition: color .2s;
        }
        .step:not(:last-child)::after {
            content: '';
            width: 32px;
            height: 2px;
            background: var(--border);
            margin: 0 4px;
            transition: background .2s;
        }
        .step.complete:not(:last-child)::after {
            background: var(--success);
        }
        .step-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid var(--border-sub);
            background: var(--surface-hi);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
            transition: all .2s;
        }
        .step.active {
            color: var(--text);
        }
        .step.active .step-dot {
            border-color: var(--primary);
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .step.complete {
            color: var(--text-muted);
        }
        .step.complete .step-dot {
            border-color: var(--success);
            background: var(--success);
            color: #ffffff;
        }

        /* ── Section heading ─────────────────────────────────────── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        /* ── Form controls ───────────────────────────────────────── */
        .field { display: flex; flex-direction: column; gap: 6px; }
        label  { font-size: 13px; font-weight: 600; color: #334155; }

        input, select {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-sub);
            color: var(--text);
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--sans);
            font-weight: 500;
            outline: none;
            transition: border .15s, box-shadow .15s;
            appearance: none;
            box-shadow: var(--shadow-sm);
        }
        input:focus, select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            cursor: pointer;
        }
        select option { background: #ffffff; color: var(--text); }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 18px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            font-family: var(--sans);
            cursor: pointer;
            transition: all .15s;
            width: 100%;
            box-shadow: var(--shadow-sm);
        }
        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-h);
            box-shadow: 0 4px 12px var(--primary-glow);
            transform: translateY(-1px);
        }
        .btn-ghost {
            background: #ffffff;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover:not(:disabled) {
            background: var(--surface-subtle);
            color: var(--text);
            border-color: var(--border-sub);
        }
        .btn:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .btn-danger {
            background: var(--danger);
            color: #ffffff;
        }
        .btn-danger:hover:not(:disabled) {
            opacity: .92;
            transform: translateY(-1px);
        }

        /* ── Warning banner (production branch) ──────────────────── */
        .prod-warning {
            display: none;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: var(--warn-dim);
            border: 1px solid var(--warn-border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            color: #92400e;
            line-height: 1.5;
        }
        .prod-warning.visible { display: flex; }
        .prod-warning-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

        /* ── Callout messages ────────────────────────────────────── */
        .callout {
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            line-height: 1.5;
            display: none;
        }
        .callout.error   { background: var(--danger-dim); border: 1px solid var(--danger-border); color: var(--danger); display: block; }
        .callout.success { background: var(--success-dim); border: 1px solid var(--success-border); color: var(--success); display: block; }
        .callout.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: var(--primary); display: block; }

        /* ── Deploy history (right panel) ────────────────────────── */
        .history-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 50px 20px;
            color: var(--text-dim);
            text-align: center;
            font-size: 13px;
            border: 1px dashed var(--border);
            border-radius: var(--radius);
            background: var(--surface-subtle);
        }
        .history-empty-icon { font-size: 32px; }

        .history-list { display: flex; flex-direction: column; gap: 8px; }
        .history-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: all .15s;
        }
        .history-item:hover {
            border-color: var(--border-sub);
            box-shadow: var(--shadow-sm);
        }
        .history-branch {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            font-family: var(--mono);
        }
        .history-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .history-badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-success { background: var(--success-dim); color: var(--success); border: 1px solid var(--success-border); }
        .badge-failed  { background: var(--danger-dim);  color: var(--danger);  border: 1px solid var(--danger-border); }
        .badge-running { background: var(--warn-dim);    color: var(--warn);    border: 1px solid var(--warn-border); }

        /* ── Terminal (Developer Console Window) ─────────────────── */
        .terminal-wrapper {
            margin-top: 8px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid #334155;
            box-shadow: var(--shadow-lg);
            display: none;
        }
        .terminal-wrapper.visible { display: block; }
        .terminal-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: #1e293b;
            border-bottom: 1px solid #334155;
        }
        .terminal-dots {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .terminal-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        .terminal-dot.red    { background: #ef4444; }
        .terminal-dot.yellow { background: #f59e0b; }
        .terminal-dot.green  { background: #10b981; }

        .terminal-title {
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            letter-spacing: .05em;
            text-transform: uppercase;
            margin-left: 4px;
        }
        .terminal-actions { margin-left: auto; display: flex; align-items: center; gap: 6px; }
        .term-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            background: #334155;
            border: 1px solid #475569;
            border-radius: var(--radius-xs);
            font-size: 11px;
            font-weight: 600;
            color: #e2e8f0;
            cursor: pointer;
            transition: all .15s;
        }
        .term-btn:hover { background: #475569; color: #ffffff; }

        .terminal-body {
            background: #090d16;
            padding: 18px 22px;
            font-family: var(--mono);
            font-size: 12.5px;
            line-height: 1.7;
            color: #e2e8f0;
            max-height: 380px;
            overflow-y: auto;
            transition: max-height .3s ease;
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
        }
        .terminal-body.expanded { max-height: 640px; }

        .log-line { word-break: break-all; padding: 1px 0; }
        .log-line.log-success { color: #4ade80; font-weight: 600; }
        .log-line.log-error   { color: #f87171; font-weight: 600; }
        .log-line.log-warn    { color: #fbbf24; }

        /* Post-deploy quick actions */
        .post-actions {
            display: none;
            gap: 10px;
            padding: 14px 20px;
            background: #1e293b;
            border-top: 1px solid #334155;
        }
        .post-actions.visible { display: flex; flex-wrap: wrap; }
        .post-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #334155;
            border: 1px solid #475569;
            border-radius: var(--radius-xs);
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            cursor: pointer;
            transition: all .15s;
            text-decoration: none;
        }
        .post-btn:hover { background: #475569; color: #ffffff; }
        .post-btn.primary {
            background: #15803d;
            border-color: #16a34a;
            color: #ffffff;
        }
        .post-btn.primary:hover { background: #16a34a; }

        /* ── Confirmation modal ──────────────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 50;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            max-width: 440px;
            width: 100%;
            box-shadow: var(--shadow-lg);
        }
        .modal-icon  { font-size: 36px; margin-bottom: 14px; }
        .modal h2    { font-size: 18px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .modal p     { font-size: 14px; color: var(--text-muted); line-height: 1.65; margin-bottom: 18px; }
        .modal-field { margin-bottom: 18px; }
        .modal-field label { font-size: 13px; font-weight: 600; color: #334155; display: block; margin-bottom: 6px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions .btn { flex: 1; }

        /* ── Utility ─────────────────────────────────────────────── */
        .hidden { display: none !important; }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 920px) {
            .app-body { grid-template-columns: 1fr; padding: 16px; }
            .app-header-sub { display: none; }
        }

        /* ── Spinner ─────────────────────────────────────────────── */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            display: inline-block;
            width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,.4);
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
        <img src="/images/branding/relaysiq-wordmark-light.png?v=5"
             alt="RelayIQ"
             class="app-header-logo-img"
             onerror="this.style.display='none'; document.getElementById('headerFallback').style.display='flex';" />

        <div id="headerFallback" class="app-header-fallback">
            <div class="app-header-icon">🚀</div>
            <div>
                <div class="app-header-title">RelayIQ</div>
            </div>
        </div>

        <span class="app-header-badge">Deploy Console</span>
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
    <div class="panel-card panel-left">

        <!-- Steps Indicator (1, 2, 3) -->
        <div class="steps">
            <div class="step active" id="step1">
                <div class="step-dot" id="stepDot1">1</div>
                <span id="stepLabel1">Auth</span>
            </div>
            <div class="step" id="step2">
                <div class="step-dot" id="stepDot2">2</div>
                <span id="stepLabel2">Branch</span>
            </div>
            <div class="step" id="step3">
                <div class="step-dot" id="stepDot3">3</div>
                <span id="stepLabel3">Deploy</span>
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
            <div id="authCallout" style="margin-bottom:14px"></div>
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

            <div id="deployCallout" style="margin-bottom:12px"></div>

            <button class="btn btn-primary" id="deployBtn" onclick="handleDeploy()">
                <span>⚡ Deploy to Live Site</span>
            </button>

            <button class="btn btn-ghost" id="logoutBtn"
                    onclick="handleLogout()" style="margin-top:10px; font-size:13px; padding:9px;">
                🔒 Lock Console
            </button>
        </div>
    </div>

    <!-- Right panel: deploy history -->
    <div class="panel-card panel-right">
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

        <!-- ── Live Terminal Console ─────────────────────────────── -->
        <div class="terminal-wrapper" id="terminalWrapper">
            <div class="terminal-header">
                <div class="terminal-dots">
                    <span class="terminal-dot red"></span>
                    <span class="terminal-dot yellow"></span>
                    <span class="terminal-dot green"></span>
                </div>
                <span class="terminal-title">Live Deployment Terminal</span>
                <div class="terminal-actions">
                    <button class="term-btn" id="copyBtn" onclick="copyLogs()">📋 Copy</button>
                    <button class="term-btn" onclick="clearTerminal()">🗑 Clear</button>
                    <button class="term-btn" id="expandBtn" onclick="toggleExpand()">⤢ Expand</button>
                </div>
            </div>
            <div class="terminal-body" id="terminalBody"></div>
            <div class="post-actions" id="postActions">
                <a href="/" target="_blank" class="post-btn primary">🌐 Open Live Site</a>
                <button class="post-btn" onclick="deployAgain()">🔄 Deploy Again</button>
                <button class="post-btn" onclick="clearTerminal()">🗑 Clear Output</button>
            </div>
        </div>

    </div>

</div>

<!-- ── Confirmation modal ─────────────────────────────────────── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-icon">⚠️</div>
        <h2>Deploy to Production?</h2>
        <p>
            You are about to deploy <strong id="confirmBranch" style="color:var(--primary)">main</strong> to the live site.
            This will pull the latest commits, run database migrations, and compile production caches.
            <br><br>
            Type <strong style="color:var(--text);font-family:var(--mono)">deploy</strong> below to proceed.
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
            showDeploySection();
        } else {
            setStep(1);
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
        setStatus('ready', '🟢 Ready to Deploy');
        document.getElementById('authSecret').value = '';
    }

    function handleLogout() {
        authToken = '';
        sessionStorage.removeItem('deploy_auth_token');
        if (pollTimer) clearInterval(pollTimer);
        document.getElementById('authSection').classList.remove('hidden');
        document.getElementById('deploySection').classList.add('hidden');
        setStep(1);
        setStatus('locked', 'Locked');
        document.getElementById('authSecret').focus();
    }

    // ── Branches ─────────────────────────────────────────────────
    function populateBranches(branches) {
        const sel = document.getElementById('branchSelect');
        sel.innerHTML = '';
        branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b === 'main' ? 'main (Production — Recommended)' : b;
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

    // ── Deploy execution (Live Streaming via 1s polling) ─────────
    async function startDeploy(branch) {
        const btn     = document.getElementById('deployBtn');
        const callout = document.getElementById('deployCallout');
        clearCallout(callout);

        setBtn(btn, true, '<div class="spinner"></div> Initiating…');
        setStatus('deploying', '⏳ Deploying…');
        setStep(3);

        openTerminal();
        appendLog(`🚀 Initiating deploy to [${escapeHtml(branch)}]…`, 'log-line');

        await startBackgroundDeploy(branch, btn, callout);
    }

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
                setStatus('ready', '🟢 Ready to Deploy');
                setStep(2);
                return;
            }

            if (!data.success) {
                showCallout(callout, 'error', data.message || 'Failed to start deploy.');
                setBtn(btn, false, '⚡ Deploy to Live Site');
                setStatus('ready', '🟢 Ready to Deploy');
                setStep(2);
                return;
            }

            pollToken    = data.deploy_token;
            renderedLogs = 0;

            let pendingChecks = 0;
            if (pollTimer) clearInterval(pollTimer);

            // Stream logs in real-time every 1000ms
            pollTimer = setInterval(async () => {
                const statusData = await fetchStatus(pollToken);
                if (!statusData) return;

                renderNewLogs(statusData.logs || []);

                if (statusData.status === 'pending') {
                    pendingChecks++;
                    if (pendingChecks > 8) {
                        clearInterval(pollTimer);
                        appendLog('❌ Deploy process timed out while pending. Check server execution permissions.', 'log-line log-error');
                        setBtn(btn, false, '⚡ Deploy to Live Site');
                        setStatus('failed', '❌ Deploy failed');
                        setStep(2);
                    }
                    return;
                }

                pendingChecks = 0;

                if (statusData.status === 'complete' || statusData.status === 'failed') {
                    clearInterval(pollTimer);
                    onDeployFinished(statusData, btn, callout, branch);
                }
            }, 1000);

        } catch (err) {
            showCallout(callout, 'error', 'Network error: ' + err.message);
            setBtn(btn, false, '⚡ Deploy to Live Site');
            setStatus('ready', '🟢 Ready to Deploy');
            setStep(2);
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
            setStatus('done', '✅ Deployed Successfully');
            setBtn(btn, false, '✅ Deploy Complete');
            document.getElementById('postActions').classList.add('visible');
            setTimeout(() => setBtn(btn, false, '⚡ Deploy to Live Site'), 5000);
            setStep(4); // All 3 steps complete
        } else {
            setStatus('failed', '❌ Deploy Failed');
            showCallout(callout, 'error', data.message || 'Deployment failed — check terminal output for details.');
            setBtn(btn, false, '⚡ Deploy to Live Site');
            setStep(2); // Return to branch config
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

        const isSuccess = msg.includes('[SUCCESS]') || msg.includes('✅') || msg.includes('DONE');
        const isError   = msg.includes('[AUTH_ERROR]') || msg.includes('[EXCEPTION]') || msg.includes('❌') || msg.includes('ERROR');
        const isWarn    = msg.includes('⚠️') || msg.includes('WARN');

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

    // ── Step indicator (Accurate 1, 2, 3 state machine) ──────────
    function setStep(step) {
        // step 1: Auth active
        // step 2: Auth done (✓), Branch active (2), Deploy pending (3)
        // step 3: Auth done (✓), Branch done (✓), Deploy active (3)
        // step 4: Auth done (✓), Branch done (✓), Deploy done (✓)

        const s1 = document.getElementById('step1');
        const s2 = document.getElementById('step2');
        const s3 = document.getElementById('step3');

        const d1 = document.getElementById('stepDot1');
        const d2 = document.getElementById('stepDot2');
        const d3 = document.getElementById('stepDot3');

        [s1, s2, s3].forEach(el => el.classList.remove('active', 'complete'));

        if (step === 1) {
            s1.classList.add('active');
            d1.textContent = '1';
            d2.textContent = '2';
            d3.textContent = '3';
        } else if (step === 2) {
            s1.classList.add('complete');
            s2.classList.add('active');
            d1.textContent = '✓';
            d2.textContent = '2';
            d3.textContent = '3';
        } else if (step === 3) {
            s1.classList.add('complete');
            s2.classList.add('complete');
            s3.classList.add('active');
            d1.textContent = '✓';
            d2.textContent = '✓';
            d3.textContent = '3';
        } else if (step >= 4) {
            s1.classList.add('complete');
            s2.classList.add('complete');
            s3.classList.add('complete');
            d1.textContent = '✓';
            d2.textContent = '✓';
            d3.textContent = '✓';
        }
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

