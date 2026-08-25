<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $host = strtolower(trim((string) $request->getHost()));
        $name = config('app.name', 'RelayIQ');
        $shortName = 'RelayIQ';
        $iconUrl = '/favicon-light.png?v=4';

        if ($host !== '') {
            $company = Company::query()
                ->where('custom_domain', $host)
                ->whereNotNull('custom_domain_verified_at')
                ->first();

            if ($company) {
                $name = $company->name;
                $shortName = $company->name;
                if ($company->logo) {
                    $iconUrl = asset('storage/'.$company->logo);
                }
            }
        }

        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0f172a',
            'icons' => [
                [
                    'src' => $iconUrl,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => $iconUrl,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
        ]);
    }
}
