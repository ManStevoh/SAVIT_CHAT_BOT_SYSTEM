<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\CompanyPolicyRule;
use App\Services\Platform\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PolicyRuleController extends Controller
{
