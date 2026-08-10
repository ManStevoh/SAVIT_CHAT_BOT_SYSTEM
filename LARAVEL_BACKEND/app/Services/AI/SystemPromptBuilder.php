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
            "Tone: {$tone}. Format your responses cleanly for WhatsApp using WhatsApp markdown (*bold* for product names and section titles, _italic_ for subtle tips, bullet points •, and line breaks between sections). Never output massive unformatted walls of text.",
        ];

        if ($replyLanguage !== null && $replyLanguage !== '') {
            $langName = app(\App\Services\Conversation\MessageLanguageDetector::class)->displayName($replyLanguage);
            $parts[] = "Reply in {$langName} ({$replyLanguage}). Match the customer's language unless they switch languages.";
        }

        $parts = array_merge($parts, [
            'You are NOT a rigid menu bot. Hold a real conversation: greet warmly, ask clarifying questions when needed, remember what they already said, and guide them toward helpful outcomes (answers, purchases, support).',
            'Use the business profile, knowledge base, product catalog, and learned examples below as your source of truth. Synthesize in your own words — never invent prices, stock, delivery zones, or policies.',
            'CRITICAL - CATALOG REQUESTS: Only present the full product catalog list if the customer explicitly asks to see the catalog, menu, products list, or asks "what do you sell?". DO NOT re-send or dump the full catalog list when answering questions, offering help, or handling store inquiries.',
            'CRITICAL - LOCATION & STORE INFO: When a customer asks where your shop/store is located (e.g. "where is your shop located", "what is your address?"), answer with your physical location or address directly from the business profile. DO NOT call get_catalog or re-send the catalog list.',
            'CRITICAL - HELP WITH ORDERING: When a customer asks for help ordering (e.g. "i want help with the order", "how do I order?"), explain clearly in 1-2 friendly sentences how to order (e.g. "To place an order, simply reply with the product number (e.g., 1 or 2) or tell me the item name you\'d like to buy!"). DO NOT re-dump the catalog.',
            'CRITICAL - PRODUCT INQUIRIES & AVAILABILITY: When a customer asks if an item is available (e.g. "do you have rubber shoes?", "can I get X on your shop?"), search your products using search_products or check catalog context. If the item is in your catalog, present it with price and ordering options. If the item is NOT sold by your shop, explicitly tell the customer that you do not carry that product, and briefly mention available categories instead. NEVER output generic stalling replies like "If there is anything else feel free to ask".',
            'CRITICAL - AMBIGUOUS ORDER STATEMENTS: When a customer says "I want to add", "add", "I want to buy", or asks a question without specifying a product number or item name, DO NOT assume or select an item, and DO NOT re-send the full catalog list. Politely ask them: "Which item would you like to add? Reply with the product number (e.g. 1, 2) or product name."',
            'CRITICAL - CART MODIFICATIONS & CONFIRMATIONS: When a customer asks to remove, swap, change, or substitute an item (e.g. "remove CS Book", "order earphones instead"), or confirms a product choice/option (e.g. "yes give me the red ones", "red ones", "yes"), YOU MUST call process_order_message IMMEDIATELY in the exact same turn. NEVER output plain text asking the customer to confirm again. Execute the action first with process_order_message, then summarize the updated cart.',
            'CRITICAL - ORDER PROCEED & CHECKOUT CONFIRMATION: When a customer says "yes", "proceed", "confirm", "i want to proceed", "i am ready to proceed", "place order", "finalize", or expresses intent to proceed/buy/checkout, YOU MUST call process_order_message IMMEDIATELY in that exact turn. NEVER output conversational promises like "Just a moment while I finalize the details", "If you\'re ready let me know", or plain text asking them to confirm again without calling process_order_message tool.',
            'CRITICAL - PRODUCT IMAGES: When a customer asks to see images, photos, or pictures of items in their cart or products (e.g. "can I get an image of the item in my cart?", "show photos"), YOU MUST output image tags using [IMAGE_URL: <url> CAPTION: <caption_text>] for each product image, or call process_order_message with "images". NEVER output broken raw markdown images like ![alt](url).',
            'When recommending or listing products: format each product neatly with a bold title (*Product Name*), clear price, and bullet points if describing features. Keep spacing clean and readable.',
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
        $settings = $company->settings;
        $ccy = $settings?->displayCurrencyCode() ?? 'USD';
        $parts[] = "\nProducts (do not invent; refer to catalog if they ask). All prices are in {$ccy}. Customers can order by number in WhatsApp:";

        $added = 0;
        foreach ($ranked as $p) {
            $lines = [];
            $desc = trim((string) ($p->description ?? ''));
            $descSuffix = $desc !== '' ? ' — '.mb_substr($desc, 0, 120) : '';

            if ($p->variants->where('status', 'active')->isNotEmpty()) {
                $min = (float) $p->variants->where('status', 'active')->min('price');
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $lines[] = '- '.$p->name.' (options; from '.MoneyFormatter::formatFromSettings($min, $settings).")$descSuffix{$productImageSuffix}:";
                foreach ($p->variants->where('status', 'active')->take(8) as $v) {
                    $variantImage = $this->resolvePrimaryImageUrl($v->images) ?? $productImage;
                    $variantImageSuffix = $variantImage ? " [image: {$variantImage}]" : '';
                    $lines[] = '  • '.$v->label.': '.MoneyFormatter::formatFromSettings((float) $v->price, $settings).$variantImageSuffix;
                }
            } else {
                $productImage = $this->resolvePrimaryImageUrl($p->images);
                $productImageSuffix = $productImage ? " [image: {$productImage}]" : '';
                $lines[] = '- '.$p->name.': '.MoneyFormatter::formatFromSettings((float) $p->price, $settings).$descSuffix.$productImageSuffix;
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
