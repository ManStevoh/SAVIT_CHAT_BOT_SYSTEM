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
