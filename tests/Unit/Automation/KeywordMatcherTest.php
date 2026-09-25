<?php

namespace Tests\Unit\Automation;

use App\Models\AutomationRule;
use App\Services\Automation\KeywordMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KeywordMatcherTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'exact ignores case + edge punctuation' => [AutomationRule::MATCH_EXACT, ['Price'], '  PRICE?! ', true],
            'exact needs whole message' => [AutomationRule::MATCH_EXACT, ['price'], 'price please', false],
            'contains whole word' => [AutomationRule::MATCH_CONTAINS, ['price'], "What's the price, pls", true],
            'contains is word-bounded' => [AutomationRule::MATCH_CONTAINS, ['price'], 'that is pricey', false],
            'contains multi-word phrase' => [AutomationRule::MATCH_CONTAINS, ['price list'], 'send the  Price   List now', true],
            'contains unicode' => [AutomationRule::MATCH_CONTAINS, ['قیمت'], 'اس کی قیمت کیا ہے', true],
            'starts_with' => [AutomationRule::MATCH_STARTS_WITH, ['hi'], 'Hi there', true],
            'starts_with word-bounded' => [AutomationRule::MATCH_STARTS_WITH, ['hi'], 'high five', false],
            'starts_with not in middle' => [AutomationRule::MATCH_STARTS_WITH, ['hi'], 'oh hi', false],
            'regex' => [AutomationRule::MATCH_REGEX, ['^order\s*#?\d+$'], 'Order #1234', true],
            'regex with tilde' => [AutomationRule::MATCH_REGEX, ['a~b'], 'xa~bx', true],
            'invalid regex never matches' => [AutomationRule::MATCH_REGEX, ['(unclosed'], '(unclosed', false],
            'any keyword matches' => [AutomationRule::MATCH_CONTAINS, ['cost', 'price'], 'price?', true],
            'unknown type' => ['fuzzy', ['price'], 'price', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_matching(string $type, array $keywords, string $text, bool $expected): void
    {
        $normalized = KeywordMatcher::normalizeKeywords($keywords, $type === AutomationRule::MATCH_REGEX);

        $this->assertSame($expected, KeywordMatcher::matches($type, $normalized, $text));
    }

    public function test_catastrophic_regex_is_bounded(): void
    {
        $started = microtime(true);

        $this->assertFalse(KeywordMatcher::matches(AutomationRule::MATCH_REGEX, ['(a+)+$'], str_repeat('a', 1500).'b'));
        $this->assertLessThan(2, microtime(true) - $started);
    }

    public function test_regex_error_and_keyword_normalization(): void
    {
        $this->assertNull(KeywordMatcher::regexError('^hi\b'));
        $this->assertNotNull(KeywordMatcher::regexError('[a-'));
        $this->assertNotNull(KeywordMatcher::regexError(' '));
        $this->assertNotNull(KeywordMatcher::regexError(str_repeat('a', 201)));

        $this->assertSame(['price', 'hello there'], KeywordMatcher::normalizeKeywords([' Price ', 'PRICE', '', null, "Hello\n there", ['x']]));
    }
}
