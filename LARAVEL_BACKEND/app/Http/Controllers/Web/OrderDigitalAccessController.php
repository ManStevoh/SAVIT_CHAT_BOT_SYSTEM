<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Services\DigitalAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderDigitalAccessController extends Controller
{
    public function portal(Order $order, DigitalAccessService $access)
    {
        if ($order->payment_status !== 'paid') {
            abort(403, 'This order is not paid yet.');
        }

        if ($access->orderAccessIsExpired($order)) {
            abort(410, 'This access portal has expired.');
        }

        $order->load(['company', 'orderProducts']);

        return response()->view('order-access', [
            'order' => $order,
            'items' => $order->receiptFulfillmentItems(),
        ]);
    }

    public function download(
        Request $request,
        Order $order,
        OrderProduct $orderProduct,
        DigitalAccessService $access
    ): BinaryFileResponse|Response {
        if ((int) $orderProduct->order_id !== (int) $order->id) {
            abort(404);
        }
        if ($order->payment_status !== 'paid') {
            abort(403, 'This order is not paid yet.');
        }

        $data = is_array($orderProduct->fulfillment_data) ? $orderProduct->fulfillment_data : [];
        $path = (string) ($data['digitalFilePath'] ?? '');
        if ($path === '') {
            abort(404, 'No digital file on this order line.');
        }

        try {
            $access->consumeDownload($orderProduct);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            if (str_contains(strtolower($message), 'expired')) {
                abort(410, $message);
            }
            abort(403, $message);
        }

        $orderProduct->refresh();
        $data = is_array($orderProduct->fulfillment_data) ? $orderProduct->fulfillment_data : $data;

        $resolved = $access->resolveReadableStream($path);
        if (! $resolved) {
            abort(404, 'File not found.');
        }

        $name = (string) ($data['digitalFileName'] ?? basename($path));
        $mime = (string) ($data['digitalFileMime'] ?? 'application/octet-stream');

        return response()->download($resolved['absolute'], $name, [
            'Content-Type' => $mime,
        ]);
    }
}
