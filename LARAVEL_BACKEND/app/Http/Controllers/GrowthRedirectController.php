<?php

namespace App\Http\Controllers;

use App\Models\AttributionLink;
use App\Services\Growth\AttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GrowthRedirectController extends Controller
{
    public function redirect(string $slug, Request $request, AttributionService $attribution): RedirectResponse
    {
        $link = AttributionLink::where('slug', $slug)->firstOrFail();
        $attribution->recordClick($link, $request);

        return redirect()->away($link->destination_url);
    }
}
