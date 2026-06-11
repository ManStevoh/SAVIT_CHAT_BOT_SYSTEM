<?php

namespace App\Services\Growth;

use App\Models\GrowthAdSpendEntry;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaAdsService
{
    public function syncAdSpendForAccount(SocialAccount $account, int $days = 30): int
    {
        $adAccountId = $account->ad_account_id ?? ($account->metadata['ad_account_id'] ?? null);
        if (! $adAccountId || ! $account->access_token) {
            return 0;
        }

        if (! str_starts_with($adAccountId, 'act_')) {
            $adAccountId = 'act_'.$adAccountId;
        }

        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $since = now()->subDays($days)->format('Y-m-d');
        $until = now()->format('Y-m-d');

        try {
            $response = Http::withToken($account->access_token)
                ->timeout(30)
                ->get("{$graphUrl}/{$adAccountId}/insights", [
                    'fields' => 'spend,impressions,clicks,campaign_name,date_start,date_stop',
                    'level' => 'campaign',
                    'time_range' => json_encode(['since' => $since, 'until' => $until]),
                    'time_increment' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('Meta Ads insights failed', [
                    'company_id' => $account->company_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return 0;
            }

            $synced = 0;
            foreach ($response->json('data', []) as $row) {
                $spentAt = $row['date_start'] ?? $since;
                $campaign = $row['campaign_name'] ?? 'Meta Ads';
                $amount = (float) ($row['spend'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $externalId = hash('sha256', implode('|', [
                    $account->company_id,
                    $adAccountId,
                    $spentAt,
                    $campaign,
                ]));

                GrowthAdSpendEntry::updateOrCreate(
                    [
                        'company_id' => $account->company_id,
                        'external_id' => $externalId,
                    ],
                    [
                        'platform' => 'facebook',
                        'campaign_name' => $campaign,
                        'amount' => $amount,
                        'currency' => config('growth.default_currency', 'KES'),
                        'spent_at' => $spentAt,
                        'source' => 'meta_api',
                        'metadata' => [
                            'impressions' => (int) ($row['impressions'] ?? 0),
                            'clicks' => (int) ($row['clicks'] ?? 0),
                            'ad_account_id' => $adAccountId,
                        ],
                    ]
                );
                $synced++;
            }

            return $synced;
        } catch (\Throwable $e) {
            Log::warning('MetaAdsService sync error', ['error' => $e->getMessage()]);

            return 0;
        }
    }

    public function discoverAdAccounts(SocialAccount $account): array
    {
        if (! $account->access_token) {
            return [];
        }

        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $response = Http::withToken($account->access_token)
            ->timeout(20)
            ->get("{$graphUrl}/me/adaccounts", ['fields' => 'id,name,account_id,currency']);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('data', []))->map(fn ($a) => [
            'id' => $a['id'] ?? null,
            'name' => $a['name'] ?? 'Ad Account',
            'accountId' => $a['account_id'] ?? null,
            'currency' => $a['currency'] ?? null,
        ])->all();
    }
}
