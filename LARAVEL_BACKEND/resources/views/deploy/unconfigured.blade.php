<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>RelayIQ — Deploy Console Configuration Required</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|geist-mono:400,500,600&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .config-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 36px 32px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .config-icon-box {
            width: 52px;
            height: 52px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d97706;
        }

        .config-title {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .config-subtitle {
            font-size: 13.5px;
            color: #475569;
            line-height: 1.6;
        }

        .code-snippet-box {
            width: 100%;
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'Geist Mono', monospace;
            font-size: 13px;
            color: #93c5fd;
            user-select: all;
        }

        .env-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            font-size: 11px;
            font-weight: 600;
            color: #1d4ed8;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="config-card">
        <div class="config-icon-box">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </div>
        <span class="env-badge">503 · Setup Required</span>
        <h1 class="config-title">Deploy Console Not Configured</h1>
        <p class="config-subtitle">
            The <code style="color:#2563eb; font-family:'Geist Mono',monospace; background:#eff6ff; padding:1px 4px; border-radius:3px;">DEPLOY_SECRET</code> environment variable is not configured on this host. Add it to your server’s <code style="color:#0f172a; font-family:'Geist Mono',monospace; background:#f1f5f9; padding:1px 4px; border-radius:3px;">.env</code> file to activate the operations console.
        </p>

        <div class="code-snippet-box">
            <span>DEPLOY_SECRET=your-secret-password-here</span>
        </div>
    </div>
</body>
</html>
