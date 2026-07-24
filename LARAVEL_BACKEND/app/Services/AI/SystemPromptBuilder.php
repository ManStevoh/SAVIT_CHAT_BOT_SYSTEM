<?php

namespace App\Services\AI;

use App\Models\Company;
use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\KnowledgeChunk;
use App\Support\MoneyFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the system prompt for the AI assistant from company context.
 * Designed for extension: add more sections (e.g. policies, feedback) without changing callers.
 */
class SystemPromptBuilder
{
    private const MAX_FAQS_IN_PROMPT = 50;

    private const MAX_PRODUCTS_IN_PROMPT = 40;

    public function __construct(
        private AiLearningConfig $learningConfig,
        private KnowledgeChunkService $chunkService,
    ) {}

    /**
     * @param  array<int, array{question: string, answer: string}>  $learningSamples
     */
    public function build(
        Company $company,
        array $learningSamples = [],
        ?string $orderFlowContext = null,
        ?string $customerMessage = null,
        ?string $replyLanguage = null,
    ): string {
        $settings = $company->settings;
        $tone = $settings?->ai_tone ?? 'friendly, professional, and clear';
        $name = $company->name;
        $budget = $this->learningConfig->maxPromptTokens();

        $parts = [
            "You are the primary AI employee and conversation OS for {$name}. You represent the owner with customers on WhatsApp — fluent, human, confident, and accurate.",
            "Tone: {$tone}. Write natural full sentences (usually 2–6 short WhatsApp lines). Plain text only — no markdown.",
        ];

        if ($replyLanguage !== null && $replyLanguage !== '') {
            $langName = app(\App\Services\Conversation\MessageLanguageDetector::class)->displayName($replyLanguage);
            $parts[] = "Reply in {$langName} ({$replyLanguage}). Match the customer's language unless they switch languages.";
        }

        $parts = array_merge($parts, [
            'You are NOT a rigid menu bot. Hold a real conversation: greet warmly, ask clarifying questions when needed, remember what they already said, and guide them toward helpful outcomes (answers, purchases, support).',
            'Use the business profile, knowledge base, product catalog, and learned examples below as your source of truth. Synthesize in your own words — never invent prices, stock, delivery zones, or policies.',
            'When selling: understand need → recommend real catalog items with reasons → handle objections → clear next step (order, pay, or human). Be persuasive but honest.',
            'When supporting: use order history and facts; own the problem; offer a path to resolution.',
            'Remember conversation context. If they thank you or say ok, respond briefly without dumping the catalog.',
            'Prefer tools when available for live catalog, orders, payments, and memory. Never contradict tool results.',
        ]);

        $this->appendBusinessProfile($company, $parts);
        $this->appendKnowledgeBase($company, $parts, $customerMessage, $budget);
        $this->appendProducts($company, $parts, $customerMessage, $budget);
        $this->appendLearningSamples($learningSamples, $parts);

        if ($orderFlowContext !== null && trim($orderFlowContext) !== '') {
            $parts[] = "\nCurrent situation (honor this; do not contradict numbered checkout instructions unless they ask to cancel or change topic):\n".trim($orderFlowContext);
        }

        $prompt = implode("\n", $parts);

        return $this->trimToTokenBudget($prompt, $budget);
    }

    private function appendBusinessProfile(Company $company, array &$parts): void
    {
        $company->loadMissing('settings');
        $settings = $company->settings;

        $lines = [];
        $lines[] = 'Business profile (authoritative):';
        $lines[] = '- Name: '.$company->name;
        if ($company->phone) {
            $lines[] = '- Phone: '.$company->phone;
        }
        if ($company->email) {
            $lines[] = '- Email: '.$company->email;
        }
        if ($company->address) {
            $lines[] = '- Address: '.$company->address;
        }
        if ($settings?->timezone) {
            $lines[] = '- Timezone: '.$settings->timezone;
        }

        $wh = $settings?->working_hours;
        if ($wh && is_array($wh)) {
            $lines[] = '- Hours:';
            foreach ($wh as $day => $hours) {
                if ($hours && is_string($hours)) {
                    $lines[] = '  • '.ucfirst((string) $day).': '.$hours;
                }
            }
        }

        $parts[] = "\n".implode("\n", $lines);
    }

    private function appendKnowledgeBase(Company $company, array &$parts, ?string $customerMessage, int $budget): void
    {
        $faqs = Faq::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        if ($faqs->isEmpty()) {
            return;
        }

        $ranked = $this->rankByRelevance($faqs, $customerMessage, fn (Faq $faq) => $faq->question.' '.$faq->answer);
        $parts[] = "\nKnowledge base (use when relevant):";
        $added = 0;
        foreach ($ranked as $faq) {
            $block = "Q: {$faq->question}\nA: {$faq->answer}";
            if (TokenEstimator::estimate(implode("\n", $parts)."\n".$block) > $budget) {
                break;
            }
            $parts[] = $block;
            $added++;
            if ($added >= self::MAX_FAQS_IN_PROMPT) {
                break;
            }
        }
    }

