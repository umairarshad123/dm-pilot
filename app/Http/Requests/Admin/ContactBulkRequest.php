<?php

namespace App\Http\Requests\Admin;

use App\Enums\LeadStage;
use Illuminate\Validation\Rule;

/**
 * Bulk action on contacts: either explicit `ids` (the selected rows) or `all=1` = every contact matching
 * `filters` (the list's query string). Always limited to the page switcher's current page.
 */
class ContactBulkRequest extends ContactFilterRequest
{
    public const ACTIONS = ['add_tag', 'remove_tag', 'set_stage', 'delete'];

    public const MAX_IDS = 1000;

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_unless:all,1,true', 'array', 'max:'.self::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
            'filters' => ['sometimes', 'nullable', 'array'],
            'tag' => ['required_if:action,add_tag,remove_tag', 'nullable', 'string', 'max:50', 'not_regex:/^\s*$/'],
            'lead_stage' => ['required_if:action,set_stage', 'nullable', Rule::enum(LeadStage::class)],
            'confirm' => ['required_if:action,delete', 'nullable', 'in:DELETE'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required_unless' => 'Select at least one contact.',
            'tag.required_if' => 'Enter a tag.',
            'lead_stage.required_if' => 'Choose a stage.',
            'confirm.required_if' => 'Type DELETE to confirm.',
            'confirm.in' => 'Type DELETE to confirm.',
        ];
    }

    public function selectsAll(): bool
    {
        return $this->boolean('all');
    }
}
