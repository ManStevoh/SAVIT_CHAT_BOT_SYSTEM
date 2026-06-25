<?php

namespace App\Services\Conversation;

use App\Models\Company;

/**
 * Detects and formats greeting-style WhatsApp messages.
 */
final class ConversationGreetingService
{
    public const QUICK_MENU_SUFFIX = "\n\nReply with: 1. Prices  2. Order  3. Talk to agent";

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

    public function buildOpening(Company $company, ?string $customerName): string
    {
        $settings = $company->settings;
        $greeting = $settings?->ai_greeting;
        if ($greeting) {
            return $this->appendQuickMenu($greeting);
        }

        $safeName = $this->sanitizeName($customerName);
        $default = 'Hello'.($safeName !== '' ? " {$safeName}" : '').'! Thanks for reaching out. How can we help you today?';

        return $this->appendQuickMenu($default);
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
}
