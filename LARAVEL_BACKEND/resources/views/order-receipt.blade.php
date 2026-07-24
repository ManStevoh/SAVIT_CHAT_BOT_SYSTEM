<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order {{ $order->order_number }}</title>
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
        .brand {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111;
            margin: 0 0 0.25rem;
        }
        .muted { color: #6b7280; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .meta { margin: 0.35rem 0; font-size: 0.95rem; }
        table { width: 100%; border-collapse: collapse; margin: 1.25rem 0; font-size: 0.95rem; }
        th, td { text-align: left; padding: 0.65rem 0; border-bottom: 1px solid #e5e7eb; }
        th { font-weight: 600; color: #374151; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; }
        .num { text-align: right; }
        .totals {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #e5e7eb;
            font-size: 1.15rem;
            font-weight: 700;
            color: #111;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        .paid { background: #dcfce7; color: #166534; }
        .pending { background: #fef3c7; color: #92400e; }
        .foot { margin-top: 2rem; font-size: 0.85rem; color: #6b7280; }
        .fulfillment {
            margin-top: 1.5rem;
            padding: 1rem 1.1rem;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            border-radius: 1rem;
        }
        .fulfillment h2 {
            margin: 0 0 0.75rem;
            font-size: 1rem;
        }
        .fulfillment-item { margin-top: 0.75rem; }
        .fulfillment-item p { margin: 0.25rem 0; }
        .print-btn {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.65rem 1.25rem;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; border-radius: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <h1 class="brand">{{ $order->company?->name ?? 'RelayIQ' }}</h1>
        <p class="muted">Invoice / receipt &middot; {{ $order->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>

        <p class="meta"><strong>Order number:</strong> {{ $order->order_number }}</p>
        <p class="meta"><strong>Customer:</strong> {{ $order->customer_name }}</p>
        @if($order->customer_phone)
            <p class="meta"><strong>Phone:</strong> {{ $order->customer_phone }}</p>
        @endif

        <p class="meta" style="margin-top:1rem;">
            <strong>Payment:</strong>
            <span class="badge {{ $order->payment_status === 'paid' ? 'paid' : 'pending' }}">{{ $order->payment_status }}</span>
            &nbsp;
            <strong>Order status:</strong>
            <span class="badge pending">{{ $order->status }}</span>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Price</th>
                    <th class="num">Line</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->orderProducts as $line)
                    <tr>
                        <td>{{ $line->name }}</td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ number_format((float) $line->price, 2) }}</td>
                        <td class="num">{{ number_format((float) $line->price * (int) $line->quantity, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="totals">Total: {{ number_format((float) $order->total, 2) }}</p>

        @php($fulfillmentItems = $order->receiptFulfillmentItems())
        @if($order->payment_status === 'paid' && count($fulfillmentItems) > 0)
            <div class="fulfillment">
                <h2>Access & fulfillment</h2>
                @foreach($fulfillmentItems as $item)
                    <div class="fulfillment-item">
                        <strong>{{ $item['name'] }}</strong>
                        @if(!empty($item['expired']))
                            <p><em>Access for this item has expired.</em></p>
                        @elseif(!empty($item['instructions']))
                            <p>{{ $item['instructions'] }}</p>
                        @endif
                        @if(!empty($item['licenseKeys']))
                            <p><strong>License key(s):</strong></p>
                            <ul>
                                @foreach($item['licenseKeys'] as $key)
                                    <li style="font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;">{{ $key }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if(!empty($item['accessUrl']))
                            <p><a href="{{ $item['accessUrl'] }}" target="_blank" rel="noopener noreferrer">Open access link</a></p>
                        @endif
                        @if(!empty($item['bookingUrl']))
                            <p><a class="print-btn" href="{{ $item['bookingUrl'] }}" target="_blank" rel="noopener noreferrer">Schedule meeting</a></p>
                        @endif
                        @if($item['downloadsExhausted'] ?? false)
                            <p><em>Download limit reached. Purchase again for more downloads.</em></p>
                        @elseif(($item['maxDownloads'] ?? null) !== null)
                            <p>Downloads remaining: {{ $item['downloadsRemaining'] }}</p>
                        @endif
                        @if(!empty($item['fileUrl']))
                            <p><a href="{{ $item['fileUrl'] }}" target="_blank" rel="noopener noreferrer">Download {{ $item['fileName'] ?: 'resource' }}</a></p>
                        @endif
                        @if(!empty($item['accessExpiresAt']))
                            <p>Access expires: {{ \Carbon\Carbon::parse($item['accessExpiresAt'])->toDayDateTimeString() }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <p class="foot">Thank you for your order. Use your browser&apos;s print dialog (Ctrl+P) to print or save as PDF.</p>
        <p class="no-print foot">
            <button type="button" class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        </p>
    </div>
</body>
</html>
