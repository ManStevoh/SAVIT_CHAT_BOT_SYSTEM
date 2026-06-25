<?php

namespace App\Services\Conversation;

/**
 * Lightweight multilingual detection for WhatsApp messages (no external API).
 */
final class MessageLanguageDetector
{
    /** @var array<string, array<int, string>> */
    private const MARKERS = [
        'sw' => ['habari', 'asante', 'karibu', 'ndio', 'hapana', 'bei', 'mzigo', 'safari', 'nataka', 'naweza', 'shukrani', 'pole'],
        'ar' => ['مرحبا', 'شكرا', 'نعم', 'لا', 'كم', 'سعر', 'طلب', 'السلام', 'مساء', 'صباح'],
        'fr' => ['bonjour', 'merci', 'oui', 'non', 'prix', 'commande', 'livraison', 'comment', 'besoin', 'svp'],
        'es' => ['hola', 'gracias', 'precio', 'pedido', 'envío', 'necesito', 'buenos', 'buenas', 'por favor'],
        'pt' => ['olá', 'obrigado', 'preço', 'pedido', 'entrega', 'preciso', 'bom dia', 'boa tarde'],
        'en' => ['hello', 'thanks', 'thank', 'price', 'order', 'delivery', 'shipping', 'help', 'please', 'what', 'how'],
    ];

    public function detect(string $text, ?string $fallback = 'en'): string
    {
        $text = trim($text);
        if ($text === '') {
            return $fallback ?? 'en';
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return 'ar';
        }

        $lower = mb_strtolower($text);
        $scores = [];

        foreach (self::MARKERS as $lang => $words) {
            $score = 0;
            foreach ($words as $word) {
                if (str_contains($lower, $word)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$lang] = $score;
            }
        }

        if ($scores !== []) {
            arsort($scores);

            return (string) array_key_first($scores);
        }

        if (preg_match('/\b(the|and|you|your|is|are|was|were)\b/i', $text)) {
            return 'en';
        }

        return $fallback ?? 'en';
    }

    public function displayName(string $code): string
    {
        return match ($code) {
            'sw' => 'Swahili',
            'ar' => 'Arabic',
            'fr' => 'French',
            'es' => 'Spanish',
            'pt' => 'Portuguese',
            'en' => 'English',
            default => ucfirst($code),
        };
    }
}
