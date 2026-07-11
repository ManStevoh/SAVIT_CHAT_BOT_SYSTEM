<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Services\AI\KnowledgeChunkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeVectorController extends Controller
{
    public function status(Request $request, KnowledgeChunkService $chunks): JsonResponse
