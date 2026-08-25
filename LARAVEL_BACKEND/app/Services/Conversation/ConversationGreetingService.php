<?php

namespace App\Services\Conversation;

use App\Models\Chat;
use App\Models\Company;

/**
 * Detects and formats greeting-style WhatsApp messages.
 */
final class ConversationGreetingService
{
    public const QUICK_MENU_SUFFIX = "\n\nReply with: 1. Prices  2. Track Order  3. Talk to agent";

    /** @var array<int, string> Longest first for prefix stripping. */
    private const GREETING_PHRASES = [
        'good morning', 'good afternoon', 'good evening',
        'marhaba', 'salam', 'hello', 'hola', 'hey', 'hi',
    ];

    public function isPureGreeting(string $message): bool
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return true;
        }

        $normalized = trim(mb_strtolower($trimmed), " \t\n\r\0\x0B!?.");
        foreach (self::GREETING_PHRASES as $greeting) {
            if ($normalized === $greeting) {
                return true;
            }
        }

        return trim($this->stripLeadingGreeting($message)) === '';
    }

    public function stripLeadingGreeting(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return '';
        }

        $lower = mb_strtolower($trimmed);
        foreach (self::GREETING_PHRASES as $greeting) {
            if ($lower === $greeting) {
                return '';
            }
            foreach ([' ', ',', '!', '.'] as $sep) {
                $prefix = $greeting.$sep;
                if (str_starts_with($lower, $prefix)) {
                    $remainder = trim(mb_substr($trimmed, mb_strlen($greeting.$sep)));

                    return ltrim($remainder, ",!. \t");
                }
            }
        }

        return $trimmed;
    }

    public function publicStorefrontUrl(Company $company, ?Chat $chat = null, ?string $customerPhone = null): ?string
    {
        $slug = $company->store_slug ?: \Illuminate\Support\Str::slug($company->name);
        if (! $slug) {
            $slug = 'store-'.$company->id;
        }

        $phone = $chat ? $chat->customer_phone : ($customerPhone ? preg_replace('/\D+/', '', $customerPhone) : null);
        $baseUrl = url('/s/'.$slug);

        if (! $phone) {
            return $baseUrl;
        }

        if ($chat) {
            $session = app(\App\Services\Storefront\StorefrontService::class)->syncChatCartToStorefrontSession($company, $chat);

            return $baseUrl.'?phone='.urlencode($phone).'&token='.$session->session_token;
        }

        return $baseUrl.'?phone='.urlencode($phone);
    }

    public function buildOpening(Company $company, ?string $customerName = null, ?Chat $chat = null, ?string $customerPhone = null): string
    {
        $settings = $company->settings;
        $greeting = $settings?->ai_greeting;
        if (! $greeting || $this->looksLikeSystemPrompt($greeting)) {
            $safeName = $this->sanitizeName($customerName);
            $greeting = 'Hello'.($safeName !== '' ? " {$safeName}" : '').'! Thanks for reaching out. How can we help you today?';
        }

        $baseText = $this->appendQuickMenu($greeting);

        $storeUrl = $this->publicStorefrontUrl($company, $chat, $customerPhone);
        if ($storeUrl && ! str_contains($baseText, '/s/')) {
            $baseText .= "\n\n🛍️ *Shop Online:*\n{$storeUrl}";
        }

        return $baseText;
    }

    public function appendQuickMenu(string $text): string
    {
        $menu = self::QUICK_MENU_SUFFIX;
        if (str_contains($text, $menu)) {
            return $text;
        }

        return rtrim($text).$menu;
    }

    public function sanitizeName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($name)) ?? '';

        return mb_substr($clean, 0, 80);
    }

    /**
     * Detect system-prompt-like text that should never be sent as a customer greeting.
     */
    private function looksLikeSystemPrompt(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        // "You are a ... assistant/agent/bot/AI"
        if (preg_match('/\byou are (?:a |an |the )?\w*\s*(?:assistant|agent|bot|ai|model|helper)\b/iu', $lower)) {
            return true;
        }

        // "Be polite, professional" — instruction-style phrasing
        if (preg_match('/\b(?:be polite|be professional|be helpful|respond as|act as|behave as)\b/iu', $lower)) {
            return true;
        }

        return false;
    }
}
