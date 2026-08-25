<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deploy Console — Configuration Error</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #080c14;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #0f172a;
            border: 1px solid #ef4444;
            border-radius: 14px;
            padding: 40px;
            max-width: 520px;
            text-align: center;
        }
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        p  { font-size: 14px; color: #94a3b8; line-height: 1.7; }
        code {
            background: #1e293b;
            border: 1px solid #334155;
            padding: 2px 7px;
            border-radius: 5px;
            font-family: ui-monospace, monospace;
            font-size: 13px;
            color: #f59e0b;
        }
        .divider { border: none; border-top: 1px solid #1e293b; margin: 24px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚙️</div>
        <h1>Deploy Console Not Configured</h1>
        <hr class="divider">
        <p>
            The <code>DEPLOY_SECRET</code> environment variable is not set on this server.
            Add it to your <code>.env</code> file to enable the deploy console.
        </p>
        <br>
        <p style="font-size:13px;">
            <code>DEPLOY_SECRET=your-strong-secret-here</code>
        </p>
    </div>
</body>
</html>
