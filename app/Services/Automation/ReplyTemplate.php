<?php

namespace App\Services\Automation;

use App\Models\Conversation;
use App\Models\MetaAccount;

/**
 * Renders automation reply text. Supported variables: {first_name} {name} {page_name}.
 * Missing values become empty and the surrounding whitespace is tidied ("Hi {first_name}!" → "Hi!").
 * Unknown {placeholders} are left untouched.
 */
final class ReplyTemplate
{
    /** @param  array<string, ?string>  $variables  keyed without braces, e.g. ['first_name' => 'Sara'] */
    public static function render(string $template, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{'.$key.'}'] = trim((string) $value);
        }

        $text = strtr($template, $replacements);

        // Only tidy when an empty variable left a gap; otherwise keep the owner's text exactly.
        $gaps = array_filter($replacements, fn (string $value, string $key) => $value === '' && str_contains($template, $key), ARRAY_FILTER_USE_BOTH);

        if ($gaps === []) {
            return trim($text);
        }

        $lines = array_map(function (string $line): string {
            $line = preg_replace('/[ \t]{2,}/u', ' ', $line) ?? $line;
            $line = preg_replace('/[ \t]+([,.!?;:])/u', '$1', $line) ?? $line;
            $line = preg_replace('/^[,;:]+[ \t]*/u', '', trim($line)) ?? $line; // ", welcome" → "welcome"

            return trim($line);
        }, preg_split('/\r\n|\r|\n/', $text) ?: [$text]);

        return trim(implode("\n", $lines));
    }

    /** @return array{first_name: string, name: string, page_name: string} */
    public static function variables(?string $customerName, ?string $pageName): array
    {
        $name = trim((string) $customerName);

        return [
            'first_name' => $name === '' ? '' : (string) preg_split('/\s+/u', $name)[0],
            'name' => $name,
            'page_name' => trim((string) $pageName),
        ];
    }

    /** @return array{first_name: string, name: string, page_name: string} */
    public static function variablesFor(Conversation $conversation, ?MetaAccount $account = null): array
    {
        return self::variables($conversation->customer_name, ($account ?? $conversation->metaAccount)?->page_name);
    }
}
