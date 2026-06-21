<?php

namespace App\Jobs\Growth;

use App\Models\Company;
use App\Services\Growth\CrmFollowUpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCrmFollowUpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(CrmFollowUpService $crm): void
    {
        if ($this->companyId) {
            $result = $crm->processCompany($this->companyId);
            if ($result['sent'] > 0) {
                Log::info('CRM follow-ups sent', ['company_id' => $this->companyId, 'sent' => $result['sent']]);
            }

            return;
        }

        $companies = Company::where('status', 'active')
            ->whereHas('whatsappAccount', fn ($q) => $q->where('status', 'active'))
            ->pluck('id');

        foreach ($companies as $companyId) {
            $result = $crm->processCompany((int) $companyId);
            if ($result['sent'] > 0) {
                Log::info('CRM follow-ups sent', ['company_id' => $companyId, 'sent' => $result['sent']]);
            }
        }
    }
}
