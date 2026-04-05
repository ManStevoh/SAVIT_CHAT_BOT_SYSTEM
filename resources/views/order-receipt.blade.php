<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order {{ $order->order_number }}</title>
    <style>
        body { font-family: system-ui, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 1.5rem; color: #111; background: #fafafa; }
        .sheet { max-width: 40rem; margin: 0 auto; background: #fff; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 0.25rem; }
        .muted { color: #555; font-size: 0.9rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.95rem; }
        th, td { text-align: left; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        th { font-weight: 600; color: #333; }
        .num { text-align: right; }
        .totals { margin-top: 1rem; font-size: 1.1rem; font-weight: 600; }
        .badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .paid { background: #e8f5e9; color: #1b5e20; }
        .pending { background: #fff3e0; color: #e65100; }
        .foot { margin-top: 2rem; font-size: 0.85rem; color: #666; }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { box-shadow: none; max-width: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <h1>{{ $order->company?->name ?? 'Order' }}</h1>
        <p class="muted">Invoice / receipt &middot; {{ $order->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>

        <p><strong>Order number:</strong> {{ $order->order_number }}</p>
        <p><strong>Customer:</strong> {{ $order->customer_name }}</p>
        @if($order->customer_phone)
            <p><strong>Phone:</strong> {{ $order->customer_phone }}</p>
        @endif

        <p style="margin-top:1rem;">
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

        <p class="foot">Thank you for your order. Use your browser&apos;s print dialog (Ctrl+P) to print or save as PDF.</p>
        <p class="no-print foot"><a href="javascript:window.print()">Print / Save as PDF</a></p>
    </div>
</body>
</html>
