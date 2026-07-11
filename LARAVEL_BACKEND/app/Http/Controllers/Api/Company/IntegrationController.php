<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyIntegration;
use App\Services\Agent\Integrations\ConnectorRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request, ConnectorRegistry $registry): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }

        $connected = CompanyIntegration::where('company_id', $company->id)->get()->keyBy('connector_type');

        $connectors = collect($registry->catalog())->map(function (array $meta) use ($connected) {
            $row = $connected->get($meta['type']);

