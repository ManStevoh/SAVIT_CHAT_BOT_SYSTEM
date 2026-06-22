<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingFaq;
use App\Models\PlatformSetting;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;

/**
 * Public landing page data (no auth).
 * GET /api/landing → { testimonials, trustedCompanies, faqs }
 */
class LandingController extends Controller
{
    public function index(): JsonResponse
    {
        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'role', 'content', 'rating'])
            ->map(fn ($t) => [
                'id' => (string) $t->id,
                'name' => $t->name,
                'role' => $t->role ?? '',
                'content' => $t->content,
                'rating' => (int) $t->rating,
            ])
            ->values()
            ->all();

        $settings = PlatformSetting::first();
        $trustedCompanies = $settings && ! empty($settings->landing_trusted_companies)
            ? (array) $settings->landing_trusted_companies
            : [];

        $faqs = LandingFaq::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'question', 'answer'])
            ->map(fn ($f) => [
                'id' => (string) $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
            ])
            ->values()
            ->all();

        return response()->json([
            'testimonials' => $testimonials,
            'trustedCompanies' => $trustedCompanies,
            'faqs' => $faqs,
        ]);
    }
}
