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
