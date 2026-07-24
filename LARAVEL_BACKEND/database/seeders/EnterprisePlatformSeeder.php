<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class EnterprisePlatformSeeder extends Seeder
{
    public function run(): void
    {
        $entitlements = \App\Services\Platform\EntitlementService::DEFAULTS;

        foreach ($entitlements as $slug => $data) {
            Plan::where('slug', $slug)->update(['entitlements' => $data]);
        }

        $templates = [
            ['key' => 'intelligence.case_opened', 'title' => 'Investigation case opened', 'body_template' => 'Goal: {{goal}}', 'type' => 'info'],
            ['key' => 'approval.pending', 'title' => 'Agent action awaiting approval', 'body_template' => '{{action}}', 'type' => 'warning'],
            ['key' => 'approval.executed', 'title' => 'Approved action executed', 'body_template' => '{{action}}', 'type' => 'success'],
            ['key' => 'subscription.confirmed', 'title' => 'Subscription confirmed', 'body_template' => 'Plan {{plan}} active until {{end_date}}', 'type' => 'success'],
            ['key' => 'subscription.trial_started', 'title' => 'Free trial started', 'body_template' => 'Your {{plan}} trial is active for {{days}} days (ends {{end_date}}).', 'type' => 'success'],
            ['key' => 'subscription.expiring', 'title' => 'Subscription expiring soon', 'body_template' => 'Your {{plan}} plan expires on {{end_date}}', 'type' => 'warning'],
            ['key' => 'subscription.expired', 'title' => 'Subscription expired', 'body_template' => 'Your {{plan}} subscription ended on {{end_date}}. Renew to restore access.', 'type' => 'warning'],
            ['key' => 'payment.received', 'title' => 'Payment received', 'body_template' => '{{amount}} {{currency}} via {{gateway}}', 'type' => 'success'],
            ['key' => 'order.new', 'title' => 'New order', 'body_template' => 'Order {{order_number}} — {{total}}', 'type' => 'info'],
            ['key' => 'usage.limit_warning', 'title' => 'Usage limit warning', 'body_template' => '{{meter}} at {{percent}}% of plan limit', 'type' => 'warning'],
            ['key' => 'alert.low_stock', 'title' => 'Low stock alert', 'body_template' => '{{summary}}', 'type' => 'warning'],
            ['key' => 'alert.sales_drop', 'title' => 'Sales drop detected', 'body_template' => '{{summary}}', 'type' => 'warning'],
            ['key' => 'alert.commerce', 'title' => 'Commerce alert', 'body_template' => '{{summary}}', 'type' => 'info'],
        ];

        foreach ($templates as $tpl) {
            NotificationTemplate::updateOrCreate(
                ['key' => $tpl['key']],
                [
                    'channel' => 'in_app',
                    'title' => $tpl['title'],
                    'body_template' => $tpl['body_template'],
                    'type' => $tpl['type'],
                    'variables' => ['goal', 'action', 'plan', 'end_date', 'days', 'amount', 'currency', 'gateway', 'order_number', 'total', 'meter', 'percent'],
                    'is_active' => true,
                ],
            );
        }
    }
}
