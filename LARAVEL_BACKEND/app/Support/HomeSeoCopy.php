<?php

namespace App\Support;

/**
 * Public homepage SEO copy. Used when CMS still has product-speak
 * ("commerce OS", "and beyond") so a deploy can change the live H1
 * without re-seeding production CMS.
 */
final class HomeSeoCopy
{
    public static function title(): string
    {
        return 'AI Sales Agent on WhatsApp for Kenyan Businesses | RelayIQ.app';
    }

    public static function description(): string
    {
        return 'RelayIQ.app is an AI WhatsApp sales platform for African businesses. Start on the free Starter plan: storefront connected to WhatsApp, bookings, and dine-in QR. Answer in English and Kiswahili, and send M-Pesa STK on the number they already message.';
    }

    public static function h1(): string
    {
        return 'AI sales agent on WhatsApp for Kenyan businesses';
    }

    public static function lede(): string
    {
        return 'Answer customers, take orders, and send M-Pesa prompts on the WhatsApp number they already use — then hand off to your team when a human should close.';
    }

    public static function shouldReplaceHero(string $title): bool
    {
        $t = mb_strtolower(trim($title));
        if ($t === '') {
            return false;
        }

        return str_contains($t, 'commerce os')
            || str_contains($t, 'and beyond')
            || str_contains($t, 'ai-powered commerce');
    }

    public static function shouldReplaceMeta(string $title, string $description): bool
    {
        $blob = mb_strtolower($title.' '.$description);

        return str_contains($blob, 'commerce os')
            || str_contains($blob, 'and beyond')
            || str_contains($blob, 'ai-powered commerce');
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function applyHero(array $content): array
    {
        $title = trim((string) ($content['title'] ?? $content['headline'] ?? ''));
        if (! self::shouldReplaceHero($title)) {
            return $content;
        }

        $content['title'] = self::h1();
        $content['headline'] = self::h1();
        $content['description'] = self::lede();
        $content['subhead'] = self::lede();

        $href = trim((string) ($content['secondaryCtaHref'] ?? ''));
        if ($href === '' || $href === '/whatsapp-ai-sales-agent') {
            if (! filled($content['secondaryCtaText'] ?? null)) {
                $content['secondaryCtaText'] = 'Kenya playbook';
            }
            $content['secondaryCtaHref'] = '/ai-sales-agent-kenya';
        }

        return $content;
    }
}
