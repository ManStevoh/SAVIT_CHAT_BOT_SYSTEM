<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\BusinessGraphNode;
use App\Services\Agent\Graph\BusinessGraphV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessGraphController extends Controller
{
