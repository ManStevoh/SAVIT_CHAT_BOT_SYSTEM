<?php

namespace App\Services\Growth;

class ContentTagger
{
    /**
     * @return array<int, string>
     */
    public static function inferTags(string $content, ?string $contentType = null): array
    {
        $tags = [];
        $lower = strtolower($content);

        $toneMap = [
            'testimonial' => ['testimonial', 'review', 'customer said', 'loved', 'recommend'],
            'promo' => ['sale', 'discount', '% off', 'offer', 'limited time', 'promo'],
            'product_showcase' => ['new arrival', 'now available', 'shop', 'order', 'price', 'kes'],
            'educational' => ['how to', 'tips', 'guide', 'learn', 'did you know'],
            'urgency' => ['today only', 'hurry', 'last chance', 'ending soon', 'don\'t miss'],
            'social_proof' => ['join', 'customers', 'trusted', 'rated', 'popular'],
        ];

        foreach ($toneMap as $tag => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        if ($contentType && $contentType !== 'text') {
            $tags[] = $contentType;
        }

        if (str_contains($lower, 'whatsapp') || str_contains($lower, 'message us') || str_contains($lower, 'dm us')) {
            $tags[] = 'whatsapp_cta';
        }

        return array_values(array_unique($tags ?: ['general']));
    }
}
