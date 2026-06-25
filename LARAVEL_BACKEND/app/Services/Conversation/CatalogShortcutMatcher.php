<?php

namespace App\Services\Conversation;

use App\Models\Company;
use App\Services\OrderFlowService;

/**
 * Catalog shortcut detection (exact commands vs broad keyword matching).
 */
final class CatalogShortcutMatcher
{
    public function __construct(
        protected OrderFlowService $orderFlow,
    ) {}

    /** @var array<int, string> */
    private const EXACT_COMMANDS = [
        '1', 'prices', 'price', 'catalog', 'menu', 'products', 'list', 'price list', 'product list',
    ];

    public function matchAiFirst(Company $company, string $lower, string $original): ?string
    {
        $trimmed = trim($lower);
        if (in_array($trimmed, self::EXACT_COMMANDS, true)) {
            return $this->orderFlow->formatCatalogForDisplay($company);
        }

        if ($this->isSimpleCatalogRequest($trimmed, $original)) {
            return $this->orderFlow->formatCatalogForDisplay($company);
        }

        return null;
    }

    public function matchBalanced(Company $company, string $lower): ?string
    {
        if ($lower === '1' || str_contains($lower, 'price') || str_contains($lower, 'prices') || str_contains($lower, 'how much')) {
            return $this->orderFlow->formatCatalogForDisplay($company);
        }
        if (str_contains($lower, 'catalog') || str_contains($lower, 'menu') || str_contains($lower, 'products') || str_contains($lower, 'product list') || ($lower === 'list' || str_contains($lower, 'price list'))) {
            return $this->orderFlow->formatCatalogForDisplay($company);
        }

        return null;
    }

    private function isSimpleCatalogRequest(string $lower, string $original): bool
    {
        if (str_contains($lower, '?')) {
            return false;
        }

        $words = preg_split('/\s+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 3) {
            return false;
        }

        foreach (['price', 'prices', 'catalog', 'menu', 'products', 'list'] as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }

        return mb_strlen(trim($original)) <= 12
            && preg_match('/^(show|send|give)\s+(me\s+)?(the\s+)?(price|prices|catalog|menu)/i', $lower);
    }
}
