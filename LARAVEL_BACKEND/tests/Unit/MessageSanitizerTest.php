<?php

namespace Tests\Unit;

use App\Support\MessageSanitizer;
use Tests\TestCase;

class MessageSanitizerTest extends TestCase
{
    public function test_strips_control_characters(): void
    {
        $dirty = "Hello\x00\x01\x02 World\x7F";
        $clean = MessageSanitizer::sanitize($dirty);

        $this->assertSame('Hello World', $clean);
    }

    public function test_preserves_unicode(): void
    {
        $unicode = 'مرحبا 你好 こんにちは 🎉';
        $clean = MessageSanitizer::sanitize($unicode);

        $this->assertSame($unicode, $clean);
    }

    public function test_caps_length_at_max(): void
    {
        $long = str_repeat('a', 5000);
        $clean = MessageSanitizer::sanitize($long);

        $this->assertSame(4000, mb_strlen($clean));
    }

    public function test_custom_max_length(): void
    {
        $long = str_repeat('a', 500);
        $clean = MessageSanitizer::sanitize($long, 100);

        $this->assertSame(100, mb_strlen($clean));
    }

    public function test_trims_whitespace(): void
    {
        $padded = '   Hello World   ';
        $clean = MessageSanitizer::sanitize($padded);

        $this->assertSame('Hello World', $clean);
    }

    public function test_preserves_newlines_and_tabs(): void
    {
        // \n (0x0A) and \r (0x0D) and \t (0x09) should be preserved (they're not in the control char range stripped)
        $text = "Line 1\nLine 2\tTabbed";
        $clean = MessageSanitizer::sanitize($text);

        $this->assertStringContainsString("\n", $clean);
        $this->assertStringContainsString("\t", $clean);
    }

    public function test_empty_string(): void
    {
        $this->assertSame('', MessageSanitizer::sanitize(''));
    }
}
