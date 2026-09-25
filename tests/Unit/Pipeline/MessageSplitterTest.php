<?php

namespace Tests\Unit\Pipeline;

use App\Services\Meta\MessageSplitter;
use PHPUnit\Framework\TestCase;

class MessageSplitterTest extends TestCase
{
    private MessageSplitter $splitter;

    protected function setUp(): void
    {
        $this->splitter = new MessageSplitter;
    }

    public function test_short_text_is_untouched(): void
    {
        $this->assertSame(['Hello there.'], $this->splitter->split('  Hello there. ', 100));
        $this->assertSame([], $this->splitter->split('   ', 100));
    }

    public function test_prefers_sentence_boundaries(): void
    {
        $chunks = $this->splitter->split('First sentence is here. Second sentence is here. Third one.', 50);

        $this->assertSame(['First sentence is here. Second sentence is here.', 'Third one.'], $chunks);
    }

    public function test_falls_back_to_word_boundaries_and_keeps_all_words(): void
    {
        $text = trim(str_repeat('lorem ipsum dolor ', 40));
        $chunks = $this->splitter->split($text, 100);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(100, mb_strlen($chunk));
        }
        $this->assertSame($text, implode(' ', $chunks));
    }

    public function test_hard_cuts_unbroken_text(): void
    {
        $chunks = $this->splitter->split(str_repeat('x', 250), 100);

        $this->assertSame([100, 100, 50], array_map('strlen', $chunks));
    }

    public function test_byte_limit_is_multibyte_safe(): void
    {
        $text = str_repeat('مرحبا بك في متجرنا ', 30).str_repeat('😀', 100);
        $chunks = $this->splitter->split($text, 200, bytes: true);

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(200, strlen($chunk));
            $this->assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
        $this->assertSame(
            preg_replace('/\s+/u', '', $text),
            preg_replace('/\s+/u', '', implode('', $chunks)),
        );
    }
}
