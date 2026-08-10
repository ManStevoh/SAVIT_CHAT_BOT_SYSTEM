<?php

namespace App\Services\AI;

use App\DTOs\ConversationState;
use App\Models\Company;
use App\Models\Product;
use App\Services\Conversation\ConversationGreetingService;
use App\Services\Conversation\FaqMatchingService;
use App\Support\MoneyFormatter;

final class ReadOnlyToolExecutor
{
    public function __construct(
        private ConversationGreetingService $greetingService,
        private FaqMatchingService $faqMatchingService,
    ) {}

    /**
     * Define the array of OpenAI tool definitions available to the Read-Only Secondary AI.
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search active store products and catalog by keywords, product names, or categories.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Search query or product name e.g. "books", "earphones", "wireless"',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_info',
                    'description' => 'Fetch store location, business address, opening hours, contact details, and online shop URL.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_faq',
                    'description' => 'Search store FAQs for policies regarding shipping, returns, payment options, and general store rules.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => [
                                'type' => 'string',
                                'description' => 'FAQ query or topic e.g. "delivery time", "payment methods", "returns"',
                            ],
                        ],
                        'required' => ['question'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_cart',
                    'description' => 'View items currently in the customer\'s cart and total price.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => new \stdClass(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a read-only tool call safely without mutating DB state.
     */
    public function executeTool(string $toolName, array $arguments, Company $company, ConversationState $state): array
    {
        return match ($toolName) {
            'search_products' => $this->searchProducts($company, (string) ($arguments['query'] ?? '')),
            'get_store_info' => $this->getStoreInfo($company, $state),
            'get_faq' => $this->getFaq($company, (string) ($arguments['question'] ?? '')),
            'get_current_cart' => $this->getCurrentCart($company, $state),
            default => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function searchProducts(Company $company, string $query): array
    {
        $q = mb_strtolower(trim($query));
        $products = Product::where('company_id', $company->id)
            ->where('status', 'active')
            ->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(category) LIKE ?', ["%{$q}%"]);
            })
            ->take(5)
            ->get();

        if ($products->isEmpty()) {
            // Fall back to top products if specific search returns empty
            $products = Product::where('company_id', $company->id)
                ->where('status', 'active')
                ->take(5)
                ->get();
        }

        $results = [];
        foreach ($products as $p) {
            $variants = $p->activeVariants()->get()->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->label ?? $v->name,
                'price' => MoneyFormatter::format((float) ($v->price ?? $p->price), $company->currency ?? 'USD'),
            ])->toArray();

            $results[] = [
                'id' => $p->id,
                'name' => $p->name,
                'price' => MoneyFormatter::format((float) $p->price, $company->currency ?? 'USD'),
                'description' => mb_substr(strip_tags((string) ($p->description ?? '')), 0, 150),
                'variants' => $variants,
            ];
        }

        return ['query' => $query, 'found_count' => count($results), 'products' => $results];
    }

    private function getStoreInfo(Company $company, ConversationState $state): array
    {
        $settings = $company->settings;
        $storeUrl = $this->greetingService->publicStorefrontUrl($company, null, $state->customerPhone);

        return [
            'store_name' => $company->name ?? 'Store',
            'business_address' => $settings?->business_address ?? $settings?->address ?? 'Online Store',
            'opening_hours' => $settings?->opening_hours ?? 'Mon-Sat 8:00 AM - 6:00 PM',
            'currency' => $company->currency ?? 'USD',
            'online_shop_url' => $storeUrl,
        ];
    }

    private function getFaq(Company $company, string $question): array
    {
        $match = $this->faqMatchingService->matchBest($company, $question, $question);
        if ($match !== null && ! empty($match['answer'])) {
            return [
                'question' => $question,
                'matched_faq' => $match['question'] ?? $question,
                'answer' => $match['answer'],
            ];
        }

        return [
            'question' => $question,
            'answer' => 'No specific FAQ found. Please visit our online storefront or ask customer support.',
        ];
    }

    private function getCurrentCart(Company $company, ConversationState $state): array
    {
        if (! $state->hasItems()) {
            return ['status' => 'empty', 'items' => [], 'total' => '$0.00'];
        }

        $items = [];
        foreach ($state->cartItems as $item) {
            $items[] = [
                'name' => $item['name'] ?? 'Item',
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => MoneyFormatter::format((float) ($item['price'] ?? 0), $company->currency ?? 'USD'),
            ];
        }

        return [
            'status' => 'has_items',
            'items' => $items,
            'total' => MoneyFormatter::format((float) $state->calculateCartTotal(), $company->currency ?? 'USD'),
            'current_step' => $state->step->value,
        ];
    }
}
