<?php

namespace App\Services\Orders;

use App\Models\Chat;
use App\Models\Company;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppMessageSenderService;
use Illuminate\Support\Facades\Log;

class CustomerRetentionService
{
    public function __construct(
        protected WhatsAppMessageSenderService $waSender,
    ) {}

    public function sendBirthdayWishes(): int
    {
        $sent = 0;
        $today = now();

        $chats = Chat::query()
            ->with(['company.settings'])
            ->whereNotNull('birthday')
            ->where('marketing_opt_in', true)
            ->whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->where(function ($q) use ($today) {
                $q->whereNull('last_birthday_wish_at')
                    ->orWhere('last_birthday_wish_at', '<', $today->copy()->startOfYear());
            })
            ->limit(200)
            ->get();

        foreach ($chats as $chat) {
            $company = $chat->company;
            $settings = $company?->settings;
            if (! $company || ! $settings?->birthday_automation_enabled) {
                continue;
            }
            if (! $chat->customer_phone) {
                continue;
            }

            $wa = WhatsAppAccount::where('company_id', $company->id)->where('status', 'active')->first();
            if (! $wa) {
                continue;
            }

            $percent = (int) ($settings->birthday_coupon_percent ?? 10);
            $name = $chat->customer_name ?: 'there';
            $template = trim((string) ($settings->birthday_message_template ?? ''));
            if ($template === '') {
                $template = "Happy birthday, {name}! Celebrate with {percent}% off your next order — today only. Reply here to order.";
            }
            $message = str_replace(
                ['{name}', '{percent}'],
                [$name, (string) $percent],
                $template
            );

            try {
                $result = $this->waSender->sendText($wa, $chat->customer_phone, $message);
                if (! ($result['success'] ?? false)) {
                    continue;
                }
                $chat->last_birthday_wish_at = now();
                $chat->save();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('birthday_wish_failed', ['chat_id' => $chat->id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    public function sendWinbacks(): int
    {
        $sent = 0;
        $companies = Company::query()->with('settings')->where('status', 'active')->get();

        foreach ($companies as $company) {
            $settings = $company->settings;
            if (! $settings?->winback_automation_enabled) {
                continue;
            }

            $wa = WhatsAppAccount::where('company_id', $company->id)->where('status', 'active')->first();
            if (! $wa) {
                continue;
            }

            $days = max(7, (int) ($settings->winback_days_inactive ?? 30));
            $cutoff = now()->subDays($days);

            $chats = Chat::query()
                ->where('company_id', $company->id)
                ->where('marketing_opt_in', true)
                ->whereNotNull('customer_phone')
                ->where('last_message_at', '<=', $cutoff)
                ->where(function ($q) {
                    $q->whereNull('last_winback_at')
                        ->orWhere('last_winback_at', '<', now()->subDays(60));
                })
                ->limit(50)
                ->get();

            foreach ($chats as $chat) {
                $store = $company->storefront_enabled && $company->store_slug
                    ? url('/s/'.$company->store_slug)
                    : null;
                $message = "We miss you".($chat->customer_name ? ", {$chat->customer_name}" : '')."!\n"
                    ."Ready for your next order? Reply here anytime."
                    .($store ? "\nShop: {$store}" : '');

                try {
                    $result = $this->waSender->sendText($wa, $chat->customer_phone, $message);
                    if (! ($result['success'] ?? false)) {
                        continue;
                    }
                    $chat->last_winback_at = now();
                    $chat->save();
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('winback_failed', ['chat_id' => $chat->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return $sent;
    }

    public function runDaily(): int
    {
        return $this->sendBirthdayWishes() + $this->sendWinbacks();
    }
}
