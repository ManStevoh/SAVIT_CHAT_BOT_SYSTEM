<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Services\Growth\GrowthIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGrowthIntegrationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(GrowthIntegrationService $integrations): void
    {
        $query = Company::where('status', 'active');
        if ($this->companyId) {
            $query->where('id', $this->companyId);
        }

        foreach ($query->pluck('id') as $companyId) {
            $company = Company::find($companyId);
            if ($company) {
                $integrations->syncCompany($company);
            }
        }
    }
}
