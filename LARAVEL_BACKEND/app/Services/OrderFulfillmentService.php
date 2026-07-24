<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\Log;

class OrderFulfillmentService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender,
        protected DigitalAccessService $digitalAccess,
    ) {}

    /**
     * Send access details/documents for paid digital goods or services.
     */
    public function sendPaidFulfillment(Order $order): void
    {
        $order->loadMissing('chat', 'company.whatsappAccount', 'orderProducts');

        $chat = $order->chat;
        $account = $order->company?->whatsappAccount;
        $to = $order->customer_phone ?: $chat?->customer_phone;
        if (! $chat || ! $account || ! $account->isActive() || ! $to) {
            return;
        }

        $lines = [];
        $hasDigital = false;

        /** @var OrderProduct $line */
        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            $type = (string) ($data['productType'] ?? 'physical');
            $fulfillmentType = (string) ($data['fulfillmentType'] ?? 'shipping');

            if ($type === 'physical') {
                continue;
            }

            $hasDigital = true;
            $lines[] = "• {$line->name}";

            $instructions = trim((string) ($data['fulfillmentInstructions'] ?? ''));
            if ($instructions !== '') {
                $lines[] = "  {$instructions}";
            }

            $accessUrl = trim((string) ($data['accessUrl'] ?? ''));
            if ($accessUrl !== '') {
                $lines[] = "  Access link: {$accessUrl}";
            }

            $bookingUrl = trim((string) ($data['bookingUrl'] ?? $data['serviceBookingUrl'] ?? ''));
            if ($bookingUrl !== '') {
                $lines[] = "  Booking: {$bookingUrl}";
            }

            $licenseKeys = [];
            if (! empty($data['licenseKeys']) && is_array($data['licenseKeys'])) {
                $licenseKeys = array_values(array_filter(array_map('strval', $data['licenseKeys'])));
            }
            if ($licenseKeys !== []) {
                $lines[] = '  License key(s): '.implode(', ', $licenseKeys);
            }

            $documentUrl = trim((string) ($data['digitalFileUrl'] ?? ''));
            $documentPath = trim((string) ($data['digitalFilePath'] ?? ''));
            $documentName = trim((string) ($data['digitalFileName'] ?? ''));
            $documentMime = trim((string) ($data['digitalFileMime'] ?? ''));
            $absolute = $documentPath !== ''
                ? (string) ($this->digitalAccess->resolveAbsolutePath($documentPath) ?? '')
                : '';

            if ($documentUrl !== '' || $absolute !== '') {
                if ($documentUrl !== '') {
                    $lines[] = "  Download: {$documentUrl}";
                }

                $result = $absolute !== ''
                    ? $this->waSender->sendDocumentFile(
                        $account,
                        $to,
                        $absolute,
                        $documentMime !== '' ? $documentMime : null,
                        $documentName !== '' ? $documentName : basename($documentPath !== '' ? $documentPath : $absolute),
                        $line->name
                    )
                    : $this->waSender->sendDocument(
                        $account,
                        $to,
                        $documentUrl,
                        $documentName !== '' ? $documentName : basename(parse_url($documentUrl, PHP_URL_PATH) ?: 'download'),
                        $line->name
                    );

                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $line->name,
                    'message_type' => 'file',
                    'attachment_url' => $documentUrl !== '' ? $documentUrl : null,
                    'attachment_name' => $documentName !== '' ? $documentName : null,
                    'attachment_mime' => $documentMime !== '' ? $documentMime : null,
                    'attachment_size' => $data['digitalFileSize'] ?? null,
                    'sender' => 'bot',
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'whatsapp_message_id' => $result['message_id'] ?? null,
                ]);

                if (! $result['success']) {
                    Log::warning('Order fulfillment document send failed', [
                        'order_id' => $order->id,
                        'order_product_id' => $line->id,
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                }
            } elseif ($fulfillmentType === 'download' || $fulfillmentType === 'link' || $type === 'digital' || $type === 'service') {
                $lines[] = '  Delivery is available in your access portal and receipt.';
            }
        }

        if ($lines === []) {
            return;
        }

        $portalUrl = $hasDigital ? $this->digitalAccess->signedAccessPortalUrl($order) : null;
        $message = "Your purchase is ready for access:\n\n".implode("\n", $lines);
        if ($portalUrl) {
            $message .= "\n\nAccess portal:\n{$portalUrl}";
        }
        $message .= "\n\nReceipt:\n".$order->publicReceiptUrl();

        $result = $this->waSender->sendText($account, $to, $message);

        Message::create([
            'chat_id' => $chat->id,
            'content' => $message,
            'sender' => 'bot',
            'status' => $result['success'] ? 'sent' : 'failed',
            'whatsapp_message_id' => $result['message_id'] ?? null,
        ]);
    }
}
