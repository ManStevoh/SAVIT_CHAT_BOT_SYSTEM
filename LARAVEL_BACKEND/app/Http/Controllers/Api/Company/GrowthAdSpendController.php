<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\GrowthAdSpendEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthAdSpendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $since = now()->subDays(match ($request->input('period', '30d')) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        });

        $entries = GrowthAdSpendEntry::where('company_id', $companyId)
            ->where('spent_at', '>=', $since)
            ->orderByDesc('spent_at')
            ->limit(200)
            ->get()
            ->map(fn (GrowthAdSpendEntry $e) => $this->format($e));

        $total = (float) GrowthAdSpendEntry::where('company_id', $companyId)
            ->where('spent_at', '>=', $since)
            ->sum('amount');

        return response()->json([
            'entries' => $entries->values()->all(),
            'totalSpend' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $validated = $request->validate([
            'platform' => 'nullable|string|max:32',
            'campaignName' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'spentAt' => 'required|date',
        ]);

        $entry = GrowthAdSpendEntry::create([
            'company_id' => $companyId,
            'platform' => $validated['platform'] ?? null,
            'campaign_name' => $validated['campaignName'] ?? null,
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency'] ?? 'KES'),
            'spent_at' => $validated['spentAt'],
            'source' => 'manual',
        ]);

        return response()->json(['success' => true, 'entry' => $this->format($entry)]);
    }

    public function import(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return response()->json(['success' => false, 'message' => 'Could not read file.'], 422);
        }

        $header = fgetcsv($handle);
        $created = 0;
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < 2) {
                continue;
            }

            $map = $this->mapCsvRow($header, $data);
            if (! isset($map['amount'], $map['spent_at'])) {
                continue;
            }

            GrowthAdSpendEntry::create([
                'company_id' => $companyId,
                'platform' => $map['platform'] ?? null,
                'campaign_name' => $map['campaign_name'] ?? null,
                'amount' => (float) $map['amount'],
                'currency' => strtoupper($map['currency'] ?? 'KES'),
                'spent_at' => $map['spent_at'],
                'source' => 'csv_import',
            ]);
            $created++;
        }

        fclose($handle);

        return response()->json(['success' => true, 'created' => $created]);
    }

    public function destroy(Request $request, GrowthAdSpendEntry $entry): JsonResponse
    {
        if ((int) $request->user()->company_id !== (int) $entry->company_id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $entry->delete();

        return response()->json(['success' => true]);
    }

  /**
   * @param  array<int, string>|false  $header
   * @param  array<int, string>  $data
   * @return array<string, string>
   */
    private function mapCsvRow(array|false $header, array $data): array
    {
        if ($header === false) {
            return [
                'spent_at' => $data[0] ?? '',
                'amount' => $data[1] ?? '',
                'platform' => $data[2] ?? null,
                'campaign_name' => $data[3] ?? null,
                'currency' => $data[4] ?? 'KES',
            ];
        }

        $normalized = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', $h))), $header);
        $combined = [];
        foreach ($normalized as $i => $key) {
            $combined[$key] = $data[$i] ?? '';
        }

        return [
            'spent_at' => $combined['spent_at'] ?? $combined['date'] ?? '',
            'amount' => $combined['amount'] ?? $combined['spend'] ?? '',
            'platform' => $combined['platform'] ?? null,
            'campaign_name' => $combined['campaign_name'] ?? $combined['campaign'] ?? null,
            'currency' => $combined['currency'] ?? 'KES',
        ];
    }

    private function format(GrowthAdSpendEntry $entry): array
    {
        return [
            'id' => (string) $entry->id,
            'platform' => $entry->platform,
            'campaignName' => $entry->campaign_name,
            'amount' => (float) $entry->amount,
            'currency' => $entry->currency,
            'spentAt' => $entry->spent_at?->format('Y-m-d'),
            'source' => $entry->source,
        ];
    }
}
