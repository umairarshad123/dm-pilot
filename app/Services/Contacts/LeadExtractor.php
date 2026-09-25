<?php

namespace App\Services\Contacts;

/**
 * Pulls email addresses and phone numbers out of free customer text.
 *
 * Phones: 8–15 digits (E.164 max), optional leading "+", separators limited to spaces, dashes, dots-free
 * groups and parentheses. Rejected: dates (2026-09-26, 26-09-2026), prices/amounts (preceded by a currency
 * sign or followed by a currency word), order/invoice/tracking/reference numbers (keyword or "#" right before),
 * card-like 16+ digit runs, digits inside URLs/emails/words. Results are normalized to "+digits" / "digits".
 */
class LeadExtractor
{
    private const EMAIL = '/(?<![\w.%+\-])[a-z0-9](?:[a-z0-9._%+\-]{0,63})@(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}(?![\w\-])/iu';

    // A "+", "(" or digit, then digits/spaces/dashes/parentheses, ending with a digit.
    private const PHONE = '/(?<![\w\/#$€£¥₹@=&.,\-+])(?:\+|00)?\(?\d[\d\s\-()]{5,22}\d(?![\w\/@%$€£¥₹]|[.,]\d)/u';

    // Words that make a nearby digit run NOT a phone (order ids, amounts, documents …).
    private const CONTEXT_WORDS = '/\b(order|invoice|inv|ref|reference|tracking|track|awb|shipment|parcel|ticket|booking|pnr|transaction|txn|trx|receipt|account|acct|iban|card|cnic|nic|passport|id|otp|pin|sku|model|serial|rs|pkr|usd|eur|gbp|inr|aed|sar|price|total|amount|cost|paid|pay|payment)\b/iu';

    // Explicit phone words ("phone", "whatsapp me", "call") – win over CONTEXT_WORDS when they come later.
    private const PHONE_WORDS = '/\b(phone|mobile|mob|cell|cellphone|whats\s?app|call|text|sms|tel|telephone|contact|reach)\b/iu';

    private const AFTER_KEYWORDS = '/^\s*(rs|pkr|usd|eur|gbp|inr|aed|sar|dollars?|rupees?|euros?|pounds?|units?|pcs|items?|kg|km|%)\b/iu';

    /** @return array{emails: list<string>, phones: list<string>} */
    public function extract(?string $text): array
    {
        return ['emails' => $this->emails($text), 'phones' => $this->phones($text)];
    }

    /** @return list<string> lowercase, unique */
    public function emails(?string $text): array
    {
        $text = (string) $text;

        if ($text === '' || ! str_contains($text, '@')) {
            return [];
        }

        preg_match_all(self::EMAIL, $text, $matches);

        $emails = [];

        foreach ($matches[0] as $email) {
            $email = mb_strtolower(rtrim($email, '.'));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) && ! in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /** @return list<string> normalized ("+4420…" when international, else digits only), unique */
    public function phones(?string $text): array
    {
        $text = (string) $text;

        if (preg_match_all('/\d/', $text) < 8) {
            return [];
        }

        // Emails / URLs can contain long digit runs: blank them out first (keeping offsets irrelevant).
        $clean = preg_replace([self::EMAIL, '~\b(?:https?://|www\.)\S+~iu'], ' ', $text) ?? $text;

        preg_match_all(self::PHONE, $clean, $matches, PREG_OFFSET_CAPTURE);

        $phones = [];

        foreach ($matches[0] as [$candidate, $offset]) {
            $phone = $this->normalize($candidate, substr($clean, 0, $offset), substr($clean, $offset + strlen($candidate)));

            if ($phone !== null && ! in_array($phone, $phones, true)) {
                $phones[] = $phone;
            }
        }

        return $phones;
    }

    private function normalize(string $candidate, string $before, string $after): ?string
    {
        $candidate = trim($candidate);
        $digits = preg_replace('/\D+/', '', $candidate) ?? '';
        $international = str_starts_with($candidate, '+') || str_starts_with($candidate, '00');

        if (str_starts_with($candidate, '00')) {
            $digits = substr($digits, 2);
        }

        $count = strlen($digits);

        if ($count < 8 || $count > 15) {
            return null;
        }

        // Balanced parentheses only, at most one pair.
        if (substr_count($candidate, '(') !== substr_count($candidate, ')') || substr_count($candidate, '(') > 1) {
            return null;
        }

        // Too many / doubled separators → probably a list of numbers, not one phone.
        if (preg_match('/[\s\-]{3,}/', $candidate) || preg_match_all('/[\s\-]/', $candidate) > 5) {
            return null;
        }

        if ($this->looksLikeDate($candidate)) {
            return null;
        }

        $tail = mb_substr($before, -30);
        $context = $this->lastPosition(self::CONTEXT_WORDS, $tail);
        $hint = $this->lastPosition(self::PHONE_WORDS, $tail);
        $hinted = $hint !== null && ($context === null || $hint > $context);

        if (! $hinted && ($context !== null || preg_match(self::AFTER_KEYWORDS, $after))) {
            return null;
        }

        // Unformatted digit runs without "+" or a phone hint: require a plausible length (10–13) and no leading
        // repeated zeros – order/ids are usually either shorter or much longer.
        if (! $international && ! $hinted && ! preg_match('/[\s\-()]/', $candidate)) {
            if ($count < 10 || $count > 13 || str_starts_with($digits, '000')) {
                return null;
            }
        }

        // All the same digit (00000000, 11111111) is never a real number.
        if (preg_match('/^(\d)\1+$/', $digits)) {
            return null;
        }

        return ($international ? '+' : '').$digits;
    }

    private function lastPosition(string $pattern, string $text): ?int
    {
        if (! preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return (int) end($m[0])[1];
    }

    private function looksLikeDate(string $candidate): bool
    {
        $c = preg_replace('/\s+/', '', $candidate) ?? $candidate;

        // 2026-09-26, 2026-9-6, 26-09-2026, 26-09-26, 2026 09 26
        return (bool) preg_match('/^(19|20)\d{2}[\-](0?[1-9]|1[0-2])[\-](0?[1-9]|[12]\d|3[01])$/', $c)
            || (bool) preg_match('/^(0?[1-9]|[12]\d|3[01])[\-](0?[1-9]|1[0-2])[\-]((19|20)?\d{2})$/', $c)
            || (bool) preg_match('/^(0?[1-9]|1[0-2])[\-](0?[1-9]|[12]\d|3[01])[\-]((19|20)?\d{2})$/', $c)
            || (bool) preg_match('/^(19|20)\d{2}(\s)(0?[1-9]|1[0-2])\s(0?[1-9]|[12]\d|3[01])$/', trim($candidate));
    }
}
