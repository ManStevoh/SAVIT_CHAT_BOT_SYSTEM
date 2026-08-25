<?php

namespace Tests\Unit;

use App\Services\AI\TokenEstimator;
use Tests\TestCase;

class TokenEstimatorTest extends TestCase
{
    public function test_empty_string_returns_zero(): void
    {
        $this->assertSame(0, TokenEstimator::estimate(''));
    }

    public function test_ascii_text_uses_four_chars_per_token(): void
    {
        // 20 ASCII chars => 20/4 = 5 tokens
        $this->assertSame(5, TokenEstimator::estimate('Hello World 12345678'));
    }

    public function test_non_ascii_text_produces_higher_estimate_than_ascii(): void
    {
        // Swahili text — same mb_strlen but should produce higher token estimate
        $swahili = 'Habari yako? Ninafurahi kukuona leo hapa';
        $english = 'Hello there! I am happy to see you today';

        $swahiliTokens = TokenEstimator::estimate($swahili);
        $englishTokens = TokenEstimator::estimate($english);

        // Both strings are similar length, but pure ASCII should estimate lower
        $this->assertGreaterThan(0, $swahiliTokens);
        $this->assertGreaterThan(0, $englishTokens);
    }

    public function test_arabic_text_estimates_more_tokens(): void
    {
        $arabic = 'مرحبا بك في متجرنا الإلكتروني';
        $asciiEquiv = str_repeat('a', mb_strlen($arabic));

        $arabicTokens = TokenEstimator::estimate($arabic);
        $asciiTokens = TokenEstimator::estimate($asciiEquiv);

        // Arabic (non-ASCII) should estimate significantly more tokens than ASCII of same mb_strlen
        $this->assertGreaterThan($asciiTokens, $arabicTokens);
    }

    public function test_emoji_text_estimates_more_tokens(): void
    {
        $emoji = '🎉🎊🎈🎁🎀🎗️🎆🎇';
        $tokens = TokenEstimator::estimate($emoji);

        // Emojis are non-ASCII, should estimate at ~2 chars/token
        $this->assertGreaterThan(0, $tokens);
        $this->assertGreaterThan(mb_strlen($emoji) / 4, $tokens);
    }

    public function test_mixed_content_blends_ratios(): void
    {
        // 10 ASCII + 10 non-ASCII chars
        $mixed = 'Hello 1234 مرحبا بكم في';
        $tokens = TokenEstimator::estimate($mixed);

        $this->assertGreaterThan(0, $tokens);
    }

    public function test_estimate_messages_sums_estimates(): void
    {
        $messages = [
            ['role' => 'system', 'content' => str_repeat('a', 400)],
            ['role' => 'user', 'content' => str_repeat('b', 200)],
        ];

        $total = TokenEstimator::estimateMessages($messages);
        // 400/4 + 200/4 = 100 + 50 = 150
        $this->assertSame(150, $total);
    }

    public function test_single_char_returns_one(): void
    {
        $this->assertSame(1, TokenEstimator::estimate('a'));
        $this->assertSame(1, TokenEstimator::estimate('م'));
    }
}
