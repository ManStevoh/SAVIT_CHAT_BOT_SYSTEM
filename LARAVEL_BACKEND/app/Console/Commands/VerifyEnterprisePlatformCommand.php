<?php

namespace App\Console\Commands;

use App\Services\Agent\AgentToolRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerifyEnterprisePlatformCommand extends Command
{
    protected $signature = 'platform:verify';

    protected $description = 'Verify Enterprise Platform Phase 2 schema and services';

    public function handle(AgentToolRegistry $registry): int
    {
        $this->info('Enterprise Platform Phase 2 verification');
        $this->newLine();

        $checks = [
            'plans.entitlements' => Schema::hasColumn('plans', 'entitlements'),
            'company_entitlement_overrides table' => Schema::hasTable('company_entitlement_overrides'),
            'usage_meters table' => Schema::hasTable('usage_meters'),
            'billing_payments table' => Schema::hasTable('billing_payments'),
            'notification_templates table' => Schema::hasTable('notification_templates'),
            'notification_deliveries table' => Schema::hasTable('notification_deliveries'),
            'company_api_keys table' => Schema::hasTable('company_api_keys'),
            'webhook_endpoints table' => Schema::hasTable('webhook_endpoints'),
            'webhook_deliveries table' => Schema::hasTable('webhook_deliveries'),
            'investigation_cases table' => Schema::hasTable('investigation_cases'),
            'audit_events table' => Schema::hasTable('audit_events'),
            'domain_events table' => Schema::hasTable('domain_events'),
            'company_policy_rules table' => Schema::hasTable('company_policy_rules'),
            'company_integrations table' => Schema::hasTable('company_integrations'),
            'company_settings.agent_council_enabled' => Schema::hasColumn('company_settings', 'agent_council_enabled'),
        ];

        $allPass = true;
        foreach ($checks as $label => $ok) {
            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : 'FAIL', $label));
            if (! $ok) {
                $allPass = false;
            }
        }

        $toolCount = count($registry->all());
        $this->line(sprintf('  [OK] Agent tools: %d', $toolCount));

        $this->newLine();
        if ($allPass) {
            $this->info('All enterprise platform checks passed.');
            $this->line('Run: php artisan test --filter=EnterprisePlatform');
            $this->line('Run: php artisan test --filter=CommerceAgent');

            return self::SUCCESS;
        }

        $this->error('Some checks failed. Run: php artisan migrate && php artisan db:seed --class=EnterprisePlatformSeeder');

        return self::FAILURE;
