<?php

namespace App\Models;

use App\Services\CompanyInAppNotificationService;
use App\Services\Growth\AttributionService;
use App\Services\MailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    protected $fillable = [
        'company_id',
        'chat_id',
        'social_post_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'total',
        'status',
        'payment_status',
        'agent_proactive_follow_up_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'agent_proactive_follow_up_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    /**
     * Signed URL to a print-friendly receipt page (shared in WhatsApp).
     */
    public function publicReceiptUrl(): string
    {
        return URL::signedRoute('orders.receipt', ['order' => $this->id], now()->addYears(10));
    }

    /**
     * @return list<array{name: string, productType: string, fulfillmentType: string, instructions: ?string, accessUrl: ?string, bookingUrl: ?string, fileUrl: ?string, fileName: ?string, licenseKeys: list<string>, accessExpiresAt: ?string, expired: bool}>
     */
    public function receiptFulfillmentItems(): array
    {
        $this->loadMissing('orderProducts');
        $access = app(\App\Services\DigitalAccessService::class);

        return $this->orderProducts
            ->map(function (OrderProduct $line) use ($access): ?array {
                $data = is_array($line->fulfillment_data) ? $line->fulfillment_data : [];
                $type = (string) ($data['productType'] ?? 'physical');
                if ($type === 'physical') {
                    return null;
                }

                $expired = $access->lineAccessIsExpired($data);
                $keys = [];
                if (! $expired && ! empty($data['licenseKeys']) && is_array($data['licenseKeys'])) {
                    $keys = array_values(array_filter(array_map('strval', $data['licenseKeys'])));
                }

                $maxDownloads = isset($data['maxDownloads']) ? (int) $data['maxDownloads'] : null;
                $downloadCount = (int) ($line->download_count ?? ($data['downloadCount'] ?? 0));
                $downloadsExhausted = $maxDownloads !== null && $maxDownloads > 0 && $downloadCount >= $maxDownloads;

                return [
                    'name' => $line->name,
                    'productType' => $type,
                    'fulfillmentType' => (string) ($data['fulfillmentType'] ?? 'shipping'),
                    'instructions' => $expired
                        ? 'Access for this item has expired.'
                        : ($downloadsExhausted
                            ? 'Download limit reached. Purchase again for more downloads.'
                            : ($data['fulfillmentInstructions'] ?? null)),
                    'accessUrl' => $expired ? null : ($data['accessUrl'] ?? null),
                    'bookingUrl' => $expired ? null : ($data['bookingUrl'] ?? $data['serviceBookingUrl'] ?? null),
                    'fileUrl' => ($expired || $downloadsExhausted) ? null : ($data['digitalFileUrl'] ?? null),
                    'fileName' => $data['digitalFileName'] ?? null,
                    'licenseKeys' => $keys,
                    'accessExpiresAt' => $data['accessExpiresAt'] ?? null,
                    'maxDownloads' => $maxDownloads,
                    'downloadCount' => $downloadCount,
                    'downloadsRemaining' => $maxDownloads !== null && $maxDownloads > 0
                        ? max(0, $maxDownloads - $downloadCount)
                        : null,
                    'expired' => $expired,
                    'downloadsExhausted' => $downloadsExhausted,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $company = $order->company;
            if (! $company) {
                return;
            }
            $settings = $company->settings;
            $notificationsOn = $settings && $settings->notifications_enabled;

            if ($notificationsOn && $company->email) {
                try {
                    $ordersUrl = rtrim(config('app.frontend_url', config('app.url')), '/').'/dashboard/orders';
                    app(MailService::class)->sendNewOrderNotification(
                        $company->email,
                        $order->order_number,
                        $order->customer_name ?? 'Customer',
                        (float) $order->total,
                        $ordersUrl
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to send new order notification', ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }

            if ($notificationsOn) {
                app(CompanyInAppNotificationService::class)->recordNewOrder($order);
            }

            try {
                app(AttributionService::class)->recordOrder($order);
            } catch (\Throwable $e) {
                Log::warning('Failed to record growth attribution for order', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        });
    }
}
