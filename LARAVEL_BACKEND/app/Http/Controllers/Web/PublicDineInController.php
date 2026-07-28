<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DineInTable;
use App\Services\Storefront\StorefrontService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicDineInController extends Controller
{
    public function __construct(protected StorefrontService $storefront) {}

    public function byToken(string $qrToken): RedirectResponse|Response
    {
        $table = DineInTable::query()
            ->with('company')
            ->where('qr_token', $qrToken)
            ->where('is_active', true)
            ->firstOrFail();

        $company = $table->company;
        if ($company?->storefront_enabled && $company->store_slug) {
            return redirect()->route('storefront.table', [
                'slug' => $company->store_slug,
                'qrToken' => $table->qr_token,
            ]);
        }

        return Inertia::render('store/dine-in', [
            'company' => [
                'name' => $company->name,
                'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
            ],
            'table' => [
                'id' => (string) $table->id,
                'name' => $table->name,
                'qrToken' => $table->qr_token,
            ],
            'products' => $company ? $this->storefront->catalog($company) : [],
            'slug' => $company?->store_slug,
        ]);
    }

    public function storeTable(string $slug, string $qrToken, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $table = DineInTable::query()
            ->where('company_id', $company->id)
            ->where('qr_token', $qrToken)
            ->where('is_active', true)
            ->firstOrFail();

        return Inertia::render('store/dine-in', [
            'company' => [
                'name' => $company->name,
                'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
            ],
            'table' => [
                'id' => (string) $table->id,
                'name' => $table->name,
                'qrToken' => $table->qr_token,
            ],
            'products' => $this->storefront->catalog($company),
            'slug' => $slug,
        ]);
    }
}
