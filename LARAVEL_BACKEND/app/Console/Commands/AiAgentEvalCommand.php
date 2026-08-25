<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Product;
use App\Services\AI\AnthropicToolPayloadConverter;
use App\Services\AI\GeminiToolPayloadConverter;
use App\Services\AI\ReplyGuardService;
use App\Services\Agent\Cognitive\SelfCritiqueService;
use App\Services\Agent\AgentCommerceProvisioningService;
use App\Services\Platform\EntitlementService;
use Illuminate\Console\Command;

/**
 * Offline AI quality eval harness (no live LLM calls).
 */
class AiAgentEvalCommand extends Command
{
    protected $signature = 'ai:eval-agent {--json : Output JSON summary}';

    protected $description = 'Run offline evals for reply guard, self-critique, tool payload converters, and agent commerce entitlements';

    public function handle(
        ReplyGuardService $guard,
        SelfCritiqueService $critique,
        EntitlementService $entitlements,
        AgentCommerceProvisioningService $provisioning,
    ): int {
        $passed = 0;
        $failed = 0;
        $results = [];

        $company = Company::create([
            'name' => 'Eval Co',
            'email' => 'eval-ai@test.local',
            'status' => 'active',
        ]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Blue Mug',
            'price' => 15.00,
            'stock' => 0,
            'status' => 'active',
            'category' => 'Kitchen',
        ]);
        Product::create([
            'company_id' => $company->id,
            'name' => 'Green Plate',
            'price' => 25.50,
            'stock' => 10,
            'status' => 'active',
            'category' => 'Kitchen',
        ]);

        // 1. Price hallucination
        $guarded = $guard->guard($company, 'The Blue Mug is only 999.99 today!');
        $ok = str_contains($guarded, 'see catalog for price');
        $results[] = ['name' => 'reply_guard_price_hallucination', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 2. Known price kept
        $kept = $guard->guard($company, 'Green Plate is 25.50.');
        $ok = str_contains($kept, '25.50') && ! str_contains($kept, 'see catalog for price');
        $results[] = ['name' => 'reply_guard_known_price', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 3. Out-of-stock claim note
        $stock = $guard->guard($company, 'Blue Mug is in stock and ready to ship.');
        $ok = str_contains(mb_strtolower($stock), 'out of stock');
        $results[] = ['name' => 'reply_guard_stock_claim', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 4. Empathy rewrite
        $review = $critique->review($company, 'Your order failed.', [
            'perception' => ['emotion' => 'angry', 'topic' => 'order'],
        ]);
        $ok = is_string($review['rewritten'] ?? null) && str_contains(mb_strtolower($review['rewritten']), 'sorry');
        $results[] = ['name' => 'self_critique_empathy', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 5. Internal leak rewrite
        $leak = $critique->review($company, 'Based on internal reasoning confidence: 0.9', [
            'perception' => ['emotion' => 'neutral', 'topic' => 'general'],
        ]);
        $ok = in_array('leaked_internal_reasoning', $leak['issues'], true);
        $results[] = ['name' => 'self_critique_leak', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 6. Anthropic tool conversion
        $anthropic = new AnthropicToolPayloadConverter;
        $tools = $anthropic->tools([[
            'type' => 'function',
            'function' => [
                'name' => 'search_products',
                'description' => 'Find products',
                'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]],
            ],
        ]]);
        $ok = ($tools[0]['name'] ?? '') === 'search_products' && isset($tools[0]['input_schema']);
        $results[] = ['name' => 'anthropic_tool_convert', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 7. Gemini tool conversion
        $gemini = new GeminiToolPayloadConverter;
        $gTools = $gemini->toolConfig([[
            'type' => 'function',
            'function' => [
                'name' => 'search_faq',
                'description' => 'FAQ',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
        ]]);
        $ok = ($gTools['functionDeclarations'][0]['name'] ?? '') === 'search_faq';
        $results[] = ['name' => 'gemini_tool_convert', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        // 8. Entitlements
        $ok = ($entitlements->limitsForPlanSlug('professional')['agent_commerce'] ?? false) === true
            && ($entitlements->limitsForPlanSlug('starter')['agent_commerce'] ?? false) === true;
        $results[] = ['name' => 'entitlement_agent_commerce', 'pass' => $ok];
        $ok ? $passed++ : $failed++;

        if ($this->option('json')) {
            $this->line(json_encode([
                'passed' => $passed,
                'failed' => $failed,
                'results' => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            foreach ($results as $row) {
                $this->line(($row['pass'] ? '[PASS]' : '[FAIL]').' '.$row['name']);
            }
            $this->info("Eval complete: {$passed} passed, {$failed} failed.");
        }

        // Cleanup ephemeral company products
        Product::where('company_id', $company->id)->delete();
        $company->delete();

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
