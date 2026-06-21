<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyNotification;
use App\Models\Order;
use Illuminate\Support\Str;

class CompanyInAppNotificationService
{
    /**
     * @param  'handoff'|'agent_active'|'message'  $kind
     */
    public function recordWhatsAppCustomerAlert(
        Company $company,
        int $chatId,
        string $customerLabel,
        string $messageText,
        string $kind
    ): void {
        $preview = Str::limit(trim($messageText), 200);

        if ($kind === 'handoff') {
            $type = 'handoff';
            $title = 'Customer requested human';
        } elseif ($kind === 'agent_active') {
            $type = 'info';
            $title = "New message while you're handling the chat";
        } else {
            $type = 'info';
            $title = 'New message from '.$customerLabel;
        }

        CompanyNotification::create([
            'company_id' => $company->id,
            'chat_id' => $chatId,
            'type' => $type,
            'title' => $title,
            'body' => $preview !== '' ? $preview : null,
        ]);
    }

    public function recordNewOrder(Order $order): void
    {
        $company = $order->company;
        if (! $company) {
            return;
        }
        $settings = $company->settings;
        if (! $settings || ! $settings->notifications_enabled) {
            return;
        }

        $total = number_format((float) $order->total, 2);
        $currency = method_exists($settings, 'displayCurrencyCode')
            ? $settings->displayCurrencyCode()
            : 'KES';

        CompanyNotification::create([
            'company_id' => $company->id,
            'chat_id' => $order->chat_id,
            'order_id' => $order->id,
            'type' => 'order',
            'title' => 'New order '.$order->order_number,
            'body' => ($order->customer_name ?: 'Customer').' - '.$currency.' '.$total,
        ]);
    }

    public function recordFirstAttributedSale(Company $company, Order $order, string $postLabel): void
    {
        $total = number_format((float) $order->total, 2);

        CompanyNotification::create([
            'company_id' => $company->id,
            'chat_id' => $order->chat_id,
            'order_id' => $order->id,
            'type' => 'growth',
            'title' => 'First attributed sale!',
            'body' => "Order {$order->order_number} ({$total}) tied to \"{$postLabel}\". Your Growth loop is working.",
        ]);
    }

    public function recordGrowthLimitWarning(Company $company, string $resource, int $used, int $limit): void
    {
        CompanyNotification::create([
            'company_id' => $company->id,
            'type' => 'growth',
            'title' => ucfirst($resource).' limit reached',
            'body' => "You've used {$used} of {$limit} {$resource} this period. Upgrade to continue.",
        ]);
    }
}
