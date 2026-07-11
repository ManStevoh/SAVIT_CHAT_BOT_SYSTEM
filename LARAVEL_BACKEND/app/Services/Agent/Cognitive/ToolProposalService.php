<?php

namespace App\Services\Agent\Cognitive;

use App\Models\AgentToolInvocation;
use App\Models\ToolProposal;

/**
 * AI creates new tools (#44) — detect repeated tool chains and propose workflows.
 */
final class ToolProposalService
{
    /**
     * @return list<ToolProposal>
     */
    public function detectForCompany(int $companyId, int $lookbackDays = 30): array
    {
        $invocations = AgentToolInvocation::query()
            ->where('company_id', $companyId)
            ->where('success', true)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->orderBy('chat_id')
            ->orderBy('created_at')
            ->get(['chat_id', 'tool_name', 'created_at']);

        $chainsByChat = [];
        foreach ($invocations as $inv) {
            $chainsByChat[$inv->chat_id][] = $inv->tool_name;
        }

        $chainCounts = [];
        foreach ($chainsByChat as $chain) {
            if (count($chain) < 2) {
                continue;
            }
            $key = implode(' → ', array_unique($chain));
            $chainCounts[$key] = ($chainCounts[$key] ?? 0) + 1;
        }

        $created = [];
        foreach ($chainCounts as $chain => $count) {
            if ($count < 2) {
                continue;
            }
            $tools = explode(' → ', $chain);
            $name = 'workflow_'.str_replace(' ', '_', mb_substr($chain, 0, 60));
            $existing = ToolProposal::query()
                ->where('company_id', $companyId)
                ->where('proposed_name', $name)
                ->where('status', 'proposed')
                ->first();

            if ($existing) {
                $existing->update(['occurrence_count' => $count]);
                $created[] = $existing->fresh();
                continue;
            }

            $created[] = ToolProposal::create([
                'company_id' => $companyId,
                'proposed_name' => $name,
                'description' => "Repeated tool chain detected {$count} times: {$chain}",
                'tool_chain' => $tools,
                'occurrence_count' => $count,
                'status' => 'proposed',
            ]);
        }

        return $created;
    }
}
