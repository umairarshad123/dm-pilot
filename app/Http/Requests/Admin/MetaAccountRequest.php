<?php

namespace App\Http\Requests\Admin;

use App\Enums\Platform;
use App\Models\MetaAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetaAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var MetaAccount|null $account */
        $account = $this->route('metaAccount');
        $platform = (string) $this->input('platform');

        return [
            'platform' => ['required', Rule::enum(Platform::class)],
            'auth_type' => ['required', Rule::in([MetaAccount::AUTH_FACEBOOK_LOGIN, MetaAccount::AUTH_INSTAGRAM_LOGIN])],
            'page_id' => [
                Rule::requiredIf($platform === Platform::Facebook->value),
                'nullable', 'string', 'max:64', 'regex:/^\d+$/',
                Rule::unique('meta_accounts', 'page_id')->where('platform', $platform)->ignore($account?->id),
            ],
            'instagram_account_id' => [
                Rule::requiredIf($platform === Platform::Instagram->value),
                'nullable', 'string', 'max:64', 'regex:/^\d+$/',
                Rule::unique('meta_accounts', 'instagram_account_id')->where('platform', $platform)->ignore($account?->id),
            ],
            'page_name' => ['nullable', 'string', 'max:255'],
            // Write-only: required on create; blank on edit keeps the stored token.
            'access_token' => [$account ? 'nullable' : 'required', 'string', 'min:20', 'max:2048', 'regex:/^\S+$/'],
            'token_expires_at' => ['nullable', 'date'],
            'active' => ['required', 'boolean'],
        ];
    }

    /** Never flash the token back into the session as "old input" on validation failure. */
    protected function failedValidation(Validator $validator): void
    {
        app('request')->request->remove('access_token');

        parent::failedValidation($validator);
    }

    public function messages(): array
    {
        return ['access_token.regex' => 'The access token must not contain spaces.'];
    }

    /** Attributes to save; omits access_token when left blank. */
    public function accountAttributes(): array
    {
        $data = $this->safe()->except('access_token');
        $data['active'] = $this->boolean('active');

        if (filled($this->validated('access_token'))) {
            $data['access_token'] = trim((string) $this->validated('access_token'));
        }

        return $data;
    }
}
