<?php



namespace App\Http\Controllers\Api\Company;



use App\Http\Controllers\Controller;

use App\Models\InvestigationCase;
use App\Models\IntelligenceOutcome;

use App\Services\Agent\Intelligence\IntelligenceOutcomeService;

use App\Services\Agent\Intelligence\IntelligenceReasoningService;

use App\Services\Agent\Intelligence\InvestigationCaseService;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



/**

 * ABI Level 19 — Decision Intelligence API.

 */
