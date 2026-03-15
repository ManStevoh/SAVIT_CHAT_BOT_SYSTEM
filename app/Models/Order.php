<?php

namespace App\Models;

use App\Services\MailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    protected $fillable = [
        'company_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'total',
        'status',
        'payment_status',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orderProducts(): HasMany
    {
        return $this->hasMany(OrderProduct::class);
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $company = $order->company;
            if (! $company?->email) {
                return;
            }
            $settings = $company->settings;
            if (! $settings || ! $settings->notifications_enabled) {
                return;
            }
            try {
                $ordersUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/dashboard/orders';
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
        });
    }
}
