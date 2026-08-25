<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>RelayIQ — Production Deployment Console</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|geist-mono:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        /* ── Design System & Theme Tokens (Light Enterprise) ──────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-body:       #f8fafc;
            --bg-canvas:     #ffffff;
            --surface-1:     #f8fafc;
            --surface-2:     #f1f5f9;
            --surface-3:     #e2e8f0;
            --surface-light: #ffffff;
            --border-subtle: #e2e8f0;
            --border-med:    #cbd5e1;
            --border-focus:  #2563eb;

            --primary:       #2563eb;
            --primary-h:     #1d4ed8;
            --primary-glow:  rgba(37, 99, 235, 0.15);

            --success:       #16a34a;
            --success-glow:  rgba(22, 163, 74, 0.15);
            --success-bg:    #f0fdf4;
            --success-border:#bbf7d0;
            --success-text:  #15803d;

            --danger:        #dc2626;
            --danger-glow:   rgba(220, 38, 38, 0.15);
            --danger-bg:     #fef2f2;
            --danger-border: #fecaca;
            --danger-text:   #991b1b;

            --warn:          #d97706;
            --warn-glow:     rgba(217, 119, 6, 0.15);
            --warn-bg:       #fffbeb;
            --warn-border:   #fde68a;
            --warn-text:     #92400e;

            --text-main:     #0f172a;
            --text-secondary:#475569;
            --text-dim:      #64748b;
            --text-muted:    #94a3b8;
            --text-inverse:  #ffffff;

            --mono:          'Geist Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            --sans:          'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

            --radius-xl:     12px;
            --radius-lg:     10px;
            --radius-md:     8px;
            --radius-sm:     6px;

            --shadow-card:   0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --shadow-float:  0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: var(--sans);
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.5;
        }

        /* ── Header Sentinel ────────────────────────────────────── */
        .app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 28px;
            background: var(--bg-canvas);
            border-bottom: 1px solid var(--border-subtle);
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }

        .header-logo-img {
            height: 28px;
            width: auto;
            object-fit: contain;
        }

        .header-fallback-logo {
            display: none;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 16px;
            letter-spacing: -0.02em;
            color: var(--text-main);
        }

        .header-badges {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .console-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .env-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ── Status Sentinels ───────────────────────────────────── */
        .status-sentinel {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            border: 1px solid transparent;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .status-sentinel.locked {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #475569;
        }
        .status-sentinel.ready {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: var(--success-text);
        }
        .status-sentinel.deploying {
            background: var(--warn-bg);
            border-color: var(--warn-border);
            color: var(--warn-text);
        }
        .status-sentinel.done {
            background: var(--success-bg);
            border-color: var(--success-border);
            color: var(--success-text);
        }
        .status-sentinel.failed {
            background: var(--danger-bg);
            border-color: var(--danger-border);
            color: var(--danger-text);
        }

        .beacon-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
            position: relative;
        }
        .status-sentinel.deploying .beacon-dot,
        .status-sentinel.ready .beacon-dot {
            animation: pulseGlow 1.8s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.8); }
        }

        .btn-lock-session {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 11px;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-lock-session:hover {
            background: var(--surface-1);
            border-color: var(--border-med);
            color: var(--text-main);
        }

        /* ── Telemetry & Overview Bar ────────────────────────────── */
        .telemetry-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            max-width: 1480px;
            width: 100%;
            margin: 18px auto 0;
            padding: 0 28px;
        }

        .telemetry-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-shadow: var(--shadow-card);
        }

        .telemetry-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .telemetry-label svg {
            color: var(--text-muted);
        }

        .telemetry-value {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: var(--mono);
        }

        /* ── Main Operations Grid Layout ────────────────────────── */
        .ops-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 440px 1fr;
            gap: 20px;
            max-width: 1480px;
            width: 100%;
            margin: 18px auto;
            padding: 0 28px 28px;
        }

        .deck-card {
            background: var(--bg-canvas);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 22px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            gap: 18px;
            position: relative;
        }

        /* ── Workflow Stepper Navigation ─────────────────────────── */
        .workflow-stepper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-subtle);
            position: relative;
        }

        .workflow-step {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dim);
            transition: color 0.15s ease;
        }

        .workflow-step:not(:last-child)::after {
            content: '';
            width: 24px;
            height: 1px;
            background: var(--border-subtle);
            margin-left: 8px;
            transition: background 0.15s ease;
        }

        .workflow-step.complete:not(:last-child)::after {
            background: var(--success);
        }

        .step-index {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 1px solid var(--border-med);
            color: var(--text-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            font-family: var(--mono);
            transition: all 0.15s ease;
        }

        .workflow-step.active {
            color: var(--text-main);
        }
        .workflow-step.active .step-index {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
        }

        .workflow-step.complete {
            color: var(--text-secondary);
        }
        .workflow-step.complete .step-index {
            background: var(--success);
            border-color: var(--success);
            color: #ffffff;
        }

        /* ── Form Controls & Typography ──────────────────────────── */
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-input, .form-select {
            width: 100%;
            background: #ffffff;
            border: 1px solid var(--border-med);
            color: var(--text-main);
            padding: 9px 12px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-family: var(--sans);
            font-weight: 500;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 34px;
            cursor: pointer;
        }
        .form-select option {
            background: #ffffff;
            color: var(--text-main);
            padding: 8px;
        }

        .input-with-action {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-with-action .form-input {
            padding-right: 40px;
        }
        .input-toggle-visibility {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s;
        }
        .input-toggle-visibility:hover {
            color: var(--text-main);
        }

        /* ── Pre-flight Execution Plan Summary ──────────────────── */
        .preflight-summary {
            background: var(--surface-1);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .preflight-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
        }

        .preflight-steps {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .preflight-step-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        .preflight-step-row svg {
            color: var(--primary);
            flex-shrink: 0;
        }

        /* ── Production Context Warning ─────────────────────────── */
        .prod-context-box {
            display: none;
            background: var(--warn-bg);
            border: 1px solid var(--warn-border);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            gap: 10px;
            align-items: flex-start;
        }
        .prod-context-box.visible {
            display: flex;
        }
        .prod-warning-icon {
            color: var(--warn);
            flex-shrink: 0;
            margin-top: 1px;
        }
        .prod-warning-content {
            font-size: 12.5px;
            color: var(--warn-text);
            line-height: 1.5;
        }
        .prod-warning-content strong {
            color: #78350f;
            font-weight: 700;
        }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            font-family: var(--sans);
            cursor: pointer;
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            width: 100%;
            text-decoration: none;
            user-select: none;
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-h);
            box-shadow: 0 2px 4px 0 rgba(37, 99, 235, 0.25);
        }
        .btn-primary:active:not(:disabled) {
            background: #1e40af;
        }

        .btn-danger {
            background: var(--danger);
            color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-danger:hover:not(:disabled) {
            background: #b91c1c;
        }

        .btn-ghost {
            background: #ffffff;
            border-color: var(--border-med);
            color: var(--text-secondary);
        }
        .btn-ghost:hover:not(:disabled) {
            background: var(--surface-1);
            border-color: var(--text-muted);
            color: var(--text-main);
        }

        .btn-success {
            background: var(--success);
            color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-success:hover:not(:disabled) {
            background: #15803d;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ── Callout Alerts ──────────────────────────────────────── */
        .callout-box {
            display: none;
            padding: 10px 12px;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            line-height: 1.5;
            align-items: flex-start;
            gap: 8px;
        }
        .callout-box.error {
            display: flex;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }
        .callout-box.success {
            display: flex;
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }
        .callout-box.info {
            display: flex;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        /* ── Active Deployment Workspace (Left Panel in Deploy Mode) */
        .deploy-workspace {
            display: none;
            flex-direction: column;
            gap: 16px;
        }
        .deploy-workspace.visible {
            display: flex;
        }

        .deploy-metrics-strip {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: var(--surface-1);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 12px 14px;
        }

        .deploy-metric-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .deploy-metric-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
        }
        .deploy-metric-val {
            font-size: 13.5px;
            font-weight: 700;
            font-family: var(--mono);
            color: var(--text-main);
        }
        .deploy-metric-val.stopwatch {
            color: var(--primary);
        }

        /* ── Pipeline Stages Visualizer ──────────────────────────── */
        .pipeline-tracker {
            display: flex;
            flex-direction: column;
            gap: 6px;
            background: var(--surface-1);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px;
        }

        .pipeline-tracker-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pipeline-stage-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 10px;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            transition: all 0.15s ease;
        }

        .stage-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
        }

        .stage-icon-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--surface-2);
            border: 1px solid var(--border-med);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: var(--text-dim);
            flex-shrink: 0;
            transition: all 0.15s ease;
        }

        .pipeline-stage-item.pending .stage-icon-dot {
            background: var(--surface-2);
            color: var(--text-dim);
        }
        .pipeline-stage-item.running {
            border-color: #bfdbfe;
            background: #eff6ff;
        }
        .pipeline-stage-item.running .stage-info {
            color: var(--text-main);
            font-weight: 600;
        }
        .pipeline-stage-item.running .stage-icon-dot {
            background: var(--primary);
            border-color: var(--primary);
            color: #ffffff;
        }
        .pipeline-stage-item.completed {
            border-color: var(--success-border);
            background: var(--success-bg);
        }
        .pipeline-stage-item.completed .stage-info {
            color: var(--text-secondary);
        }
        .pipeline-stage-item.completed .stage-icon-dot {
            background: var(--success);
            border-color: var(--success);
            color: #ffffff;
        }
        .pipeline-stage-item.failed {
            border-color: var(--danger-border);
            background: var(--danger-bg);
        }
        .pipeline-stage-item.failed .stage-icon-dot {
            background: var(--danger);
            border-color: var(--danger);
            color: #ffffff;
        }

        .stage-status-badge {
            font-size: 11px;
            font-weight: 600;
            font-family: var(--mono);
            color: var(--text-muted);
        }
        .pipeline-stage-item.running .stage-status-badge {
            color: var(--primary);
        }
        .pipeline-stage-item.completed .stage-status-badge {
            color: var(--success-text);
        }
        .pipeline-stage-item.failed .stage-status-badge {
            color: var(--danger-text);
        }

        /* ── Post-Deploy Completion Receipt ──────────────────────── */
        .completion-receipt {
            display: none;
            background: #ffffff;
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 14px;
            flex-direction: column;
            gap: 10px;
        }
        .completion-receipt.visible {
            display: flex;
        }
        .receipt-status-banner {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
        }
        .receipt-status-banner.success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }
        .receipt-status-banner.failed {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        .receipt-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            font-size: 12px;
        }
        .receipt-grid-item {
            background: var(--surface-1);
            padding: 8px 10px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-subtle);
        }
        .receipt-grid-label {
            color: var(--text-dim);
            font-size: 10.5px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .receipt-grid-value {
            color: var(--text-main);
            font-family: var(--mono);
            font-weight: 600;
            margin-top: 2px;
        }

        .receipt-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 4px;
        }

        /* ── Right Panel: Terminal Deck & Audit History ──────────── */
        .deck-right {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .deck-tabs {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-subtle);
            padding-bottom: 8px;
        }

        .deck-tab-list {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .deck-tab-btn {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-dim);
            padding: 5px 12px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .deck-tab-btn:hover {
            color: var(--text-main);
            background: var(--surface-2);
        }
        .deck-tab-btn.active {
            background: #ffffff;
            border-color: var(--border-med);
            color: var(--text-main);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        /* ── Live Developer Operations Terminal ──────────────────── */
        .terminal-container {
            display: flex;
            flex-direction: column;
            background: #090d16;
            border: 1px solid #1e293b;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
        }

        .terminal-container.expanded {
            position: fixed;
            inset: 20px;
            z-index: 100;
            max-height: none;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .terminal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 16px;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
        }

        .terminal-window-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .term-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }
        .term-dot.red    { background: #ef4444; }
        .term-dot.yellow { background: #f59e0b; }
        .term-dot.green  { background: #10b981; }

        .terminal-title-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11.5px;
            font-family: var(--mono);
            color: #94a3b8;
        }
        .stream-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .stream-indicator.offline {
            background: rgba(148, 163, 184, 0.12);
            color: #94a3b8;
        }

        .terminal-toolbar {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .term-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            color: #cbd5e1;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .term-action-btn:hover {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
        }
        .term-action-btn.active {
            background: rgba(37, 99, 235, 0.25);
            border-color: rgba(59, 130, 246, 0.4);
            color: #93c5fd;
        }

        .terminal-viewport {
            background: #070a10;
            padding: 14px 18px;
            font-family: var(--mono);
            font-size: 12px;
            line-height: 1.6;
            color: #e2e8f0;
            height: 480px;
            overflow-y: auto;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #334155 transparent;
            position: relative;
        }
        .terminal-container.expanded .terminal-viewport {
            height: calc(100vh - 100px);
        }

        .terminal-empty-view {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #64748b;
            text-align: center;
            font-size: 13px;
        }

        .terminal-log-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 2px 0;
            border-radius: 2px;
            transition: background 0.1s;
        }
        .terminal-log-row:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .log-line-num {
            color: #475569;
            user-select: none;
            font-size: 11px;
            min-width: 28px;
            text-align: right;
            flex-shrink: 0;
        }

        .log-line-text {
            word-break: break-all;
            white-space: pre-wrap;
            flex: 1;
        }

        .log-line-text.highlight-success {
            color: #34d399;
            font-weight: 600;
        }
        .log-line-text.highlight-error {
            color: #f87171;
            font-weight: 600;
        }
        .log-line-text.highlight-warn {
            color: #fbbf24;
        }
        .log-line-text.highlight-info {
            color: #60a5fa;
        }

        /* Auto-scroll pause badge */
        .autoscroll-badge {
            position: absolute;
            bottom: 14px;
            right: 18px;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 9999px;
            padding: 5px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #60a5fa;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            align-items: center;
            gap: 6px;
            z-index: 10;
        }
        .autoscroll-badge:hover {
            background: var(--primary);
            color: #ffffff;
        }

        /* ── Deployment Audit History Section ────────────────────── */
        .history-section {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .history-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 60px 20px;
            color: var(--text-dim);
            text-align: center;
            border: 1px dashed var(--border-med);
            border-radius: var(--radius-lg);
            background: var(--bg-canvas);
        }

        .history-item-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: var(--bg-canvas);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            transition: all 0.15s ease;
        }
        .history-item-card:hover {
            background: var(--surface-1);
            border-color: var(--border-med);
        }
        .history-item-card.is-latest {
            border-color: #bfdbfe;
            background: #f8fafc;
        }

        .history-left-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .history-branch-line {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-branch-badge {
            font-size: 12px;
            font-weight: 700;
            font-family: var(--mono);
            color: var(--text-main);
            background: var(--surface-2);
            padding: 2px 7px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-subtle);
        }
        .history-item-card.is-latest .history-branch-badge {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }

        .history-latest-pill {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .history-sub-meta {
            font-size: 11.5px;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: var(--mono);
        }

        .history-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 9px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            font-family: var(--mono);
        }
        .history-status-badge.success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }
        .history-status-badge.failed {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        /* ── Modal Dialog: Production Confirmation ───────────────── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open {
            display: flex;
        }

        .modal-dialog-box {
            background: var(--bg-canvas);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-xl);
            padding: 24px 28px;
            max-width: 480px;
            width: 100%;
            box-shadow: var(--shadow-float);
            display: flex;
            flex-direction: column;
            gap: 16px;
            animation: modalPop 0.15s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            0% { opacity: 0; transform: scale(0.97) translateY(4px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-top-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--warn-bg);
            border: 1px solid var(--warn-border);
            color: var(--warn);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-heading {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.01em;
        }

        .modal-body-text {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .modal-impact-checklist {
            background: var(--surface-1);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .modal-action-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4px;
        }

        /* ── Spinners & Utilities ────────────────────────────────── */
        .spinner {
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden { display: none !important; }

        /* ── Responsive Adaptations ──────────────────────────────── */
        @media (max-width: 1080px) {
            .telemetry-strip {
                grid-template-columns: 1fr 1fr;
            }
            .ops-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .app-header {
                padding: 12px 16px;
            }
            .header-badges {
                display: none;
            }
            .telemetry-strip {
                grid-template-columns: 1fr;
                padding: 0 16px;
            }
            .ops-grid {
                padding: 0 16px 20px;
            }
            .deck-card {
                padding: 18px;
            }
            .terminal-viewport {
                height: 380px;
                padding: 12px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<!-- ── Top Operations Header Sentinel ────────────────────────── -->
<header class="app-header">
    <a href="/deploy" class="header-brand" title="RelayIQ Deploy Console">
        <img src="/images/branding/relaysiq-wordmark-dark.png?v=5"
             alt="RelayIQ"
             class="header-logo-img"
             onerror="this.style.display='none'; document.getElementById('headerFallbackLogo').style.display='flex';" />
        <div id="headerFallbackLogo" class="header-fallback-logo">
            <span>⚡ RelayIQ</span>
        </div>
        <div class="header-badges">
            <span class="console-pill">Deploy Console</span>
            <span class="env-pill">Production Gateway</span>
        </div>
    </a>

    <div class="header-actions">
        <!-- Sentinel status pill -->
        <div class="status-sentinel locked" id="globalStatusSentinel">
            <span class="beacon-dot"></span>
            <span id="globalStatusText">Locked</span>
        </div>

        <!-- Lock session action -->
        <button class="btn-lock-session hidden" id="headerLockBtn" onclick="handleLockSession()" title="Lock console and expire session">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            <span>Lock Console</span>
        </button>
    </div>
</header>

<!-- ── Telemetry & Overview Bar ──────────────────────────────── -->
<section class="telemetry-strip">
    <div class="telemetry-card">
        <span class="telemetry-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="m4.93 4.93 4.24 4.24"></path><path d="m14.83 9.17 4.24-4.24"></path><path d="m14.83 14.83 4.24 4.24"></path><path d="m9.17 14.83-4.24 4.24"></path></svg>
            Target Gateway
        </span>
        <span class="telemetry-value" style="color: #2563eb;">Production (Live Site)</span>
    </div>

    <div class="telemetry-card">
        <span class="telemetry-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Latest Release
        </span>
        <span class="telemetry-value" id="telemetryLatestDeploy">
            @if(count($history) > 0 && isset($history[0]['timestamp']))
                {{ \Carbon\Carbon::parse($history[0]['timestamp'])->diffForHumans() }}
            @else
                None recorded
            @endif
        </span>
    </div>

    <div class="telemetry-card">
        <span class="telemetry-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
            Execution Engine
        </span>
        <span class="telemetry-value" style="color: #16a34a;">
            {{ $backgroundMode ? 'Async Background Polling' : 'Synchronous Engine' }}
        </span>
    </div>

    <div class="telemetry-card">
        <span class="telemetry-label">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            Production Guard
        </span>
        <span class="telemetry-value" style="color: #d97706;">Typed Confirmation</span>
    </div>
</section>

<!-- ── Main Control Desk ─────────────────────────────────────── -->
<main class="ops-grid">

    <!-- ── Left Column: Deployment Commander ───────────────────── -->
    <div class="deck-card">

        <!-- Operational Workflow Stepper -->
        <nav class="workflow-stepper" aria-label="Deployment steps">
            <div class="workflow-step active" id="stepIndicator1">
                <span class="step-index" id="stepIndex1">1</span>
                <span>Authenticate</span>
            </div>
            <div class="workflow-step" id="stepIndicator2">
                <span class="step-index" id="stepIndex2">2</span>
                <span>Target & Review</span>
            </div>
            <div class="workflow-step" id="stepIndicator3">
                <span class="step-index" id="stepIndex3">3</span>
                <span>Execute & Monitor</span>
            </div>
        </nav>

        <!-- ── Step 1: Authentication Gateway View ──────────────── -->
        <div id="authSection" class="deck-section">
            <div class="form-group" style="margin-bottom: 16px;">
                <label for="authSecret" class="form-label">
                    <span>Deploy Console Password</span>
                    <span style="font-size: 11px; color: var(--text-dim); font-family: var(--mono);">DEPLOY_SECRET</span>
                </label>
                <div class="input-with-action">
                    <input type="password" id="authSecret" class="form-input"
                           placeholder="Enter deploy password"
                           autocomplete="current-password"
                           autofocus required />
                    <button type="button" class="input-toggle-visibility" onclick="togglePasswordVisibility('authSecret', this)" title="Toggle password visibility">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <div id="authCallout" class="callout-box error" style="margin-bottom: 16px;"></div>

            <button class="btn btn-primary" id="authBtn" onclick="handleAuth()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                <span>Unlock Deployment Console</span>
            </button>
        </div>

        <!-- ── Step 2: Target Selection & Pre-Flight Review ──────── -->
        <div id="configSection" class="deck-section hidden">
            <div class="form-group" style="margin-bottom: 14px;">
                <label for="branchSelect" class="form-label">
                    <span>Select Git Branch</span>
                    <span style="font-size: 11px; color: #16a34a; font-family: var(--mono);">origin/remote</span>
                </label>
                <select id="branchSelect" class="form-select" onchange="onBranchChange()">
                    <!-- Dynamically populated from backend -->
                </select>
            </div>

            <!-- Custom branch entry -->
            <div id="customBranchField" class="form-group hidden" style="margin-bottom: 14px;">
                <label for="customBranch" class="form-label">Custom Branch Reference</label>
                <input type="text" id="customBranch" class="form-input" placeholder="e.g. feature/checkout-v2" oninput="onCustomBranchInput()" />
            </div>

            <!-- Production Context Notice -->
            <div class="prod-context-box" id="prodWarning">
                <svg class="prod-warning-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <div class="prod-warning-content">
                    You are deploying <strong>main</strong> to the <strong>live production site</strong>. This will execute database migrations and replace production application caches.
                </div>
            </div>

            <!-- Pre-Flight Pipeline Plan Checklist -->
            <div class="preflight-summary" style="margin-top: 14px; margin-bottom: 16px;">
                <div class="preflight-header">
                    <span>Pre-Flight Execution Plan</span>
                    <span style="color: #2563eb;" id="preflightBranchLabel">main</span>
                </div>
                <div class="preflight-steps">
                    <div class="preflight-step-row">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Git fetch & reset to latest remote commits</span>
                    </div>
                    <div class="preflight-step-row">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Execute pending database migrations (<code style="color:#2563eb; background:#eff6ff; padding:1px 4px; border-radius:3px;">--force</code>)</span>
                    </div>
                    <div class="preflight-step-row">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Verify storage symlinks & compile caches</span>
                    </div>
                </div>
            </div>

            <div id="deployCallout" class="callout-box error" style="margin-bottom: 14px;"></div>

            <!-- Primary Action Button -->
            <button class="btn btn-primary" id="deployBtn" onclick="handleDeployTrigger()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                <span id="deployBtnLabel">Deploy main to Production</span>
            </button>
        </div>

        <!-- ── Step 3: Active Deployment Workspace ───────────────── -->
        <div id="deployWorkspace" class="deploy-workspace">

            <!-- Active Deploy Stopwatch & Target Strip -->
            <div class="deploy-metrics-strip">
                <div class="deploy-metric-item">
                    <span class="deploy-metric-label">Target Environment</span>
                    <span class="deploy-metric-val" id="activeDeployTarget">main → Production</span>
                </div>
                <div class="deploy-metric-item">
                    <span class="deploy-metric-label">Elapsed Time</span>
                    <span class="deploy-metric-val stopwatch" id="activeStopwatch">00:00.0s</span>
                </div>
            </div>

            <!-- Pipeline Real-time Stages Visualizer -->
            <div class="pipeline-tracker">
                <div class="pipeline-tracker-title">
                    <span>Pipeline Progress</span>
                    <span id="pipelineProgressLabel">Executing…</span>
                </div>

                <!-- Stage 1: Git -->
                <div class="pipeline-stage-item running" id="stage1">
                    <div class="stage-info">
                        <span class="stage-icon-dot" id="stageDot1">1</span>
                        <span>Code Fetch & Git Sync</span>
                    </div>
                    <span class="stage-status-badge" id="stageBadge1">Running…</span>
                </div>

                <!-- Stage 2: Database -->
                <div class="pipeline-stage-item pending" id="stage2">
                    <div class="stage-info">
                        <span class="stage-icon-dot" id="stageDot2">2</span>
                        <span>Database Migrations</span>
                    </div>
                    <span class="stage-status-badge" id="stageBadge2">Pending</span>
                </div>

                <!-- Stage 3: Storage -->
                <div class="pipeline-stage-item pending" id="stage3">
                    <div class="stage-info">
                        <span class="stage-icon-dot" id="stageDot3">3</span>
                        <span>Storage Symlinks</span>
                    </div>
                    <span class="stage-status-badge" id="stageBadge3">Pending</span>
                </div>

                <!-- Stage 4: Cache -->
                <div class="pipeline-stage-item pending" id="stage4">
                    <div class="stage-info">
                        <span class="stage-icon-dot" id="stageDot4">4</span>
                        <span>Application Cache Optimization</span>
                    </div>
                    <span class="stage-status-badge" id="stageBadge4">Pending</span>
                </div>

                <!-- Stage 5: Finalized -->
                <div class="pipeline-stage-item pending" id="stage5">
                    <div class="stage-info">
                        <span class="stage-icon-dot" id="stageDot5">5</span>
                        <span>Pipeline Finalized</span>
                    </div>
                    <span class="stage-status-badge" id="stageBadge5">Pending</span>
                </div>
            </div>

            <!-- Completion Summary Receipt Card -->
            <div class="completion-receipt" id="completionReceipt">
                <div class="receipt-status-banner success" id="receiptBanner">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span id="receiptStatusText">Deployment Succeeded</span>
                </div>

                <div class="receipt-grid">
                    <div class="receipt-grid-item">
                        <div class="receipt-grid-label">Branch</div>
                        <div class="receipt-grid-value" id="receiptBranch">main</div>
                    </div>
                    <div class="receipt-grid-item">
                        <div class="receipt-grid-label">Total Duration</div>
                        <div class="receipt-grid-value" id="receiptDuration">0.00s</div>
                    </div>
                    <div class="receipt-grid-item">
                        <div class="receipt-grid-label">Timestamp</div>
                        <div class="receipt-grid-value" id="receiptTimestamp">Just now</div>
                    </div>
                    <div class="receipt-grid-item">
                        <div class="receipt-grid-label">Target Gateway</div>
                        <div class="receipt-grid-value" style="color: #2563eb;">Production</div>
                    </div>
                </div>

                <div class="receipt-actions">
                    <a href="/" target="_blank" class="btn btn-success" id="openSiteBtn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                        <span>Open Live Production Site</span>
                    </a>
                    <button class="btn btn-ghost" onclick="resetToTargetSelection()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                        <span>Trigger Another Deployment</span>
                    </button>
                </div>
            </div>

        </div>

    </div>

    <!-- ── Right Column: Live Terminal & Audit Deck ────────────── -->
    <div class="deck-right">

        <!-- Top Tab Bar -->
        <div class="deck-tabs">
            <div class="deck-tab-list">
                <button class="deck-tab-btn active" id="tabTerminalBtn" onclick="switchDeckTab('terminal')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                    <span>Operations Terminal</span>
                </button>
                <button class="deck-tab-btn" id="tabHistoryBtn" onclick="switchDeckTab('history')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Deployment History ({{ count($history) }})</span>
                </button>
            </div>
        </div>

        <!-- ── Tab 1: Terminal Console ──────────────────────────── -->
        <div id="terminalTabContent" class="terminal-container">
            <div class="terminal-header">
                <div class="terminal-window-controls">
                    <span class="term-dot red"></span>
                    <span class="term-dot yellow"></span>
                    <span class="term-dot green"></span>
                </div>

                <div class="terminal-title-meta">
                    <span>Live Output Stream</span>
                    <span class="stream-indicator offline" id="streamStatusBadge">Idle</span>
                    <span id="logLineCount" style="color: #64748b;">0 lines</span>
                </div>

                <div class="terminal-toolbar">
                    <button class="term-action-btn" id="autoScrollToggleBtn" onclick="toggleAutoScroll()" title="Toggle Auto-scroll">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline></svg>
                        <span id="autoScrollLabel">Auto-scroll: ON</span>
                    </button>
                    <button class="term-action-btn" id="copyLogsBtn" onclick="copyTerminalLogs()" title="Copy all logs to clipboard">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Copy</span>
                    </button>
                    <button class="term-action-btn" onclick="clearTerminalView()" title="Clear terminal viewport">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span>Clear</span>
                    </button>
                    <button class="term-action-btn" id="expandTerminalBtn" onclick="toggleTerminalFullscreen()" title="Toggle Fullscreen">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                        <span>Expand</span>
                    </button>
                </div>
            </div>

            <!-- Scrollable Terminal Body -->
            <div class="terminal-viewport" id="terminalViewport" onscroll="handleTerminalScroll()">
                <div class="terminal-empty-view" id="terminalEmptyState">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                    <span>Terminal is waiting for deployment execution to stream output.</span>
                </div>
                <div id="terminalLogRows"></div>

                <!-- Auto-scroll resume badge -->
                <button class="autoscroll-badge" id="resumeAutoScrollBadge" onclick="resumeAutoScroll()">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline></svg>
                    <span>Resume Auto-Scroll</span>
                </button>
            </div>
        </div>

        <!-- ── Tab 2: Deployment Audit History ───────────────────── -->
        <div id="historyTabContent" class="history-section hidden">
            @if(count($history) === 0)
                <div class="history-empty-state">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <div>No deployment records found.<br><span style="font-size:12px;color:var(--text-dim);">Execute a deployment to begin recording the audit trail.</span></div>
                </div>
            @else
                @foreach($history as $index => $entry)
                    @php
                        $isLatest = ($index === 0);
                        $status = $entry['status'] ?? 'unknown';
                        $branch = $entry['branch'] ?? 'main';
                        $duration = $entry['duration'] ?? null;
                        $ip = $entry['ip'] ?? null;
                        $timestamp = $entry['timestamp'] ?? null;
                    @endphp
                    <div class="history-item-card {{ $isLatest ? 'is-latest' : '' }}">
                        <div class="history-left-meta">
                            <div class="history-branch-line">
                                <span class="history-branch-badge">{{ $branch }}</span>
                                @if($isLatest)
                                    <span class="history-latest-pill">Active Release</span>
                                @endif
                            </div>
                            <div class="history-sub-meta">
                                @if($timestamp)
                                    <span>{{ \Carbon\Carbon::parse($timestamp)->diffForHumans() }}</span>
                                    <span>·</span>
                                    <span title="{{ $timestamp }}">{{ \Carbon\Carbon::parse($timestamp)->format('Y-m-d H:i:s T') }}</span>
                                @endif
                                @if($duration)
                                    <span>·</span>
                                    <span>{{ $duration }}s</span>
                                @endif
                                @if($ip)
                                    <span>·</span>
                                    <span>{{ $ip }}</span>
                                @endif
                            </div>
                        </div>

                        <span class="history-status-badge {{ $status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : '') }}">
                            @if($status === 'success')
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Success</span>
                            @elseif($status === 'failed')
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                <span>Failed</span>
                            @else
                                <span>{{ ucfirst($status) }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            @endif
        </div>

    </div>

</main>

<!-- ── Production Confirmation Modal Dialog ─────────────────── -->
<div class="modal-backdrop" id="productionModal" onclick="handleModalBackdropClick(event)">
    <div class="modal-dialog-box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-top-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>

        <div>
            <h2 class="modal-heading" id="modalTitle">Authorize Production Deployment</h2>
            <p class="modal-body-text" style="margin-top: 6px;">
                You are executing a deployment of <strong style="color:#2563eb;" id="modalBranchLabel">main</strong> directly to the live production server.
            </p>
        </div>

        <div class="modal-impact-checklist">
            <div style="font-weight:700; color:var(--text-main); margin-bottom: 2px;">Execution Impact Summary:</div>
            <div>• Pulls & resets live codebase to latest remote commit.</div>
            <div>• Runs all pending database schema migrations.</div>
            <div>• Re-compiles production route, view, and config caches.</div>
        </div>

        <div class="form-group">
            <label for="confirmInput" class="form-label">
                <span>Type <code style="color:#dc2626; font-weight:700; background:#fef2f2; padding:1px 5px; border-radius:3px; border:1px solid #fecaca;">deploy</code> to confirm authorization:</span>
            </label>
            <input type="text" id="confirmInput" class="form-input"
                   placeholder="Type deploy to confirm"
                   autocomplete="off"
                   oninput="onConfirmInputChange()" />
        </div>

        <div class="modal-action-row">
            <button class="btn btn-ghost" onclick="closeProductionModal()">Cancel</button>
            <button class="btn btn-danger" id="confirmExecuteBtn" disabled onclick="executeConfirmedDeploy()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                <span>Authorize & Deploy</span>
            </button>
        </div>
    </div>
</div>

<script>
    // ── Application State ────────────────────────────────────────
    let authToken       = sessionStorage.getItem('deploy_auth_token') || '';
    let pendingBranch   = 'main';
    let pollTimer       = null;
    let pollToken       = '';
    let renderedLogs    = 0;
    let logLineCounter  = 0;
    let autoScroll      = true;
    let stopwatchTimer  = null;
    let stopwatchStart  = 0;

    // ── Initialization Lifecycle ─────────────────────────────────
    (function init() {
        if (authToken) {
            showAuthenticatedView();
        } else {
            setWorkflowStep(1);
            setSentinelStatus('locked', 'Locked');
        }

        // Handle ESC key for modal
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('productionModal').classList.contains('open')) {
                closeProductionModal();
            }
        });
    })();

    // ── Authentication Flow ──────────────────────────────────────
    async function handleAuth() {
        const btn      = document.getElementById('authBtn');
        const secret   = document.getElementById('authSecret').value.trim();
        const callout  = document.getElementById('authCallout');

        if (!secret) {
            showCallout(callout, 'error', 'Please enter your deployment password.');
            return;
        }

        setButtonState(btn, true, '<span class="spinner"></span> Authenticating…');

        try {
            const res = await postJson('/deploy/auth', { secret });
            const data = await res.json().catch(() => ({ success: false, message: 'Server returned HTTP ' + res.status }));

            if (data.success && data.token) {
                authToken = data.token;
                sessionStorage.setItem('deploy_auth_token', authToken);
                populateBranchList(data.branches || ['main']);
                showAuthenticatedView();
                hideCallout(callout);
            } else {
                showCallout(callout, 'error', data.message || 'Authentication failed. Please verify the secret.');
                setButtonState(btn, false, '<span>Unlock Deployment Console</span>');
                document.getElementById('authSecret').focus();
                document.getElementById('authSecret').select();
            }
        } catch (err) {
            showCallout(callout, 'error', 'Network error communicating with server: ' + err.message);
            setButtonState(btn, false, '<span>Unlock Deployment Console</span>');
        }
    }

    function showAuthenticatedView() {
        document.getElementById('authSection').classList.add('hidden');
        document.getElementById('configSection').classList.remove('hidden');
        document.getElementById('headerLockBtn').classList.remove('hidden');
        setWorkflowStep(2);
        setSentinelStatus('ready', 'Ready to Deploy');
        document.getElementById('authSecret').value = '';
    }

    function handleLockSession() {
        authToken = '';
        sessionStorage.removeItem('deploy_auth_token');
        if (pollTimer) clearInterval(pollTimer);
        if (stopwatchTimer) clearInterval(stopwatchTimer);

        document.getElementById('authSection').classList.remove('hidden');
        document.getElementById('configSection').classList.add('hidden');
        document.getElementById('deployWorkspace').classList.remove('visible');
        document.getElementById('headerLockBtn').classList.add('hidden');

        setWorkflowStep(1);
        setSentinelStatus('locked', 'Locked');
        document.getElementById('authSecret').focus();
    }

    function togglePasswordVisibility(inputId, triggerBtn) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            triggerBtn.style.color = '#2563eb';
        } else {
            input.type = 'password';
            triggerBtn.style.color = '';
        }
    }

    // ── Branch Management ────────────────────────────────────────
    function populateBranchList(branches) {
        const sel = document.getElementById('branchSelect');
        sel.innerHTML = '';

        branches.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = (b === 'main') ? 'main (Production — Recommended)' : b;
            sel.appendChild(opt);
        });

        const customOpt = document.createElement('option');
        customOpt.value = '__custom__';
        customOpt.textContent = '+ Specify Custom Branch…';
        sel.appendChild(customOpt);

        onBranchChange();
    }

    function onBranchChange() {
        const selValue = document.getElementById('branchSelect').value;
        const isCustom = (selValue === '__custom__');
        const branch   = getSelectedBranchName();
        const isProd   = (branch === 'main');

        document.getElementById('customBranchField').classList.toggle('hidden', !isCustom);
        document.getElementById('prodWarning').classList.toggle('visible', isProd);
        document.getElementById('preflightBranchLabel').textContent = branch;

        const btnLabel = document.getElementById('deployBtnLabel');
        btnLabel.textContent = isProd ? 'Deploy main to Production' : `Deploy ${branch} to Server`;

        if (isCustom) {
            document.getElementById('customBranch').focus();
        }
    }

    function onCustomBranchInput() {
        onBranchChange();
    }

    function getSelectedBranchName() {
        const sel = document.getElementById('branchSelect');
        if (!sel) return 'main';
        const val = sel.value;
        if (val === '__custom__') {
            return document.getElementById('customBranch').value.trim() || 'main';
        }
        return val || 'main';
    }

    // ── Deployment Flow & Confirmation ───────────────────────────
    function handleDeployTrigger() {
        const branch = getSelectedBranchName();
        if (!branch) return;

        pendingBranch = branch;

        if (branch === 'main') {
            // Production deployment requires explicit confirmation
            document.getElementById('modalBranchLabel').textContent = branch;
            document.getElementById('confirmInput').value = '';
            document.getElementById('confirmExecuteBtn').disabled = true;
            document.getElementById('productionModal').classList.add('open');
            document.getElementById('confirmInput').focus();
        } else {
            initiateDeployment(branch);
        }
    }

    function onConfirmInputChange() {
        const val = document.getElementById('confirmInput').value.trim();
        document.getElementById('confirmExecuteBtn').disabled = (val.toLowerCase() !== 'deploy');
    }

    function closeProductionModal() {
        document.getElementById('productionModal').classList.remove('open');
        document.getElementById('confirmInput').value = '';
        document.getElementById('confirmExecuteBtn').disabled = true;
    }

    function handleModalBackdropClick(event) {
        if (event.target === document.getElementById('productionModal')) {
            closeProductionModal();
        }
    }

    function executeConfirmedDeploy() {
        closeProductionModal();
        initiateDeployment(pendingBranch);
    }

    // ── Active Deployment Execution Engine ───────────────────────
    async function initiateDeployment(branch) {
        const callout = document.getElementById('deployCallout');
        hideCallout(callout);

        // Switch UI to active workspace mode
        document.getElementById('configSection').classList.add('hidden');
        document.getElementById('deployWorkspace').classList.add('visible');
        document.getElementById('completionReceipt').classList.remove('visible');

        // Switch to Terminal tab automatically
        switchDeckTab('terminal');

        // Reset Pipeline stages
        resetPipelineStages();
        setWorkflowStep(3);
        setSentinelStatus('deploying', 'Deploying…');
        setStreamStatus(true, 'Live Stream');

        document.getElementById('activeDeployTarget').textContent = `${branch} → Production`;

        // Start stopwatch
        startStopwatch();

        // Clear terminal & append startup banner
        clearTerminalView();
        appendTerminalLine(`🚀 [${new Date().toISOString()}] Initiating deployment pipeline for [${branch}]…`, 'highlight-info');

        try {
            const res = await postJson('/deploy/start', { token: authToken, branch });
            const data = await res.json().catch(() => ({ success: false, message: 'Server returned HTTP ' + res.status }));

            if (res.status === 401 || data.message?.includes('expired') || data.message?.includes('Session')) {
                stopStopwatch();
                appendTerminalLine('❌ [AUTH_ERROR] Session expired or invalid. Please re-authenticate.', 'highlight-error');
                handleSessionExpired();
                return;
            }

            if (res.status === 409) {
                stopStopwatch();
                appendTerminalLine('⚠️ [LOCK_CONFLICT] A deployment is already running on the server.', 'highlight-warn');
                showCallout(callout, 'error', 'A deployment is currently in progress on the server. Please wait for completion.');
                resetToTargetSelection();
                return;
            }

            if (!data.success) {
                stopStopwatch();
                appendTerminalLine(`❌ [DEPLOY_ERROR] ${data.message || 'Failed to start deployment.'}`, 'highlight-error');
                showCallout(callout, 'error', data.message || 'Failed to start deployment.');
                onDeploymentFinished(false, data.message || 'Failed to start deployment.', branch, 0);
                return;
            }

            // Handle synchronous execution mode (e.g. servers without shell_exec background spawning)
            if (data.synchronous) {
                stopStopwatch();
                renderNewLogs(data.logs || []);
                analyzePipelineStages(data.logs || []);
                const isSuccess = (data.status === 'complete' || data.success === true);
                onDeploymentFinished(isSuccess, data.message, branch, data.duration);
                return;
            }

            pollToken = data.deploy_token;
            renderedLogs = 0;
            let consecutivePendingChecks = 0;

            if (pollTimer) clearInterval(pollTimer);

            // Poll deployment logs every 1000ms
            pollTimer = setInterval(async () => {
                const statusData = await fetchDeployStatus(pollToken);
                if (!statusData) return;

                renderNewLogs(statusData.logs || []);
                analyzePipelineStages(statusData.logs || []);

                if (statusData.status === 'pending') {
                    consecutivePendingChecks++;
                    if (consecutivePendingChecks > 10) {
                        clearInterval(pollTimer);
                        stopStopwatch();
                        appendTerminalLine('❌ Deploy process timed out while waiting for background execution.', 'highlight-error');
                        onDeploymentFinished(false, 'Deployment process timed out while pending.', branch, 0);
                    }
                    return;
                }

                consecutivePendingChecks = 0;

                if (statusData.status === 'complete' || statusData.status === 'failed') {
                    clearInterval(pollTimer);
                    stopStopwatch();
                    const isSuccess = (statusData.status === 'complete' || statusData.success === true);
                    onDeploymentFinished(isSuccess, statusData.message, branch, statusData.duration);
                }
            }, 1000);

        } catch (err) {
            stopStopwatch();
            appendTerminalLine(`❌ Network error executing deployment: ${err.message}`, 'highlight-error');
            onDeploymentFinished(false, err.message, branch, 0);
        }
    }

    async function fetchDeployStatus(token) {
        try {
            const res = await fetch('/deploy/status/' + token, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) return null;
            return await res.json();
        } catch {
            return null;
        }
    }

    function onDeploymentFinished(isSuccess, message, branch, duration) {
        setStreamStatus(false, 'Finished');

        const elapsedSec = duration || ((Date.now() - stopwatchStart) / 1000).toFixed(2);

        if (isSuccess) {
            setSentinelStatus('done', 'Deployed Successfully');
            markPipelineComplete();
            showCompletionReceipt(true, branch, elapsedSec);
        } else {
            setSentinelStatus('failed', 'Deployment Failed');
            markPipelineFailed(message);
            showCompletionReceipt(false, branch, elapsedSec, message);
        }
    }

    function showCompletionReceipt(isSuccess, branch, duration, errorMsg) {
        const receipt = document.getElementById('completionReceipt');
        const banner  = document.getElementById('receiptBanner');
        const text    = document.getElementById('receiptStatusText');
        const openBtn = document.getElementById('openSiteBtn');

        receipt.classList.add('visible');

        document.getElementById('receiptBranch').textContent = branch;
        document.getElementById('receiptDuration').textContent = `${duration}s`;
        document.getElementById('receiptTimestamp').textContent = new Date().toLocaleTimeString();

        if (isSuccess) {
            banner.className = 'receipt-status-banner success';
            text.textContent = 'Deployment Successfully Completed';
            openBtn.classList.remove('hidden');
        } else {
            banner.className = 'receipt-status-banner failed';
            text.textContent = errorMsg ? `Failed: ${errorMsg}` : 'Deployment Failed — Inspect Logs';
            openBtn.classList.add('hidden');
        }
    }

    function resetToTargetSelection() {
        if (pollTimer) clearInterval(pollTimer);
        if (stopwatchTimer) clearInterval(stopwatchTimer);

        document.getElementById('deployWorkspace').classList.remove('visible');
        document.getElementById('completionReceipt').classList.remove('visible');
        document.getElementById('configSection').classList.remove('hidden');

        setWorkflowStep(2);
        setSentinelStatus('ready', 'Ready to Deploy');
    }

    function handleSessionExpired() {
        handleLockSession();
        showCallout(document.getElementById('authCallout'), 'info', 'Your session expired or is invalid. Please enter your deployment password to re-authenticate.');
    }

    // ── Pipeline Stages Detection Engine ─────────────────────────
    function resetPipelineStages() {
        for (let i = 1; i <= 5; i++) {
            const stage = document.getElementById(`stage${i}`);
            const dot   = document.getElementById(`stageDot${i}`);
            const badge = document.getElementById(`stageBadge${i}`);
            stage.className = (i === 1) ? 'pipeline-stage-item running' : 'pipeline-stage-item pending';
            dot.textContent = `${i}`;
            badge.textContent = (i === 1) ? 'Running…' : 'Pending';
        }
        document.getElementById('pipelineProgressLabel').textContent = 'Syncing Code…';
    }

    function analyzePipelineStages(logs) {
        const combined = logs.join('\n');

        // Stage 1: Git Sync
        if (combined.includes('git reset') || combined.includes('git fetch')) {
            setStageState(1, 'running', 'Syncing…');
            document.getElementById('pipelineProgressLabel').textContent = 'Fetching Git commits…';
        }
        if (combined.includes('[migrate]') || combined.includes('Running database migrations') || combined.includes('storage:link')) {
            setStageState(1, 'completed', '✓ Done');
        }

        // Stage 2: Migrations
        if (combined.includes('[migrate]') || combined.includes('Running database migrations')) {
            setStageState(2, 'running', 'Migrating…');
            document.getElementById('pipelineProgressLabel').textContent = 'Running database migrations…';
        }
        if (combined.includes('storage:link') || combined.includes('[optimize]') || combined.includes('[optimize:clear]')) {
            setStageState(2, 'completed', '✓ Done');
        }

        // Stage 3: Storage
        if (combined.includes('storage:link')) {
            setStageState(3, 'running', 'Linking…');
        }
        if (combined.includes('[optimize]') || combined.includes('[optimize:clear]')) {
            setStageState(3, 'completed', '✓ Done');
        }

        // Stage 4: Cache Optimization
        if (combined.includes('[optimize:clear]') || combined.includes('[optimize]') || combined.includes('[view:cache]')) {
            setStageState(4, 'running', 'Optimizing…');
            document.getElementById('pipelineProgressLabel').textContent = 'Compiling application caches…';
        }
        if (combined.includes('[SUCCESS]') || combined.includes('Deployment completed')) {
            setStageState(4, 'completed', '✓ Done');
            setStageState(5, 'completed', '✓ Finalized');
            document.getElementById('pipelineProgressLabel').textContent = 'Complete';
        }

        // Exception / failure
        if (combined.includes('[EXCEPTION]') || combined.includes('ERROR') || combined.includes('Failed')) {
            document.getElementById('pipelineProgressLabel').textContent = 'Failed';
        }
    }

    function setStageState(num, state, badgeText) {
        const stage = document.getElementById(`stage${num}`);
        const dot   = document.getElementById(`stageDot${num}`);
        const badge = document.getElementById(`stageBadge${num}`);
        if (!stage) return;

        stage.className = `pipeline-stage-item ${state}`;
        badge.textContent = badgeText;

        if (state === 'completed') {
            dot.textContent = '✓';
        } else if (state === 'failed') {
            dot.textContent = '✕';
        }
    }

    function markPipelineComplete() {
        for (let i = 1; i <= 5; i++) {
            setStageState(i, 'completed', '✓ Done');
        }
        document.getElementById('pipelineProgressLabel').textContent = '100% Finalized';
    }

    function markPipelineFailed(reason) {
        for (let i = 1; i <= 5; i++) {
            const stage = document.getElementById(`stage${i}`);
            if (stage.classList.contains('running')) {
                setStageState(i, 'failed', '✕ Failed');
            }
        }
        document.getElementById('pipelineProgressLabel').textContent = 'Halted on Error';
    }

    // ── Stopwatch Timer ──────────────────────────────────────────
    function startStopwatch() {
        stopwatchStart = Date.now();
        const display = document.getElementById('activeStopwatch');
        display.textContent = '00:00.0s';

        if (stopwatchTimer) clearInterval(stopwatchTimer);
        stopwatchTimer = setInterval(() => {
            const elapsed = (Date.now() - stopwatchStart) / 1000;
            const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const secs = (elapsed % 60).toFixed(1).padStart(4, '0');
            display.textContent = `${mins}:${secs}s`;
        }, 100);
    }

    function stopStopwatch() {
        if (stopwatchTimer) {
            clearInterval(stopwatchTimer);
            stopwatchTimer = null;
        }
    }

    // ── Terminal Streaming & Interaction ─────────────────────────
    function renderNewLogs(logs) {
        if (!logs || logs.length === 0) return;

        const slice = logs.slice(renderedLogs);
        slice.forEach(log => appendTerminalLine(log));
        renderedLogs = logs.length;
    }

    function appendTerminalLine(msg, manualClass) {
        document.getElementById('terminalEmptyState').style.display = 'none';

        const container = document.getElementById('terminalLogRows');
        logLineCounter++;

        const row = document.createElement('div');
        row.className = 'terminal-log-row';

        const lineNum = document.createElement('span');
        lineNum.className = 'log-line-num';
        lineNum.textContent = String(logLineCounter);

        const lineText = document.createElement('span');
        lineText.className = 'log-line-text';

        // Log level styling
        const isSuccess = msg.includes('[SUCCESS]') || msg.includes('✅') || msg.includes('DONE');
        const isError   = msg.includes('[EXCEPTION]') || msg.includes('[AUTH_ERROR]') || msg.includes('❌') || msg.includes('ERROR');
        const isWarn    = msg.includes('⚠️') || msg.includes('WARN');
        const isInfo    = msg.includes('🚀') || msg.includes('⚡') || msg.includes('📂');

        if (manualClass) {
            lineText.classList.add(manualClass);
        } else if (isSuccess) {
            lineText.classList.add('highlight-success');
        } else if (isError) {
            lineText.classList.add('highlight-error');
        } else if (isWarn) {
            lineText.classList.add('highlight-warn');
        } else if (isInfo) {
            lineText.classList.add('highlight-info');
        }

        // Safe DOM insertion — zero XSS vulnerability
        lineText.textContent = msg;

        row.appendChild(lineNum);
        row.appendChild(lineText);
        container.appendChild(row);

        document.getElementById('logLineCount').textContent = `${logLineCounter} lines`;

        if (autoScroll) {
            const vp = document.getElementById('terminalViewport');
            vp.scrollTop = vp.scrollHeight;
        }
    }

    function clearTerminalView() {
        document.getElementById('terminalLogRows').innerHTML = '';
        document.getElementById('terminalEmptyState').style.display = 'flex';
        logLineCounter = 0;
        renderedLogs = 0;
        document.getElementById('logLineCount').textContent = '0 lines';
    }

    function handleTerminalScroll() {
        const vp = document.getElementById('terminalViewport');
        const atBottom = (vp.scrollHeight - vp.scrollTop - vp.clientHeight) < 30;
        const badge = document.getElementById('resumeAutoScrollBadge');

        if (!atBottom && autoScroll) {
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }

    function resumeAutoScroll() {
        const vp = document.getElementById('terminalViewport');
        vp.scrollTop = vp.scrollHeight;
        document.getElementById('resumeAutoScrollBadge').style.display = 'none';
        autoScroll = true;
        document.getElementById('autoScrollLabel').textContent = 'Auto-scroll: ON';
    }

    function toggleAutoScroll() {
        autoScroll = !autoScroll;
        const label = document.getElementById('autoScrollLabel');
        label.textContent = autoScroll ? 'Auto-scroll: ON' : 'Auto-scroll: OFF';
        if (autoScroll) resumeAutoScroll();
    }

    function copyTerminalLogs() {
        const textLines = Array.from(document.querySelectorAll('.log-line-text')).map(el => el.textContent).join('\n');
        navigator.clipboard.writeText(textLines).then(() => {
            const btn = document.getElementById('copyLogsBtn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span>✓ Copied</span>';
            setTimeout(() => btn.innerHTML = originalHTML, 2000);
        });
    }

    function toggleTerminalFullscreen() {
        const container = document.getElementById('terminalTabContent');
        const btn = document.getElementById('expandTerminalBtn');
        const isExpanded = container.classList.toggle('expanded');
        btn.innerHTML = isExpanded
            ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg><span>Collapse</span>'
            : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg><span>Expand</span>';
    }

    function switchDeckTab(tabKey) {
        const isTerminal = (tabKey === 'terminal');
        document.getElementById('tabTerminalBtn').classList.toggle('active', isTerminal);
        document.getElementById('tabHistoryBtn').classList.toggle('active', !isTerminal);
        document.getElementById('terminalTabContent').classList.toggle('hidden', !isTerminal);
        document.getElementById('historyTabContent').classList.toggle('hidden', isTerminal);
    }

    // ── Workflow & Status Visual Sentinels ────────────────────────
    function setWorkflowStep(stepNum) {
        for (let i = 1; i <= 3; i++) {
            const stepEl  = document.getElementById(`stepIndicator${i}`);
            const indexEl = document.getElementById(`stepIndex${i}`);
            stepEl.classList.remove('active', 'complete');

            if (i < stepNum) {
                stepEl.classList.add('complete');
                indexEl.textContent = '✓';
            } else if (i === stepNum) {
                stepEl.classList.add('active');
                indexEl.textContent = `${i}`;
            } else {
                indexEl.textContent = `${i}`;
            }
        }
    }

    function setSentinelStatus(statusClass, labelText) {
        const sentinel = document.getElementById('globalStatusSentinel');
        sentinel.className = `status-sentinel ${statusClass}`;
        document.getElementById('globalStatusText').textContent = labelText;
    }

    function setStreamStatus(isStreaming, label) {
        const badge = document.getElementById('streamStatusBadge');
        badge.className = isStreaming ? 'stream-indicator' : 'stream-indicator offline';
        badge.textContent = label;
    }

    // ── Utility Helpers ──────────────────────────────────────────
    function showCallout(el, type, message) {
        el.className = `callout-box ${type}`;
        el.textContent = message;
    }

    function hideCallout(el) {
        el.className = 'callout-box';
        el.textContent = '';
    }

    function setButtonState(btn, isDisabled, htmlContent) {
        btn.disabled = isDisabled;
        btn.innerHTML = htmlContent;
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    }

    // Enter key submits auth password
    document.getElementById('authSecret').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') handleAuth();
    });
</script>
</body>
</html>
