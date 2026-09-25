<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;

/**
 * Pure text matching for keyword rules. Case-insensitive, whitespace-normalised, unicode-aware.
 *
 *  - exact:       whole message equals a keyword (surrounding punctuation/whitespace ignored): "Price?" = "price"
 *  - contains:    keyword appears as whole word(s) anywhere: "what's the price pls" ∋ "price" (but "pricey" does not)
 *  - starts_with: message begins with the keyword as whole word(s): "price list please" ⊃ "price list"
 *  - regex:       keyword is a PCRE pattern body WITHOUT delimiters/flags, matched case-insensitively (unicode).
 *                 Invalid patterns never match; runtime is bounded by a low backtrack limit.
 */
final class KeywordMatcher
{
    public const MAX_KEYWORD_LENGTH = 200;

    /** Longer messages are truncated before matching (keeps regex cost bounded). */
    private const MAX_TEXT_LENGTH = 2000;

    private const BACKTRACK_LIMIT = 100000;

    private const EDGE_PUNCTUATION = " \t\n\r\0\x0B.,!?;:'\"()[]{}¡¿…-";

    /** @param  list<string>  $keywords  already normalised (see normalizeKeywords) */
    public static function matches(string $matchType, array $keywords, string $text): bool
    {
        $text = self::normalizeText($text);

        if ($text === '' || $keywords === []) {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (self::matchesOne($matchType, $keyword, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $keywords
     * @return list<string>
     */
    public static function normalizeKeywords(array $keywords, bool $regex = false): array
    {
        $normalized = [];

        foreach ($keywords as $keyword) {
            if (! is_scalar($keyword)) {
                continue;
            }

            $keyword = $regex ? trim((string) $keyword) : self::normalizeText((string) $keyword);

            if ($keyword !== '' && mb_strlen($keyword) <= self::MAX_KEYWORD_LENGTH) {
                $normalized[] = $keyword;
            }
        }

        return array_values(array_unique($normalized));
    }

    /** Null when the pattern body compiles, otherwise a human-readable error. */
    public static function regexError(string $pattern): ?string
    {
        if (trim($pattern) === '') {
            return 'Pattern is empty.';
        }

        if (mb_strlen($pattern) > self::MAX_KEYWORD_LENGTH) {
            return 'Pattern is too long (max '.self::MAX_KEYWORD_LENGTH.' characters).';
        }

        $result = @preg_match(self::compileRegex($pattern), '');

        return $result === false ? 'Invalid regular expression: '.(preg_last_error_msg() ?: 'syntax error') : null;
    }

    public static function normalizeText(string $text): string
    {
        $text = mb_strtolower(mb_substr($text, 0, self::MAX_TEXT_LENGTH));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function matchesOne(string $matchType, string $keyword, string $text): bool
    {
        return match ($matchType) {
            AutomationRule::MATCH_EXACT => self::trimEdges($text) === self::trimEdges($keyword),
            AutomationRule::MATCH_CONTAINS => self::safeMatch('(?<![\p{L}\p{N}_])'.preg_quote($keyword, '~').'(?![\p{L}\p{N}_])', $text),
            AutomationRule::MATCH_STARTS_WITH => self::safeMatch('^'.preg_quote($keyword, '~').'(?![\p{L}\p{N}_])', $text),
            AutomationRule::MATCH_REGEX => self::regexError($keyword) === null && self::safeMatch($keyword, $text, raw: true),
            default => false,
        };
    }

    private static function trimEdges(string $value): string
    {
        return trim($value, self::EDGE_PUNCTUATION);
    }

    private static function compileRegex(string $body): string
    {
        // Escape the delimiter unless the user already escaped it.
        return '~'.(preg_replace('/(?<!\\\\)~/', '\~', $body) ?? $body).'~iu';
    }

    private static function safeMatch(string $body, string $text, bool $raw = false): bool
    {
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', (string) self::BACKTRACK_LIMIT);

        try {
            return @preg_match($raw ? self::compileRegex($body) : '~'.$body.'~u', $text) === 1;
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previous);
        }
    }
}
