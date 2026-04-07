<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the system prompt for the AI assistant from company context.
 * Designed for extension: add more sections (e.g. policies, feedback) without changing callers.
 */
class SystemPromptBuilder
{
    private const MAX_FAQS_IN_PROMPT = 15;

    private const MAX_PRODUCTS_IN_PROMPT = 20;

    /**
     * @param  array<int, array{question: string, answer: string}>  $learningSamples  Optional past Q&A to improve consistency
     */
    public function build(Company $company, array $learningSamples = []): string
    {
        $settings = $company->settings;
        $tone = $settings?->ai_tone ?? 'friendly and professional';
        $name = $company->name;

        $parts = [
            "You are a helpful customer service assistant for the business: {$name}.",
            "Reply in a {$tone} tone. Keep replies concise (1-3 short paragraphs).",
            "Do not invent prices or product names. Use the product list from context when relevant. If the customer names products and quantities to buy, acknowledge the order intent; do not only redirect them to type 'prices' or 'catalog'.",
        ];

        $this->appendKnowledgeBase($company, $parts);
        $this->appendProducts($company, $parts);
        $this->appendLearningSamples($learningSamples, $parts);

        return implode("\n", $parts);
    }

    private function appendKnowledgeBase(Company $company, array &$parts): void
    {
        $faqs = Faq::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->limit(self::MAX_FAQS_IN_PROMPT)
            ->get();

        if ($faqs->isEmpty()) {
            return;
        }

        $parts[] = "\nKnowledge base (use when relevant):";
        foreach ($faqs as $faq) {
            $parts[] = "Q: {$faq->question}\nA: {$faq->answer}";
        }
    }

    private function appendProducts(Company $company, array &$parts): void
    {
        $products = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->with([
                'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id'),
                'variants' => fn ($q) => $q
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with(['images' => fn ($iq) => $iq->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id')]),
            ])
            ->orderBy('name')
            ->limit(self::MAX_PRODUCTS_IN_PROMPT)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $company->loadMissing('settings');
        $ccy = $company->settings?->displayCurrencyCode() ?? 'USD';
        $parts[] = "\nProducts (do not invent; refer to catalog if they ask). All prices are in {$ccy}. Customers can order by number in WhatsApp:";
        foreach ($products as $p) {
            if ($p->variants->where('status', 'active')->isNotEmpty()) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $parts[] = '- '.$p->name.' (options; from '.MoneyFormatter::format($min, $ccy)."){$productImageSuffix}:";
                foreach ($p->variants->where('status', 'active')->take(8) as $v) {
                    $variantImage = $this->resolvePrimaryImageUrl($v->images) ?? $productImage;
                    $variantImageSuffix = $variantImage ? " [image: {$variantImage}]" : '';
                    $parts[] = '  • '.$v->label.': '.MoneyFormatter::format((float) $v->price, $ccy).$variantImageSuffix;
                }
            } else {
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $parts[] = '- '.$p->name.': '.MoneyFormatter::format((float) $p->price, $ccy).$productImageSuffix;
            }
        }
    }

    private function resolvePrimaryImageUrl($images): ?string
    {
        if (! $images || $images->isEmpty()) {
            return null;
        }

        /** @var ProductImage|null $image */
        $image = $images->firstWhere('is_primary', true) ?? $images->first();

        return $image ? Storage::url($image->path) : null;
    }

    /**
     * @param  array<int, array{question: string, answer: string}>  $samples
     */
    private function appendLearningSamples(array $samples, array &$parts): void
    {
        if ($samples === []) {
            return;
        }

        $parts[] = "\nRecent similar exchanges (use as style/context reference when relevant):";
        foreach ($samples as $s) {
            $q = $s['question'] ?? '';
            $a = $s['answer'] ?? '';
            if ($q !== '' && $a !== '') {
                $parts[] = "Q: {$q}\nA: {$a}";
            }
        }
    }
}
