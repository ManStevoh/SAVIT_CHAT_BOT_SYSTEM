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
            background: #0c1017;
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            -webkit-font-smoothing: antialiased;
        }

        .config-card {
            background: #111827;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
            max-width: 540px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px -10px rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .config-icon-box {
            width: 60px;
            height: 60px;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fbbf24;
        }

        .config-title {
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .config-subtitle {
            font-size: 13.5px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .code-snippet-box {
            width: 100%;
            background: #090d16;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'Geist Mono', monospace;
            font-size: 13px;
            color: #60a5fa;
            user-select: all;
        }

        .env-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-size: 11px;
            font-weight: 700;
            color: #93c5fd;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="config-card">
        <div class="config-icon-box">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </div>
        <span class="env-badge">503 · Setup Required</span>
        <h1 class="config-title">Deploy Console Not Configured</h1>
        <p class="config-subtitle">
            The <code style="color:#60a5fa; font-family:'Geist Mono',monospace;">DEPLOY_SECRET</code> environment variable is not configured on this host. Add it to your server’s <code style="color:#f1f5f9; font-family:'Geist Mono',monospace;">.env</code> file to activate the operations console.
        </p>

        <div class="code-snippet-box">
            <span>DEPLOY_SECRET=your-secret-password-here</span>
        </div>
    </div>
</body>
</html>