    private function appendProducts(Company $company, array &$parts, ?string $customerMessage, int $budget): void
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
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $ranked = $this->rankByRelevance($products, $customerMessage, fn (Product $p) => $p->name.' '.($p->description ?? ''));
        if ($customerMessage !== null && trim($customerMessage) !== '') {
            $semanticIds = collect($this->chunkService->search(
                (int) $company->id,
                $customerMessage,
                KnowledgeChunk::SOURCE_PRODUCT,
                8,
            ))->pluck('source_id')->map(fn ($id) => (int) $id)->all();
            if ($semanticIds !== []) {
                $ranked = $ranked->sortByDesc(fn (Product $p) => in_array((int) $p->id, $semanticIds, true) ? 1 : 0)->values();
            }
        }
        $company->loadMissing('settings');
        $ccy = $company->settings?->displayCurrencyCode() ?? 'USD';
        $parts[] = "\nProducts (do not invent; refer to catalog if they ask). All prices are in {$ccy}. Customers can order by number in WhatsApp:";

        $added = 0;
        foreach ($ranked as $p) {
            $lines = [];
            if ($p->variants->where('status', 'active')->isNotEmpty()) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $lines[] = '- '.$p->name.' (options; from '.MoneyFormatter::format($min, $ccy)."){$productImageSuffix}:";
                foreach ($p->variants->where('status', 'active')->take(8) as $v) {
                    $variantImage = $this->resolvePrimaryImageUrl($v->images) ?? $productImage;
                    $variantImageSuffix = $variantImage ? " [image: {$variantImage}]" : '';
                    $lines[] = '  • '.$v->label.': '.MoneyFormatter::format((float) $v->price, $ccy).$variantImageSuffix;
                }
            } else {
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $lines[] = '- '.$p->name.': '.MoneyFormatter::format((float) $p->price, $ccy).$productImageSuffix;
            }

            $block = implode("\n", $lines);
            if (TokenEstimator::estimate(implode("\n", $parts)."\n".$block) > $budget) {
                break;
            }
            $parts[] = $block;
            $added++;
            if ($added >= self::MAX_PRODUCTS_IN_PROMPT) {
                break;
            }
        }
    }

    /**
     * @template T
     * @param  Collection<int, T>  $items
     * @param  callable(T): string  $textExtractor
     * @return Collection<int, T>
     */
    private function rankByRelevance(Collection $items, ?string $customerMessage, callable $textExtractor): Collection
    {
        if ($customerMessage === null || trim($customerMessage) === '') {
            return $items->take(self::MAX_FAQS_IN_PROMPT);
        }

        $queryWords = $this->significantWords(mb_strtolower($customerMessage));
        if ($queryWords === []) {
            return $items;
        }

        return $items->sortByDesc(function ($item) use ($textExtractor, $queryWords) {
            $textWords = $this->significantWords(mb_strtolower($textExtractor($item)));
            if ($textWords === []) {
                return 0;
            }

            return count(array_intersect($queryWords, $textWords));
        })->values();
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $text): array
    {
        $stop = ['a', 'an', 'the', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'was', 'were', 'i', 'you', 'we', 'they', 'and', 'or', 'what', 'how', 'when', 'where', 'why'];
        $tokens = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($tokens as $t) {
            $t = mb_strtolower($t);
            if (mb_strlen($t) < 2 || in_array($t, $stop, true)) {
                continue;
            }
            $out[] = $t;
        }

        return array_values(array_unique($out));
    }

    private function trimToTokenBudget(string $prompt, int $budget): string
    {
        if (TokenEstimator::estimate($prompt) <= $budget) {
            return $prompt;
        }

        $lines = explode("\n", $prompt);
        while ($lines !== [] && TokenEstimator::estimate(implode("\n", $lines)) > $budget) {
            array_pop($lines);
        }

        return implode("\n", $lines);
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
     * @param  array<int, array{id?: int, question: string, answer: string, score?: float, source?: string}>  $samples
     */
    private function appendLearningSamples(array $samples, array &$parts): void
    {
        if ($samples === []) {
            return;
        }

        $parts[] = "\nSimilar past exchanges (hybrid retrieval — prefer when relevant to the current question):";
        foreach ($samples as $s) {
            $q = $s['question'] ?? '';
            $a = $s['answer'] ?? '';
            if ($q === '' || $a === '') {
                continue;
            }
            $meta = [];
            if (isset($s['id'])) {
                $meta[] = 'id='.$s['id'];
            }
            if (isset($s['score']) && $s['score'] > 0) {
                $meta[] = 'relevance='.number_format((float) $s['score'], 2);
            }
            if (! empty($s['source'])) {
                $meta[] = 'source='.$s['source'];
            }
            $tag = $meta !== [] ? ' ['.implode(', ', $meta).']' : '';
            $parts[] = "Q{$tag}: {$q}\nA: {$a}";
        }
    }
}
