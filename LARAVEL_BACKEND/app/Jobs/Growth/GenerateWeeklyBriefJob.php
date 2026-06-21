<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Services\Growth\GrowthLimitService;
use App\Services\Growth\GrowthStrategyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWeeklyBriefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GrowthStrategyService $strategy): void
    {
        Company::where('status', 'active')->each(function (Company $company) use ($strategy) {
            if (GrowthLimitService::isGrowthEnabled($company)) {
                $strategy->generateWeeklyBrief($company);
            }
        });
    }
}
