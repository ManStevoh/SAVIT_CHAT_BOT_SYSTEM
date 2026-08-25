<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access — Order {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 2rem 1.25rem;
            color: #111;
            background: #f3f4f6;
            line-height: 1.5;
        }
        .sheet {
            max-width: 42rem;
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .brand { font-size: 1.125rem; font-weight: 700; margin: 0 0 0.25rem; }
        .muted { color: #6b7280; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .item {
            margin-top: 1rem;
            padding: 1rem 1.1rem;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            border-radius: 1rem;
        }
        .item h2 { margin: 0 0 0.5rem; font-size: 1rem; }
        .item p { margin: 0.35rem 0; }
        .keys {
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: #fff;
            border: 1px dashed #93c5fd;
            border-radius: 0.75rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }
        a.btn {
            display: inline-block;
            margin-top: 0.5rem;
            margin-right: 0.5rem;
            padding: 0.55rem 1rem;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        a.btn.secondary { background: #111827; }
        .foot { margin-top: 1.5rem; font-size: 0.85rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="sheet">
        <h1 class="brand">{{ $order->company?->name ?? 'RelayIQ' }}</h1>
        <p class="muted">Your purchase access · Order {{ $order->order_number }}</p>

        @if(count($items) === 0)
            <p>No digital access items were found for this order.</p>
        @else
            @foreach($items as $item)
                <div class="item">
                    <h2>{{ $item['name'] }}</h2>
                    @if(!empty($item['expired']))
                        <p><em>Access for this item has expired.</em></p>
                    @elseif(!empty($item['instructions']))
                        <p>{{ $item['instructions'] }}</p>
                    @endif
                    @if(!empty($item['licenseKeys']))
                        <p><strong>License key(s):</strong></p>
                        <div class="keys">
                            @foreach($item['licenseKeys'] as $key)
                                <div>{{ $key }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if(!empty($item['accessUrl']))
                        <a class="btn" href="{{ $item['accessUrl'] }}" target="_blank" rel="noopener noreferrer">Open access link</a>
                    @endif
                    @if(!empty($item['bookingUrl']))
                        <a class="btn secondary" href="{{ $item['bookingUrl'] }}" target="_blank" rel="noopener noreferrer">Schedule meeting</a>
                    @endif
                    @if($item['downloadsExhausted'] ?? false)
                        <p><em>Download limit reached. Purchase again for more downloads.</em></p>
                    @elseif(($item['maxDownloads'] ?? null) !== null)
                        <p>Downloads remaining: {{ $item['downloadsRemaining'] }}</p>
                    @endif
                    @if(!empty($item['fileUrl']))
                        <a class="btn" href="{{ $item['fileUrl'] }}">Download {{ $item['fileName'] ?: 'resource' }}</a>
                    @endif
                    @if(!empty($item['accessExpiresAt']))
                        <p class="muted" style="margin-top:0.75rem;">Access expires: {{ \Carbon\Carbon::parse($item['accessExpiresAt'])->toDayDateTimeString() }}</p>
                    @endif
                </div>
            @endforeach
        @endif

        <p class="foot">
            Keep this page private. You can also open your
            <a href="{{ $order->publicReceiptUrl() }}">order receipt</a>.
        </p>
    </div>
</body>
</html>
