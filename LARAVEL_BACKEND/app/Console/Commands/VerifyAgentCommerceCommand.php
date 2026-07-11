<?php



namespace App\Console\Commands;



use App\Models\Company;
use App\Models\CompanySetting;

use App\Services\Agent\AgentToolRegistry;

use App\Services\Agent\CommerceAgentReplyService;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Schema;



/**

 * Runtime verification of Agent Commerce OS — run after deploy or locally.

 */

class VerifyAgentCommerceCommand extends Command

{

    protected $signature = 'agent:verify {--company= : Optional company ID to check settings}';



    protected $description = 'Verify Agent Commerce OS: schema, tools, config, and optional company settings';



    public function handle(AgentToolRegistry $registry): int

    {

        $this->info('Agent Commerce OS verification');

        $this->newLine();



        $checks = [

            'company_settings.agent_commerce_enabled' => Schema::hasColumn('company_settings', 'agent_commerce_enabled'),

            'company_settings.agent_business_goals' => Schema::hasColumn('company_settings', 'agent_business_goals'),

            'company_settings.agent_proactive_enabled' => Schema::hasColumn('company_settings', 'agent_proactive_enabled'),

            'customer_memories table' => Schema::hasTable('customer_memories'),

            'agent_tool_invocations table' => Schema::hasTable('agent_tool_invocations'),

            'agent_reflections table' => Schema::hasTable('agent_reflections'),
            'agent_reasoning_traces table' => Schema::hasTable('agent_reasoning_traces'),
            'customer_intent_chains table' => Schema::hasTable('customer_intent_chains'),
            'agent_operating_guides table' => Schema::hasTable('agent_operating_guides'),
            'commerce_briefs table' => Schema::hasTable('commerce_briefs'),
            'company_settings.digital_twin' => Schema::hasColumn('company_settings', 'digital_twin'),
            'chats.detected_sentiment' => Schema::hasColumn('chats', 'detected_sentiment'),
            'business_world_snapshots table' => Schema::hasTable('business_world_snapshots'),
            'business_opportunities table' => Schema::hasTable('business_opportunities'),
            'agent_trust_logs table' => Schema::hasTable('agent_trust_logs'),
            'agent_action_requests table' => Schema::hasTable('agent_action_requests'),
            'organizational_memories table' => Schema::hasTable('organizational_memories'),
            'business_health_scores table' => Schema::hasTable('business_health_scores'),
            'company_settings.business_dna' => Schema::hasColumn('company_settings', 'business_dna'),
            'cognitive_episodes table' => Schema::hasTable('cognitive_episodes'),
            'strategic_memories table' => Schema::hasTable('strategic_memories'),
            'tool_proposals table' => Schema::hasTable('tool_proposals'),
            'platform_intelligence_patterns table' => Schema::hasTable('platform_intelligence_patterns'),
            'executive_plans table' => Schema::hasTable('executive_plans'),
            'knowledge_artifacts table' => Schema::hasTable('knowledge_artifacts'),
            'cognitive_simulations table' => Schema::hasTable('cognitive_simulations'),
            'commerce_agent_runs table' => Schema::hasTable('commerce_agent_runs'),
            'product_relationships table' => Schema::hasTable('product_relationships'),
            'commerce_agent_events table' => Schema::hasTable('commerce_agent_events'),
            'message_vision_analyses table' => Schema::hasTable('message_vision_analyses'),
            'company_brain_snapshots table' => Schema::hasTable('company_brain_snapshots'),
            'owner_analytics_investigations table' => Schema::hasTable('owner_analytics_investigations'),
            'commerce_experiments table' => Schema::hasTable('commerce_experiments'),
            'commerce_experiment_variants table' => Schema::hasTable('commerce_experiment_variants'),
            'agent_action_requests.execution_result' => Schema::hasColumn('agent_action_requests', 'execution_result'),
            'agent_action_requests.rejected_at' => Schema::hasColumn('agent_action_requests', 'rejected_at'),

            'orders.agent_proactive_follow_up_at' => Schema::hasColumn('orders', 'agent_proactive_follow_up_at'),

        ];



        $allPass = true;

        foreach ($checks as $label => $ok) {

            $this->line(sprintf('  [%s] %s', $ok ? 'OK' : 'FAIL', $label));

            if (! $ok) {

                $allPass = false;

            }

        }



        $toolCount = count($registry->all());

        $expectedTools = 20;

        $toolsOk = $toolCount === $expectedTools;

        $this->line(sprintf('  [%s] Tool registry (%d tools, expected %d)', $toolsOk ? 'OK' : 'FAIL', $toolCount, $expectedTools));

        if (! $toolsOk) {

            $allPass = false;

        }



        $maxIter = config('agent.max_loop_iterations');

        $this->line(sprintf('  [OK] config agent.max_loop_iterations = %s', $maxIter));



        $companyId = $this->option('company');

        if ($companyId) {
            $company = Company::with('settings')->find($companyId);
            if (! $company) {
                $this->warn("  Company {$companyId} not found");
            } else {
                $enabled = CommerceAgentReplyService::isEnabledForCompany($company);
                $settings = $company->settings;
                $this->line(sprintf('  [INFO] Company %s agent_commerce_enabled: %s', $companyId, $enabled ? 'yes' : 'no'));
                $this->line(sprintf('  [INFO] agent_proactive_enabled: %s', ($settings?->agent_proactive_enabled ?? false) ? 'yes' : 'no'));
                $goals = $settings?->agent_business_goals ?? [];
                $this->line('  [INFO] agent_business_goals: '.(empty($goals) ? '(defaults)' : implode(', ', $goals)));
            }
        }



        $this->newLine();

        if ($allPass) {
            $this->info('All schema and registry checks passed.');
            $this->line('Run tests: php artisan test --filter=CommerceAgent');

            return self::SUCCESS;
        }

        $this->error('Some checks failed. Run: php artisan migrate');

        return self::FAILURE;

    }

}

