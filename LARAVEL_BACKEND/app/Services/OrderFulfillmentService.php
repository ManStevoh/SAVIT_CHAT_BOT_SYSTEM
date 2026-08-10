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
        $primaryDownloadUrl = null;

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
            if ($documentUrl !== '') {
                $lines[] = "  Download: {$documentUrl}";
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
}
