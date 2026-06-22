<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Services\Growth\GrowthPatternService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractGrowthPatternsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(GrowthPatternService $patterns): void
    {
        if ($this->companyId) {
            $company = Company::find($this->companyId);
            if ($company) {
                $patterns->extractForCompany($company);
            }

            return;
        }

        Company::where('status', 'active')->each(function (Company $company) use ($patterns) {
            $patterns->extractForCompany($company);
        });
    }
}
