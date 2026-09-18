<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — {{ $appName }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 48px 16px; }
        .card { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 1px 3px rgb(15 23 42 / 8%); }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { line-height: 1.55; color: #334155; }
    </style>
</head>
<body>
    <div class="card">
        <h1>You’re unsubscribed</h1>
        <p>{{ $email }} will no longer receive product updates and marketing emails from {{ $appName }}.</p>
        <p>You’ll still get account, payment, and order emails.</p>
    </div>
</body>
</html>
