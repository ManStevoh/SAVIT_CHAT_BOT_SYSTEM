<?php

namespace App\Jobs\Growth;

use App\Models\SocialAccount;
use App\Services\Growth\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetaAdSpendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null, public int $days = 30) {}

    public function handle(MetaAdsService $ads): void
    {
        $query = SocialAccount::whereIn('platform', ['facebook', 'instagram'])
            ->where('status', 'connected')
            ->whereNotNull('ad_account_id');

        if ($this->companyId) {
            $query->where('company_id', $this->companyId);
        }

        foreach ($query->get() as $account) {
            $ads->syncAdSpendForAccount($account, $this->days);
        }
    }
}
