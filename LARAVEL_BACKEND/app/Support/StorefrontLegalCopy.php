<?php

namespace App\Support;

final class StorefrontLegalCopy
{
    public static function defaultTerms(string $storeName): string
    {
        $name = trim($storeName) !== '' ? trim($storeName) : 'this store';

        return "By creating an account or placing an order with {$name}, you agree that:\n\n"
            ."1. You are buying from {$name}, not from RelayIQ.\n"
            ."2. Prices, taxes, and delivery or download details are as shown at checkout.\n"
            ."3. Digital products (files, links, or license keys) are delivered to the email you provide after payment is confirmed.\n"
            ."4. Refunds, replacements, and support are handled by {$name}.\n\n"
            ."The store owner can replace this text with their own terms in Storefront settings.";
    }

    public static function defaultAbout(string $storeName): string
    {
        $name = trim($storeName) !== '' ? trim($storeName) : 'this store';

        return "Welcome to {$name}. Browse the catalog, add items to your cart, and check out when you are ready. "
            ."Questions about an order or a product? Use WhatsApp or the track-order page and {$name} will help you directly.";
    }
}
