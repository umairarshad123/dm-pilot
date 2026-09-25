<?php

namespace App\Services\Automation;

use App\Models\AutomationRule;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validates the `keywords` array of an automation rule; regex keywords must compile. */
class ValidAutomationKeywords implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($this->data['trigger'] ?? AutomationRule::TRIGGER_KEYWORD) !== AutomationRule::TRIGGER_KEYWORD) {
            return;
        }

        if (! is_array($value)) {
            return; // the "array" rule reports this
        }

        $regex = ($this->data['match_type'] ?? null) === AutomationRule::MATCH_REGEX;

        if (KeywordMatcher::normalizeKeywords($value, $regex) === []) {
            $fail('Add at least one keyword.');

            return;
        }

        if ($regex) {
            foreach ($value as $pattern) {
                if (is_string($pattern) && trim($pattern) !== '' && ($error = KeywordMatcher::regexError(trim($pattern))) !== null) {
                    $fail(sprintf('"%s": %s', mb_substr($pattern, 0, 60), $error));

                    return;
                }
            }
        }
    }
}
