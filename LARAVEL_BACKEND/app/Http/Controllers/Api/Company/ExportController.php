<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportController extends Controller
{
    private const EXPORT_DISK = 'local';
    private const EXPORT_DIR = 'exports/company';

    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'dataType' => 'required|in:orders,products,customers,faqs',
            'format' => 'required|in:csv,json',
        ]);

        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $dataType = $request->dataType;
        $format = $request->format;
        $filename = $dataType . '-' . time() . '.' . $format;
        $path = self::EXPORT_DIR . '/' . $companyId . '-' . $filename;

        if ($format === 'json') {
            $data = $this->getCompanyExportData($companyId, $dataType);
            Storage::disk(self::EXPORT_DISK)->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->writeCompanyCsv($companyId, $dataType, $path);
        }

        $downloadUrl = url('api/company/export/download/' . basename($path));

        return response()->json([
            'success' => true,
            'downloadUrl' => $downloadUrl,
            'filename' => $filename,
            'message' => 'Export generated successfully',
        ]);
    }

    public function download(Request $request, string $filename): Response|JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $safeName = basename($filename);
        if ($safeName !== $filename || preg_match('/\\.\\./', $filename)) {
            return response()->json(['message' => 'Invalid file.'], 400);
        }
        if (! str_starts_with($safeName, (string) $companyId . '-')) {
            return response()->json(['message' => 'File not found.'], 404);
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
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/^\d+-/', '', $safeName) . '"',
        ]);
    }

    private function getCompanyExportData(int $companyId, string $dataType): array
    {
        return match ($dataType) {
            'orders' => Order::where('company_id', $companyId)
                ->with('orderProducts')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'customer_name' => $o->customer_name,
                    'customer_phone' => $o->customer_phone,
                    'total' => (float) $o->total,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'created_at' => $o->created_at->toIso8601String(),
                    'items' => $o->orderProducts->map(fn ($i) => [
                        'name' => $i->name,
                        'quantity' => $i->quantity,
                        'price' => (float) $i->price,
                    ])->all(),
                ])
                ->values()
                ->all(),
            'products' => Product::where('company_id', $companyId)->orderBy('name')->get()->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => (float) $p->price,
                'category' => $p->category,
                'stock' => $p->stock,
                'status' => $p->status,
                'created_at' => $p->created_at->toIso8601String(),
            ])->values()->all(),
            'customers' => $this->getCustomersExportData($companyId),
            'faqs' => Faq::where('company_id', $companyId)->orderBy('category')->get()->map(fn (Faq $f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'category' => $f->category,
                'keywords' => $f->keywords,
                'is_active' => $f->is_active,
                'usage_count' => $f->usage_count,
                'created_at' => $f->created_at->toIso8601String(),
            ])->values()->all(),
            default => [],
        };
    }

    private function getCustomersExportData(int $companyId): array
    {
        $customers = Order::where('company_id', $companyId)
            ->select('customer_phone', 'customer_name', DB::raw('COUNT(*) as order_count'), DB::raw('SUM(total) as total_spent'), DB::raw('MAX(created_at) as last_order_at'))
            ->groupBy('customer_phone', 'customer_name')
            ->get();
        return $customers->map(fn ($c) => [
            'customer_phone' => $c->customer_phone,
            'customer_name' => $c->customer_name,
            'order_count' => (int) $c->order_count,
            'total_spent' => (float) $c->total_spent,
            'last_order_at' => $c->last_order_at,
        ])->values()->all();
    }

    private function writeCompanyCsv(int $companyId, string $dataType, string $path): void
    {
        $data = $this->getCompanyExportData($companyId, $dataType);
        $rows = $this->rowsForCompanyCsv($dataType, $data);
        if (empty($rows)) {
            Storage::disk(self::EXPORT_DISK)->put($path, "");
            return;
        }
        $writer = Writer::createFromString();
        $writer->insertOne(array_keys($rows[0]));
        $writer->insertAll(array_map(fn ($r) => array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $r), $rows));
        Storage::disk(self::EXPORT_DISK)->put($path, $writer->toString());
    }

    private function rowsForCompanyCsv(string $dataType, array $data): array
    {
        if ($dataType === 'orders') {
            $out = [];
            foreach ($data as $o) {
                $items = $o['items'] ?? [];
                unset($o['items']);
                if (empty($items)) {
                    $out[] = array_merge($o, ['items' => '']);
                } else {
                    $first = true;
                    foreach ($items as $item) {
                        $row = $first ? array_merge($o, ['items' => json_encode([$item])]) : [
                            'id' => '',
                            'order_number' => $o['order_number'] ?? '',
                            'customer_name' => '',
                            'customer_phone' => '',
                            'total' => '',
                            'status' => '',
                            'payment_status' => '',
                            'created_at' => '',
                            'items' => json_encode([$item]),
                        ];
                        $out[] = $row;
                        $first = false;
                    }
                }
            }
            return $out;
        }
        if ($dataType === 'products' || $dataType === 'customers' || $dataType === 'faqs') {
            return array_map(fn ($r) => array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $r), $data);
        }
        return $data;
    }
}
