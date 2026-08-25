<?php

namespace App\Services\Platform;

use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Cache;

final class NotificationTemplateService
{
    public function resolve(string $key, array $data = []): array
    {
        $template = Cache::remember('notification_template:'.$key, 300, function () use ($key) {
            return NotificationTemplate::where('key', $key)->where('is_active', true)->first();
        });

        if ($template) {
            $body = $this->interpolate($template->body_template ?? '', $data);

            return [$template->title, $body !== '' ? $body : null, $template->type];
        }

        return $this->fallback($key, $data);
    }

    private function interpolate(string $template, array $data): string
    {
        $result = $template;
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $result = str_replace('{{'.$key.'}}', (string) $value, $result);
            }
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string|null, 2: string}
     */
    private function fallback(string $key, array $data): array
    {
        return match ($key) {
            'intelligence.case_opened' => [
                'Investigation case opened',
                'Goal: '.($data['goal'] ?? 'Business question'),
                'info',
            ],
            'approval.pending' => [
                'Agent action awaiting approval',
                (string) ($data['action'] ?? 'High-risk action'),
                'warning',
            ],
            'approval.executed' => [
                'Approved action executed',
                (string) ($data['action'] ?? 'Action completed'),
                'success',
            ],
            'payment.received' => [
                'Payment received',
                ($data['amount'] ?? '').' '.($data['currency'] ?? 'USD').' via '.($data['gateway'] ?? 'gateway'),
                'success',
            ],
            'subscription.confirmed' => [
                'Subscription confirmed',
                'Plan '.($data['plan'] ?? '').' active until '.($data['end_date'] ?? ''),
                'success',
            ],
            'subscription.trial_started' => [
                'Free trial started',
                'Your '.($data['plan'] ?? 'plan').' trial is active for '.($data['days'] ?? '14').' days (ends '.($data['end_date'] ?? '').').',
                'success',
            ],
            'subscription.expiring' => [
                'Subscription expiring soon',
                'Your '.($data['plan'] ?? 'plan').' subscription ends on '.($data['end_date'] ?? '').
                    (isset($data['days_left']) ? ' ('.$data['days_left'].' days left)' : ''),
                'warning',
            ],
            'subscription.expired' => [
                'Subscription expired',
                'Your '.($data['plan'] ?? 'plan').' subscription ended on '.($data['end_date'] ?? '').'. Renew to restore access.',
                'warning',
            ],
            'alert.low_stock' => [
                'Low stock alert',
                (string) ($data['summary'] ?? 'Review inventory'),
                'warning',
            ],
            'alert.sales_drop' => [
                'Sales drop detected',
                (string) ($data['summary'] ?? 'Review sales trend'),
                'warning',
            ],
            'alert.commerce' => [
                'Commerce alert',
                (string) ($data['summary'] ?? 'Event detected'),
                'info',
            ],
            default => [
                (string) ($data['title'] ?? 'Notification'),
                isset($data['body']) ? (string) $data['body'] : null,
                (string) ($data['type'] ?? 'info'),
            ],
        };
    }
}
