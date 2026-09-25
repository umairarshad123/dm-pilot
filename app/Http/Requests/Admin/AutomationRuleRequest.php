<?php

namespace App\Http\Requests\Admin;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create / update a keyword (or welcome) automation rule. Uses AutomationRule::validationRules().
 * Keywords may be sent as an array or as a comma / newline separated string.
 */
class AutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keywords = $this->input('keywords');

        if (is_string($keywords)) {
            $keywords = preg_split('/[\n,]+/', $keywords) ?: [];
        }

        if (is_array($keywords)) {
            $keywords = array_values(array_filter(
                array_map(fn ($k) => is_scalar($k) ? trim((string) $k) : $k, $keywords),
                fn ($k) => $k !== '' && $k !== null,
            ));
        }

        $this->merge([
            'trigger' => $this->input('trigger', AutomationRule::TRIGGER_KEYWORD),
            'keywords' => $keywords,
            'meta_account_id' => $this->filled('meta_account_id') ? $this->input('meta_account_id') : null,
            'active' => $this->has('active') ? $this->boolean('active') : true,
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return AutomationRule::validationRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'meta_account_id' => 'page',
            'reply_text' => 'reply',
            'match_type' => 'match type',
            'keywords.*' => 'keyword',
        ];
    }

    /** @return array<string, mixed> */
    public function ruleAttributes(): array
    {
        $v = $this->validated();
        $keyword = $v['trigger'] === AutomationRule::TRIGGER_KEYWORD;

        return [
            'meta_account_id' => $v['meta_account_id'] ?? null,
            'name' => $v['name'],
            'trigger' => $v['trigger'],
            'match_type' => $keyword ? ($v['match_type'] ?? AutomationRule::MATCH_CONTAINS) : AutomationRule::MATCH_CONTAINS,
            'keywords' => $keyword ? array_values(array_unique(array_map('strval', $v['keywords'] ?? []))) : null,
            'reply_text' => trim($v['reply_text']),
            'priority' => (int) ($v['priority'] ?? 0),
            'active' => (bool) ($v['active'] ?? true),
        ];
    }
}
