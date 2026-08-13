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
        'invoice_token',
        'pay_token',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'fulfillment_type',
        'dine_in_table_id',
        'dine_in_table_name',
        'subtotal',
        'tax_total',
        'delivery_fee',
        'discount_total',
        'tip_amount',
        'tax_breakdown',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'order_notes',
        'gift_message',
        'coupon_code',
        'coupon_id',
        'scheduled_for',
        'spam_flagged',
        'source',
        'payment_recovered_at',
        'agent_proactive_follow_up_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'tax_breakdown' => 'array',
        'total' => 'decimal:2',
        'scheduled_for' => 'datetime',
        'spam_flagged' => 'boolean',
        'payment_recovered_at' => 'datetime',
        'agent_proactive_follow_up_at' => 'datetime',
    ];

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->attributes['total'] ?? 0);
    }

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

    public function dineInTable(): BelongsTo
    {
        return $this->belongsTo(DineInTable::class, 'dine_in_table_id');
    }

    public function paymentRecoveryAttempts(): HasMany
    {
        return $this->hasMany(PaymentRecoveryAttempt::class);
    }

    /**
     * Ensure public pay + invoice tokens exist.
     */
    public function ensurePublicTokens(): void
    {
        $dirty = false;
        if (empty($this->invoice_token)) {
            $this->invoice_token = bin2hex(random_bytes(24));
            $dirty = true;
        }
        if (empty($this->pay_token)) {
            $this->pay_token = bin2hex(random_bytes(24));
            $dirty = true;
        }
        if ($dirty) {
            $this->save();
        }
    }

    public function publicPayUrl(?string $method = null): string
    {
        $this->ensurePublicTokens();
        $base = url('/pay/'.$this->pay_token);
        $m = $method ?? $this->payment_method;
        if ($m) {
            return $base.'?method='.urlencode((string) $m);
        }

        return $base;
    }

    public function publicInvoiceUrl(): string
    {
        $this->ensurePublicTokens();

        return url('/invoice/'.$this->invoice_token);
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
        static::creating(function (Order $order) {
            if (empty($order->invoice_token)) {
                $order->invoice_token = bin2hex(random_bytes(24));
            }
            if (empty($order->pay_token)) {
                $order->pay_token = bin2hex(random_bytes(24));
            }
        });

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
