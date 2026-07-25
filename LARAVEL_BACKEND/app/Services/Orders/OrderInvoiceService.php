<?php

namespace App\Services\Orders;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;
use App\Services\WhatsAppMessageSenderService;
use App\Support\MoneyFormatter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generate a customer invoice/receipt PDF and deliver it on WhatsApp.
 */
class OrderInvoiceService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender,
    ) {}

    /**
     * Resolve the best order for this customer (by number or latest).
     */
    public function resolveOrder(Company $company, string $customerPhone, ?string $orderNumber = null): ?Order
    {
        $phone = preg_replace('/\D+/', '', $customerPhone) ?? $customerPhone;
        $query = Order::query()
            ->where('company_id', $company->id)
            ->where(function ($q) use ($phone, $customerPhone) {
                $q->where('customer_phone', $customerPhone)
                    ->orWhere('customer_phone', $phone)
                    ->orWhere('customer_phone', 'like', '%'.substr($phone, -9).'%');
            })
            ->with(['orderProducts', 'company.settings']);

        if ($orderNumber && trim($orderNumber) !== '') {
            $found = (clone $query)->where('order_number', 'like', '%'.trim($orderNumber).'%')->orderByDesc('id')->first();
            if ($found) {
                return $found;
            }
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Build a PDF invoice for the order and return absolute path + public receipt URL.
     *
     * @return array{path: string, filename: string, receipt_url: string}
     */
    public function generatePdf(Order $order): array
    {
        $order->loadMissing(['orderProducts', 'company.settings']);
        $currency = $order->company?->settings?->displayCurrencyCode() ?? 'USD';
        $companyName = e($order->company?->name ?? 'Invoice');
        $orderNumber = e((string) ($order->order_number ?: $order->id));
        $customer = e((string) ($order->customer_name ?: 'Customer'));
        $phone = e((string) $order->customer_phone);
        $status = e((string) $order->status);
        $payment = e((string) ($order->payment_status ?? 'pending'));
        $total = e(MoneyFormatter::format((float) $order->total, $currency));
        $date = e(optional($order->created_at)->format('Y-m-d H:i') ?: '');

        $rows = '';
        foreach ($order->orderProducts as $item) {
            $line = MoneyFormatter::format((float) $item->price * (int) $item->quantity, $currency);
            $rows .= '<tr>'
                .'<td>'.e((string) $item->name).'</td>'
                .'<td style="text-align:right">'.(int) $item->quantity.'</td>'
                .'<td style="text-align:right">'.e(MoneyFormatter::format((float) $item->price, $currency)).'</td>'
                .'<td style="text-align:right">'.e($line).'</td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4">No line items recorded.</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#111}
table{width:100%;border-collapse:collapse;margin-top:16px}
th,td{text-align:left;padding:8px 4px;border-bottom:1px solid #ddd}
.num,.totals{text-align:right}
.totals{margin-top:16px;font-size:14px;font-weight:bold}
</style></head><body>
<h1>{$companyName}</h1>
<p>Invoice / receipt for order #{$orderNumber}</p>
<p><strong>Customer:</strong> {$customer}<br>
<strong>Phone:</strong> {$phone}<br>
<strong>Date:</strong> {$date}<br>
<strong>Status:</strong> {$status} | <strong>Payment:</strong> {$payment}</p>
<table>
<thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Line</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<div class="totals">Total due: {$total}</div>
</body></html>
HTML;

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice-'.Str::slug((string) ($order->order_number ?: $order->id)).'.pdf';
        $relative = 'invoices/'.$order->company_id.'/'.$filename;
        Storage::disk('local')->put($relative, $dompdf->output());

        return [
            'path' => Storage::disk('local')->path($relative),
            'filename' => $filename,
            'receipt_url' => $order->publicReceiptUrl(),
        ];
    }

    /**
     * Generate invoice PDF and send to the customer on WhatsApp.
     *
     * @return array{
     *   success: bool,
     *   order_number?: string,
     *   status?: string,
     *   payment_status?: string,
     *   total?: string,
     *   receipt_url?: string,
     *   whatsapp_sent?: bool,
     *   message?: string,
     *   error?: string
     * }
     */
    public function sendInvoiceToCustomer(
        Company $company,
        Chat $chat,
        string $customerPhone,
        ?string $orderNumber = null,
        ?string $caption = null,
    ): array {
        $order = $this->resolveOrder($company, $customerPhone, $orderNumber);
        if (! $order) {
            return [
                'success' => false,
                'error' => 'no_order',
                'message' => 'No order found for this customer yet. Create/confirm an order first (use process_order_message), then send the invoice.',
            ];
        }

        $account = $company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            return [
                'success' => false,
                'error' => 'whatsapp_inactive',
                'message' => 'WhatsApp is not connected for this business.',
                'order_number' => $order->order_number,
            ];
        }

        try {
            $pdf = $this->generatePdf($order);
        } catch (\Throwable $e) {
            Log::error('OrderInvoiceService: PDF generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback: still send the signed receipt link.
            $url = $order->publicReceiptUrl();
            $text = $this->invoiceTextMessage($order, $url, includePdfNote: false);
            $send = $this->waSender->sendText($account, $customerPhone, $text);

            return [
                'success' => (bool) ($send['success'] ?? false),
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => $this->formatTotal($order),
                'receipt_url' => $url,
                'whatsapp_sent' => (bool) ($send['success'] ?? false),
                'message' => 'Invoice link shared (PDF generation failed).',
                'error' => $send['success'] ?? false ? null : ($send['error'] ?? 'send_failed'),
                'pdf_error' => $e->getMessage(),
            ];
        }

        $currency = $company->settings?->displayCurrencyCode() ?? 'USD';
        $defaultCaption = sprintf(
            "Invoice for order #%s — %s (%s). Payment status: %s.\n\nView online:\n%s",
            $order->order_number,
            MoneyFormatter::format((float) $order->total, $currency),
            $order->status,
            $order->payment_status ?? 'unknown',
            $pdf['receipt_url'],
        );

        $send = $this->waSender->sendDocumentFile(
            $account,
            $customerPhone,
            $pdf['path'],
            'application/pdf',
            $pdf['filename'],
            $caption && trim($caption) !== '' ? trim($caption) : $defaultCaption,
        );

        return [
            'success' => (bool) ($send['success'] ?? false),
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => $this->formatTotal($order),
            'receipt_url' => $pdf['receipt_url'],
            'whatsapp_sent' => (bool) ($send['success'] ?? false),
            'pdf_filename' => $pdf['filename'],
            'message' => ($send['success'] ?? false)
                ? 'Invoice PDF sent to the customer on WhatsApp.'
                : ('Failed to send invoice PDF: '.($send['error'] ?? 'unknown')),
            'error' => ($send['success'] ?? false) ? null : ($send['error'] ?? 'send_failed'),
        ];
    }

    protected function formatTotal(Order $order): string
    {
        $currency = $order->company?->settings?->displayCurrencyCode() ?? 'USD';

        return MoneyFormatter::format((float) $order->total, $currency);
    }

    protected function invoiceTextMessage(Order $order, string $receiptUrl, bool $includePdfNote = true): string
    {
        $lines = [
            'Invoice for order #'.$order->order_number,
            'Status: '.$order->status.' | Payment: '.($order->payment_status ?? 'unknown'),
            'Total: '.$this->formatTotal($order),
            '',
            'View / pay invoice:',
            $receiptUrl,
        ];
        if ($includePdfNote) {
            $lines[] = '';
            $lines[] = '(PDF attached when available)';
        }

        return implode("\n", $lines);
    }
}
