<?php

namespace App\Services\Meta;

/**
 * Splits long text into chunks that fit a platform limit, preferring paragraph, sentence, then word
 * boundaries. Multibyte-safe; the limit can be measured in characters or UTF-8 bytes.
 */
class MessageSplitter
{
    /** @return list<string> */
    public function split(string $text, int $limit, bool $bytes = false): array
    {
        $text = trim($text);

        if ($limit < 1 || $this->length($text, $bytes) <= $limit) {
            return $text === '' ? [] : [$text];
        }

        $chunks = [];

        while ($text !== '' && $this->length($text, $bytes) > $limit) {
            $window = $bytes ? mb_strcut($text, 0, $limit, 'UTF-8') : mb_substr($text, 0, $limit);
            $cut = $this->boundary($window);
            $chunk = rtrim(mb_substr($window, 0, $cut));

            if ($chunk === '') { // no usable boundary: hard cut
                $chunk = $window;
                $cut = mb_strlen($window);
            }

            $chunks[] = $chunk;
            $text = ltrim(mb_substr($text, $cut));
        }

        if ($text !== '') {
            $chunks[] = $text;
        }

        return $chunks;
    }

    /** Character offset to cut the window at (end of the best boundary in its second half). */
    private function boundary(string $window): int
    {
        $length = mb_strlen($window);
        $min = (int) floor($length / 2);

        foreach (['/\n\s*\n/u', '/[.!?…](["\')\]]*)\s+/u', '/\n/u', '/\s+/u'] as $pattern) {
            if (preg_match_all($pattern, $window, $matches, PREG_OFFSET_CAPTURE) && $matches[0] !== []) {
                $last = end($matches[0]);
                // Offsets are in bytes; convert to characters.
                $offset = mb_strlen(substr($window, 0, $last[1] + strlen($last[0])));

                if ($offset >= $min) {
                    return $offset;
                }
            }
        }

        return $length;
    }

    private function length(string $text, bool $bytes): int
    {
        return $bytes ? strlen($text) : mb_strlen($text);
    }
}
