<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Deploy Console — Configuration Error</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 40px;
            max-width: 520px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        }
        .icon {
            width: 56px; height: 56px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }
        h1 { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 10px; }
        p  { font-size: 14px; color: #475569; line-height: 1.7; }
        code {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: ui-monospace, monospace;
            font-size: 13px;
            color: #2563eb;
            font-weight: 600;
        }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
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

