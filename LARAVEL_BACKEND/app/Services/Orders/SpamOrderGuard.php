<?php

namespace App\Services\Orders;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Order;

class SpamOrderGuard
{
    /**
     * @return array{allowed: bool, reason?: string}
     */
    public function assertCanPlaceOrder(Company $company, ?string $phone = null, ?Chat $chat = null): array
    {
        if ($chat?->blocked_from_ordering) {
            return [
                'allowed' => false,
                'reason' => 'Ordering is temporarily blocked for this number. Please contact the business.',
            ];
        }

        $settings = $company->settings;
        if (! $settings || ! $settings->spam_order_protection_enabled) {
            return ['allowed' => true];
        }

        $phone = $phone ?: $chat?->customer_phone;
        if (! $phone) {
            return ['allowed' => true];
        }

        $perHour = max(1, (int) ($settings->spam_max_orders_per_hour ?? 5));
        $perDay = max(1, (int) ($settings->spam_max_orders_per_day ?? 20));

        $hourly = Order::query()
            ->where('company_id', $company->id)
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourly >= $perHour) {
            return [
                'allowed' => false,
                'reason' => 'Too many orders in a short time. Please wait a bit and try again.',
            ];
        }

        $daily = Order::query()
            ->where('company_id', $company->id)
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($daily >= $perDay) {
            return [
                'allowed' => false,
                'reason' => 'Daily order limit reached. Please try again tomorrow or contact the business.',
            ];
        }

        return ['allowed' => true];
    }

    public function flagOrderAsSpam(Order $order): void
    {
        $order->spam_flagged = true;
        $order->save();
    }
}
