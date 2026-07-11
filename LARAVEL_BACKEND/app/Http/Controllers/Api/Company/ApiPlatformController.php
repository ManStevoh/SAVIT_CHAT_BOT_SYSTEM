<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\CompanyApiKey;
use App\Models\WebhookEndpoint;
use App\Services\Platform\ApiKeyService;
use App\Services\Platform\WebhookDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiPlatformController extends Controller
{
    public function listApiKeys(Request $request): JsonResponse
    {
        $company = $request->user()->company;
