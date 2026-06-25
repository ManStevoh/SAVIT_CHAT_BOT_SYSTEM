<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\ConversationLearningExportService;
use Illuminate\Http\Request;

class LearningExportController extends Controller
{
    public function export(Request $request, ConversationLearningExportService $export)
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        return $export->exportCsvForCompany($company);
    }
}
