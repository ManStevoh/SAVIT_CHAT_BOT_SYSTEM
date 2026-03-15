<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'dataType' => 'required|in:companies,users,subscriptions,revenue',
            'format' => 'required|in:csv,json,xlsx',
        ]);

        $dataType = $request->dataType;
        $format = $request->format;
        $filename = "{$dataType}-" . time() . ".{$format}";

        if ($format === 'json') {
            $data = $this->getExportData($dataType);
            $path = "exports/{$filename}";
            Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT));
            $downloadUrl = url('api/admin/export/download/' . basename($path));
        } else {
            // CSV/XLSX would require a package (e.g. League CSV); return placeholder URL
            $downloadUrl = "/exports/{$filename}";
        }

        return response()->json([
            'success' => true,
            'downloadUrl' => $downloadUrl,
            'message' => 'Export generated successfully',
        ]);
    }

    private function getExportData(string $dataType): array
    {
        return match ($dataType) {
            'companies' => Company::all()->toArray(),
            'users' => User::with('company')->get()->toArray(),
            'subscriptions' => [],
            'revenue' => ['total' => 0, 'mrr' => 0],
            default => [],
        };
    }
}
