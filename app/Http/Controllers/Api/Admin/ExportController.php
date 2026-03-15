<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportController extends Controller
{
    private const EXPORT_DISK = 'local';
    private const EXPORT_DIR = 'exports';

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'dataType' => 'required|in:companies,users,subscriptions,revenue,logs',
            'format' => 'required|in:csv,json',
        ]);

        $dataType = $request->dataType;
        $format = $request->format;
        $filename = $dataType . '-' . time() . '.' . $format;
        $path = self::EXPORT_DIR . '/' . $filename;

        if ($format === 'json') {
            $data = $this->getExportData($dataType);
            Storage::disk(self::EXPORT_DISK)->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->writeCsvExport($dataType, $path);
        }

        $downloadUrl = url('api/admin/export/download/' . basename($path));

        return response()->json([
            'success' => true,
            'downloadUrl' => $downloadUrl,
            'filename' => $filename,
            'message' => 'Export generated successfully',
        ]);
    }

    public function download(Request $request, string $filename): Response|JsonResponse
    {
        $safeName = basename($filename);
        if ($safeName !== $filename || preg_match('/\\.\\./', $filename)) {
            return response()->json(['message' => 'Invalid file.'], 400);
        }
        $path = self::EXPORT_DIR . '/' . $safeName;
        if (! Storage::disk(self::EXPORT_DISK)->exists($path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $mime = str_ends_with($safeName, '.json') ? 'application/json' : 'text/csv';
        $contents = Storage::disk(self::EXPORT_DISK)->get($path);
        Storage::disk(self::EXPORT_DISK)->delete($path);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
        ]);
    }

    private function getExportData(string $dataType): array
    {
        return match ($dataType) {
            'companies' => Company::all()->toArray(),
            'users' => User::with('company:id,name,email')->get()->makeHidden(['password', 'remember_token'])->toArray(),
            'subscriptions' => Subscription::with('company:id,name,email')->get()->toArray(),
            'revenue' => $this->getRevenueExportData(),
            'logs' => SystemLog::orderByDesc('created_at')->limit(2000)->get()->toArray(),
            default => [],
        };
    }

    private function getRevenueExportData(): array
    {
        $totalRevenue = (float) Order::sum('total');
        $mrr = (float) Subscription::where('status', 'active')->sum('amount');
        $byPlan = Subscription::where('status', 'active')
            ->selectRaw('plan, SUM(amount) as amount, COUNT(*) as count')
            ->groupBy('plan')
            ->get()
            ->map(fn ($r) => ['plan' => $r->plan, 'amount' => (float) $r->amount, 'count' => (int) $r->count])
            ->values()
            ->all();
        $ordersSummary = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'orders' => (int) $r->count, 'total' => (float) $r->total])
            ->values()
            ->all();

        return [
            'totalRevenue' => $totalRevenue,
            'mrr' => $mrr,
            'byPlan' => $byPlan,
            'ordersSummary' => $ordersSummary,
        ];
    }

    private function writeCsvExport(string $dataType, string $path): void
    {
        $data = $this->getExportData($dataType);
        $rows = $this->flattenRowsForCsv($dataType, $data);
        if (empty($rows)) {
            Storage::disk(self::EXPORT_DISK)->put($path, "");
            return;
        }
        $writer = Writer::createFromString();
        $writer->insertOne(array_keys($rows[0]));
        $writer->insertAll($this->toPlainRows($rows));
        Storage::disk(self::EXPORT_DISK)->put($path, $writer->toString());
    }

    private function flattenRowsForCsv(string $dataType, array $data): array
    {
        if ($dataType === 'revenue') {
            $out = [];
            $out[] = ['metric' => 'totalRevenue', 'value' => $data['totalRevenue'] ?? 0];
            $out[] = ['metric' => 'mrr', 'value' => $data['mrr'] ?? 0];
            foreach ($data['byPlan'] ?? [] as $r) {
                $out[] = ['metric' => 'plan_' . ($r['plan'] ?? ''), 'value' => $r['amount'] ?? 0, 'count' => $r['count'] ?? 0];
            }
            foreach ($data['ordersSummary'] ?? [] as $r) {
                $out[] = ['metric' => 'orders', 'date' => $r['date'] ?? '', 'orders' => $r['orders'] ?? 0, 'total' => $r['total'] ?? 0];
            }
            return $out;
        }
        if ($dataType === 'logs') {
            return array_map(fn ($log) => [
                'id' => $log['id'] ?? '',
                'type' => $log['type'] ?? '',
                'message' => $log['message'] ?? '',
                'source' => $log['source'] ?? '',
                'details' => is_string($log['details'] ?? null) ? $log['details'] : json_encode($log['details'] ?? null),
                'created_at' => $log['created_at'] ?? '',
            ], $data);
        }
        if ($dataType === 'companies') {
            return array_map(fn ($c) => [
                'id' => $c['id'] ?? '',
                'name' => $c['name'] ?? '',
                'email' => $c['email'] ?? '',
                'phone' => $c['phone'] ?? '',
                'address' => $c['address'] ?? '',
                'plan' => $c['plan'] ?? '',
                'status' => $c['status'] ?? '',
                'created_at' => $c['created_at'] ?? '',
            ], $data);
        }
        if ($dataType === 'users') {
            return array_map(fn ($u) => [
                'id' => $u['id'] ?? '',
                'name' => $u['name'] ?? '',
                'email' => $u['email'] ?? '',
                'role' => $u['role'] ?? '',
                'company_id' => $u['company_id'] ?? '',
                'company_name' => $u['company']['name'] ?? '',
                'status' => $u['status'] ?? '',
                'created_at' => $u['created_at'] ?? '',
            ], $data);
        }
        if ($dataType === 'subscriptions') {
            return array_map(fn ($s) => [
                'id' => $s['id'] ?? '',
                'company_id' => $s['company_id'] ?? '',
                'company_name' => $s['company']['name'] ?? '',
                'plan' => $s['plan'] ?? '',
                'status' => $s['status'] ?? '',
                'amount' => $s['amount'] ?? '',
                'start_date' => $s['start_date'] ?? '',
                'end_date' => $s['end_date'] ?? '',
                'created_at' => $s['created_at'] ?? '',
            ], $data);
        }
        return [];
    }

    private function toPlainRows(array $rows): array
    {
        return array_map(fn ($row) => array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $row), $rows);
    }
}
