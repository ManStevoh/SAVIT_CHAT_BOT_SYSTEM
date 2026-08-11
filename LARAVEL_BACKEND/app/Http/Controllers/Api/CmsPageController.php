<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Cms\CmsPagePayloadBuilder;
use Illuminate\Http\JsonResponse;

class CmsPageController extends Controller
{
    public function __construct(private readonly CmsPagePayloadBuilder $payloads)
    {
    }

    public function show(string $slug): JsonResponse
    {
        $payload = $this->payloads->forSlug($slug);

        if (! $payload) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json($payload);
    }

    public function global(): JsonResponse
    {
        return $this->show('global');
    }
}
