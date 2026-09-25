<?php

namespace Tests\Unit\Admin;

use App\Http\Requests\Admin\BotSettingRequest;
use PHPUnit\Framework\TestCase;

class FaqParserTest extends TestCase
{
    public function test_parses_q_and_a_blocks(): void
    {
        $text = "Q: Hours?\nA: 9-5\n\nq. Multi\nline question\nA: Answer\nsecond line\n\nQ: No answer\n\nnoise before";

        $this->assertSame([
            ['question' => 'Hours?', 'answer' => '9-5'],
            ['question' => "Multi\nline question", 'answer' => "Answer\nsecond line"],
        ], BotSettingRequest::parseFaqs($text));
    }

    public function test_empty_input(): void
    {
        $this->assertSame([], BotSettingRequest::parseFaqs(null));
        $this->assertSame([], BotSettingRequest::parseFaqs("   \n"));
    }

    public function test_round_trip(): void
    {
        $faqs = [['question' => 'A?', 'answer' => 'B'], ['question' => 'C?', 'answer' => "D\nE"]];

        $this->assertSame($faqs, BotSettingRequest::parseFaqs(BotSettingRequest::formatFaqs($faqs)));
    }
}
