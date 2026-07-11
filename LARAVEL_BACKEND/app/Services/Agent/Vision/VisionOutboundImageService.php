<?php

namespace App\Services\Agent\Vision;

use App\Models\Company;
use App\Models\MessageVisionAnalysis;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Resolve catalog product preview for outbound WhatsApp image after vision match.
 */
final class VisionOutboundImageService
{
    /**
     * @return array{url: string, caption: string, product_id: int}|null
     */
    public function resolveProductPreview(Company $company, MessageVisionAnalysis $analysis): ?array
    {
        if (! config('agent.vision.send_product_image_on_match', true)) {
            return null;
        }

        $matches = $analysis->product_matches ?? [];
        if ($matches === []) {
            return null;
        }

        $productId = (int) ($matches[0]['product_id'] ?? 0);
        if ($productId <= 0) {
            return null;
        }

        $product = Product::query()
            ->where('company_id', $company->id)
            ->where('id', $productId)
            ->with('images')
            ->first();

        if (! $product) {
            return null;
        }

        $imageUrl = $this->resolveProductImageUrl($product);
        if ($imageUrl === null) {
            return null;
        }

        $name = (string) ($matches[0]['name'] ?? $product->name);
        $price = $product->price ? ' — '.$product->price : '';

        return [
            'url' => $imageUrl,
            'caption' => trim($name.$price),
            'product_id' => $productId,
        ];
    }

    private function resolveProductImageUrl(Product $product): ?string
    {
        $path = null;
        $primary = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        if ($primary?->path) {
            $path = $primary->path;
        } elseif ($product->image) {
            $path = $product->image;
        }

        if (! $path) {
            return null;
        }

        $relative = Storage::url($path);

        return str_starts_with($relative, 'http') ? $relative : url($relative);
    }
}
