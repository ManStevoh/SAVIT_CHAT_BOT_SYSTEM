<?php

namespace App\Jobs\Agent;

use App\Models\Company;
use App\Services\Agent\Company\CommerceMorningBriefService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyCommerceBriefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(CommerceMorningBriefService $briefs): void
    {
        $query = Company::query()
            ->where('status', 'active')
            ->whereHas('settings', fn ($q) => $q->where('agent_commerce_enabled', true));

        if ($this->companyId) {
            $query->where('id', $this->companyId);
        }

        foreach ($query->pluck('id') as $companyId) {
            try {
                $briefs->generateForCompany(Company::find((int) $companyId));
            } catch (\Throwable $e) {
                Log::warning('Commerce brief generation failed', [
                    'company_id' => $companyId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
