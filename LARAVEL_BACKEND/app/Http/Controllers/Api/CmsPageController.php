<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class CmsPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::where('slug', $slug)->where('is_published', true)->first();

