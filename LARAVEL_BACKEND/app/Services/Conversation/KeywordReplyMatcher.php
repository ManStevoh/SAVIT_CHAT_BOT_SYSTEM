<?php

namespace App\Services\Conversation;

use App\Models\Company;

/**
 * Legacy keyword heuristics used only in balanced reply mode.
 */
final class KeywordReplyMatcher
{
    public function match(Company $company, string $lower): ?string
    {
        if (str_contains($lower, 'order') || str_contains($lower, 'place order')) {
            return 'You can place an order by typing "order" or "2", then reply with product numbers from the list and quantity when asked. You can also use text like "2 x Product Name".';
        }

        if ($this->looksLikeOrderStatusQuestion($lower)) {
            return null;
        }

        if (str_contains($lower, 'location')
            || str_contains($lower, 'address')
            || $this->looksLikeShopLocationQuestion($lower)) {
            $addr = $company->address ?? null;
            if ($addr) {
                return "We're located at: {$addr}";
            }

            return 'Please contact us for our address.';
        }

        if (str_contains($lower, 'hour') || str_contains($lower, 'open') || str_contains($lower, 'when are you')) {
            $wh = $company->settings?->working_hours;
            if ($wh && is_array($wh)) {
                $lines = ['Our hours:'];
                foreach ($wh as $day => $hours) {
                    if ($hours && is_string($hours)) {
                        $lines[] = ucfirst($day).': '.$hours;
                    }
                }

                return implode("\n", $lines);
            }

            return 'Please contact us for our opening hours.';
        }

        if (str_contains($lower, 'delivery') || str_contains($lower, 'shipping')) {
            return 'We offer delivery. For details and delivery areas, type "order" to start an order or ask a specific question and we\'ll answer from our business info.';
        }

        return null;
    }

    private function looksLikeOrderStatusQuestion(string $lower): bool
    {
        if (! str_contains($lower, 'order')) {
            return false;
        }

        return str_contains($lower, 'where is')
            || str_contains($lower, 'where\'s')
            || str_contains($lower, 'wheres')
            || str_contains($lower, 'status')
            || str_contains($lower, 'track')
            || str_contains($lower, 'tracking')
            || str_contains($lower, 'my order')
            || str_contains($lower, 'order number');
    }

    private function looksLikeShopLocationQuestion(string $lower): bool
    {
        if (str_contains($lower, 'where') && (str_contains($lower, 'shop') || str_contains($lower, 'store') || str_contains($lower, 'located') || str_contains($lower, 'find you') || str_contains($lower, 'your address'))) {
            return true;
        }

        return str_contains($lower, 'where are you') || str_contains($lower, 'where is the');
    }
}
