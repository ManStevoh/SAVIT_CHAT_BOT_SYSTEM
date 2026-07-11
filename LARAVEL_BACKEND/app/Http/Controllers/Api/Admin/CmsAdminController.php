<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\CmsSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CmsAdminController extends Controller
{
    public function pages(): JsonResponse
    {
        $pages = CmsPage::orderBy('id')->get()->map(fn (CmsPage $p) => [
            'id' => (string) $p->id,
            'slug' => $p->slug,
