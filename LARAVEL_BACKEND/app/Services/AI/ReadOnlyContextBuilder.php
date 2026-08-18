<?php

namespace App\Services\AI;

use App\DTOs\ConversationState;
use App\Models\Company;
use App\Models\Product;
use App\Services\Conversation\ConversationGreetingService;
use App\Support\MoneyFormatter;

final class ReadOnlyContextBuilder
{
    public function __construct(
        private ConversationGreetingService $greetingService
    ) {}

    public function build(
        Company $company,
        ConversationState $state,
        array $candidateContext = []
    ): array {
        $settings = $company->settings;
        $storeUrl = $this->greetingService->publicStorefrontUrl($company, null, $state->customerPhone);

        $address = $settings?->business_address ?? $settings?->address ?? 'Online Store';
        $hours = $settings?->opening_hours ?? 'Mon-Sat 8:00 AM - 6:00 PM';

        // Cart summary snippet
        $cartSummary = 'Empty';
        if ($state->hasItems()) {
            $itemsList = [];
            foreach ($state->cartItems as $item) {
                $name = $item['name'] ?? 'Item';
                $qty = (int) ($item['quantity'] ?? 1);
                $price = MoneyFormatter::format((float) ($item['price'] ?? 0), $company->currency ?? 'USD');
                $itemsList[] = "{$qty}x {$name} ({$price})";
            }
            $cartSummary = implode(', ', $itemsList) . " (Total: " . MoneyFormatter::format((float) $state->calculateCartTotal(), $company->currency ?? 'USD') . ")";
        }

        // Candidate products snippet (top candidates or top active products)
        $rawCandidates = $candidateContext['candidates'] ?? [];
        $candidates = [];

        if (! empty($rawCandidates) && is_array($rawCandidates)) {
            foreach (array_slice($rawCandidates, 0, 5) as $c) {
                if (is_array($c)) {
                    $candidates[] = [
                        'name' => $c['name'] ?? '',
                        'price' => $c['price'] ?? '',
                        'desc' => mb_substr(strip_tags((string) ($c['description'] ?? '')), 0, 100),
                    ];
                }
            }
        }

        if (empty($candidates)) {
            $prods = Product::where('company_id', $company->id)
                ->where('status', 'active')
                ->take(5)
                ->get();

            foreach ($prods as $p) {
                $candidates[] = [
                    'name' => $p->name,
                    'price' => MoneyFormatter::format((float) $p->price, $company->currency ?? 'USD'),
                    'desc' => mb_substr(strip_tags((string) ($p->description ?? '')), 0, 100),
                ];
            }
        }

        // Verified customer orders context (anti-hallucination fact injection)
        $orderTrackingService = app(\App\Services\Domain\OrderTrackingService::class);
        $verifiedOrders = $orderTrackingService->getLlmOrderContext($company, $state->customerPhone, $state->chatId);

        return [
            'store' => [
                'name' => $company->name ?? 'Store',
                'address' => $address,
                'hours' => $hours,
                'url' => $storeUrl,
            ],
            'current_flow' => [
                'step' => $state->step->value,
                'cart_summary' => $cartSummary,
            ],
            'verified_orders' => $verifiedOrders,
            'candidate_products' => $candidates,
        ];
    }
}
