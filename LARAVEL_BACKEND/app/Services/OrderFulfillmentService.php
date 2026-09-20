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
        $order->loadMissing('chat', 'company.whatsappAccount', 'orderProducts.product');

        $chat = $order->chat;
        if (! $chat && $order->customer_phone) {
            $chat = app(\App\Services\Storefront\StorefrontWhatsAppBridgeService::class)->attachOrderToChat($order);
            $order->setRelation('chat', $chat);
        }
        $account = $order->company?->whatsappAccount;
        $to = $order->customer_phone ?: $chat?->customer_phone;
        if (! $chat || ! $account || ! $account->isActive() || ! $to) {
            return;
        }

        $lines = [];
        $hasDigital = false;
        $primaryDownloadUrl = null;

        /** @var OrderProduct $line */
        foreach ($order->orderProducts as $line) {
            $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
            $type = (string) ($data['productType'] ?? 'physical');
            $fulfillmentType = (string) ($data['fulfillmentType'] ?? 'shipping');

            if (! $this->digitalAccess->isDigitalFulfillment($data, $line->product)) {
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
            if ($documentUrl !== '') {
                $fileName = trim((string) ($data['digitalFileName'] ?? 'your file'));
                $lines[] = "  Download: {$fileName}";
                if ($primaryDownloadUrl === null) {
                    $primaryDownloadUrl = $documentUrl;
                }
            } elseif ($fulfillmentType === 'download' || $fulfillmentType === 'link' || $type === 'digital' || $type === 'service') {
                $lines[] = '  Delivery is available in your access portal.';
            }
        }

        if ($lines === []) {
            return;
        }

        $portalUrl = $hasDigital ? $this->digitalAccess->signedAccessPortalUrl($order) : null;
        $deliveryMessage = "Your purchase is ready for access:\n\n".implode("\n", $lines);

        if ($primaryDownloadUrl) {
            $result = $this->waSender->sendInteractiveCtaUrl(
                $account,
                $to,
                $deliveryMessage,
                'Download File',
                $primaryDownloadUrl
            );

            Message::create([
                'chat_id' => $chat->id,
                'content' => $deliveryMessage,
                'sender' => 'bot',
                'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                'whatsapp_message_id' => $result['message_id'] ?? null,
            ]);

            if ($portalUrl) {
                $portalMsg = '🔑 Access your customer portal anytime to manage your purchases:';
                $portalRes = $this->waSender->sendInteractiveCtaUrl(
                    $account,
                    $to,
                    $portalMsg,
                    'Access Portal',
                    $portalUrl
                );

                Message::create([
                    'chat_id' => $chat->id,
                    'content' => $portalMsg,
                    'sender' => 'bot',
                    'status' => ($portalRes['success'] ?? false) ? 'sent' : 'failed',
                    'whatsapp_message_id' => $portalRes['message_id'] ?? null,
                ]);
            }
        } elseif ($portalUrl) {
            $portalRes = $this->waSender->sendInteractiveCtaUrl(
                $account,
                $to,
                $deliveryMessage,
                'Access Portal',
                $portalUrl
            );

            Message::create([
                'chat_id' => $chat->id,
                'content' => $deliveryMessage,
                'sender' => 'bot',
                'status' => ($portalRes['success'] ?? false) ? 'sent' : 'failed',
                'whatsapp_message_id' => $portalRes['message_id'] ?? null,
            ]);
        } else {
            $result = $this->waSender->sendText($account, $to, $deliveryMessage);

            Message::create([
                'chat_id' => $chat->id,
                'content' => $deliveryMessage,
                'sender' => 'bot',
                'status' => ($result['success'] ?? false) ? 'sent' : 'failed',
                'whatsapp_message_id' => $result['message_id'] ?? null,
            ]);
        }
    }

    /**
     * Merchant-triggered resend: email the customer again (PDF attached when present)
     * and retry WhatsApp, including the document itself when connected.
     *
     * @return array{
     *   success: bool,
     *   emailSent: bool,
     *   whatsappSent: bool,
     *   pdfAttached: bool,
     *   needsEmail: bool,
     *   message: string,
     *   orderNumber?: string
     * }
     */
    public function resendDigitalToCustomer(Order $order, ?string $email = null): array
    {
        $order->loadMissing(['company.whatsappAccount', 'company.settings', 'orderProducts.product', 'chat']);
        $mail = app(MailService::class);

        $submitted = strtolower(trim((string) $email));
        if ($submitted !== '' && filter_var($submitted, FILTER_VALIDATE_EMAIL)) {
            $order->update(['customer_email' => $submitted]);
            $order->refresh();
            $order->loadMissing(['company.whatsappAccount', 'company.settings', 'orderProducts.product', 'chat']);
        } elseif ($this->customerEmailMissing($order)) {
            $resolved = $mail->resolveNotifyEmail($order->company, $order->customer_phone);
            if ($resolved) {
                $order->update(['customer_email' => $resolved]);
                $order->refresh();
                $order->loadMissing(['company.whatsappAccount', 'company.settings', 'orderProducts.product', 'chat']);
            }
        }

        if (! $mail->orderHasDigitalItems($order)) {
            return [
                'success' => false,
                'emailSent' => false,
                'whatsappSent' => false,
                'pdfAttached' => false,
                'needsEmail' => false,
                'message' => 'This order has no digital file or download to resend.',
                'orderNumber' => (string) $order->order_number,
            ];
        }

        $paid = strtolower((string) $order->payment_status) === 'paid';
        if ($paid) {
            $this->digitalAccess->preparePaidOrder($order);
            $order->refresh();
            $order->loadMissing(['company.whatsappAccount', 'orderProducts.product', 'chat']);
        }

        $pdfs = $mail->digitalPdfAttachmentsFor($order);
        $needsEmail = $this->customerEmailMissing($order);

        $emailSent = false;
        if (! $needsEmail) {
            $emailSent = $paid
                ? $mail->sendCustomerPaymentFulfillmentSafely($order)
                : $mail->sendCustomerOrderConfirmationSafely($order, true);
        }

        $whatsappSent = false;
        $account = $order->company?->whatsappAccount;
        $to = $order->customer_phone ?: $order->chat?->customer_phone;
        if ($account && $account->isActive() && $to) {
            $this->sendPaidFulfillment($order);
            foreach ($pdfs as $file) {
                $result = $this->waSender->sendDocumentFile(
                    $account,
                    $to,
                    $file['path'],
                    $file['mime'] ?? 'application/pdf',
                    $file['name'] ?? 'download.pdf',
                    'Your purchased file is attached — save it to your phone.'
                );
                if ($result['success'] ?? false) {
                    $whatsappSent = true;
                    if ($order->chat_id) {
                        Message::create([
                            'chat_id' => $order->chat_id,
                            'content' => 'Sent digital file: '.($file['name'] ?? 'download.pdf'),
                            'sender' => 'bot',
                            'status' => 'sent',
                            'whatsapp_message_id' => $result['message_id'] ?? null,
                        ]);
                    }
                }
            }
            if (! $whatsappSent && $order->chat_id) {
                $whatsappSent = Message::query()
                    ->where('chat_id', $order->chat_id)
                    ->where('sender', 'bot')
                    ->where('status', 'sent')
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->exists();
            }
        }

        $pdfAttached = $pdfs !== [];

        if ($needsEmail && ! $whatsappSent) {
            return [
                'success' => false,
                'emailSent' => false,
                'whatsappSent' => false,
                'pdfAttached' => $pdfAttached,
                'needsEmail' => true,
                'message' => 'Add the customer email to resend the book by email.',
                'orderNumber' => (string) $order->order_number,
            ];
        }

        if (! $emailSent && ! $whatsappSent) {
            return [
                'success' => false,
                'emailSent' => false,
                'whatsappSent' => false,
                'pdfAttached' => $pdfAttached,
                'needsEmail' => $needsEmail,
                'message' => 'Could not resend. Add a customer email, or connect WhatsApp.',
                'orderNumber' => (string) $order->order_number,
            ];
        }

        $parts = [];
        if ($emailSent) {
            $parts[] = $pdfAttached
                ? 'emailed with the PDF attached'
                : 'emailed with the download link';
        }
        if ($whatsappSent) {
            $parts[] = 'sent on WhatsApp';
        }

        return [
            'success' => true,
            'emailSent' => $emailSent,
            'whatsappSent' => $whatsappSent,
            'pdfAttached' => $pdfAttached,
            'needsEmail' => false,
            'message' => 'Order '.$order->order_number.' '.implode(' and ', $parts).'.',
            'orderNumber' => (string) $order->order_number,
        ];
    }

    private function customerEmailMissing(Order $order): bool
    {
        $to = strtolower(trim((string) ($order->customer_email ?? '')));

        return $to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL);
    }
}
