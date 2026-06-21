<?php

namespace App\Jobs\Growth;

use App\Models\PortfolioRecommendation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PrunePortfolioRecommendationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $days = (int) config('growth.portfolio_prune_days', 90);
        $cutoff = now()->subDays($days);

        $deleted = PortfolioRecommendation::where('created_at', '<', $cutoff)->delete();

        if ($deleted > 0) {
            Log::info('Pruned old portfolio recommendations', ['deleted' => $deleted, 'older_than_days' => $days]);
        }
    }
}
