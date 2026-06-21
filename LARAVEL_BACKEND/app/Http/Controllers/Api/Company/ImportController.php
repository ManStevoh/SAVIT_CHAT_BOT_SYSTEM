<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportController extends Controller
{
    /**
     * Import products from CSV.
     * Expected columns: name, description, price, category, status (optional: stock).
     */
    public function importProducts(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $csv = Reader::createFromPath($path);
            $csv->setHeaderOffset(0);
            $headers = array_map('trim', $csv->getHeader());
            $stmt = (new Statement)->process($csv);
            $created = 0;
            $errors = [];

            foreach ($stmt->getRecords($headers) as $index => $record) {
                $row = array_combine($headers, $record);
                $row = array_map('trim', $row);
                $validator = Validator::make($row, [
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'price' => 'required|numeric|min:0',
                    'category' => 'nullable|string|max:255',
                    'status' => 'nullable|in:active,inactive',
                    'stock' => 'nullable|integer|min:0',
                ]);

                if ($validator->fails()) {
                    $errors[] = ['row' => $index + 2, 'errors' => $validator->errors()->all()];
                    continue;
                }

                Product::create([
                    'company_id' => $companyId,
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'price' => (float) ($row['price'] ?? 0),
                    'category' => $row['category'] ?? null,
                    'status' => in_array(strtolower($row['status'] ?? 'active'), ['inactive', '0', 'no'], true) ? 'inactive' : 'active',
                    'stock' => isset($row['stock']) ? (int) $row['stock'] : 0,
                ]);
                $created++;
            }

            return response()->json([
                'success' => true,
                'message' => "Imported {$created} product(s).",
                'created' => $created,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse CSV: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Import FAQs from CSV.
     * Expected columns: question, answer, category (optional: keywords, is_active).
     */
    public function importFaqs(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $csv = Reader::createFromPath($path);
            $csv->setHeaderOffset(0);
            $headers = array_map('trim', $csv->getHeader());
            $stmt = (new Statement)->process($csv);
            $created = 0;
            $errors = [];

            foreach ($stmt->getRecords($headers) as $index => $record) {
                $row = array_combine($headers, $record);
                $row = array_map('trim', $row);
                $validator = Validator::make($row, [
                    'question' => 'required|string|max:1000',
                    'answer' => 'required|string',
                    'category' => 'nullable|string|max:255',
                    'keywords' => 'nullable|string',
                    'is_active' => 'nullable|in:1,0,yes,no,true,false',
                ]);

                if ($validator->fails()) {
                    $errors[] = ['row' => $index + 2, 'errors' => $validator->errors()->all()];
                    continue;
                }

                $keywords = $row['keywords'] ?? null;
                if ($keywords !== null && $keywords !== '') {
                    $keywords = array_map('trim', preg_split('/[,;]/', $keywords));
                    $keywords = array_values(array_filter($keywords));
                } else {
                    $keywords = [];
                }

                $isActive = true;
                if (isset($row['is_active'])) {
                    $v = strtolower($row['is_active']);
                    $isActive = in_array($v, ['1', 'yes', 'true'], true);
                }

                Faq::create([
                    'company_id' => $companyId,
                    'question' => $row['question'],
                    'answer' => $row['answer'],
                    'category' => $row['category'] ?? null,
                    'keywords' => $keywords,
                    'is_active' => $isActive,
                    'usage_count' => 0,
                ]);
                $created++;
            }

            return response()->json([
                'success' => true,
                'message' => "Imported {$created} FAQ(s).",
                'created' => $created,
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to parse CSV: ' . $e->getMessage(),
            ], 422);
        }
    }
}
