<?php

namespace App\Http\Requests\Admin;

use App\Enums\Platform;
use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Foundation\Http\FormRequest;

/** Welcome screen (greeting + Get Started, Messenger only) and ice breakers for one page. */
class MessengerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'greeting' => ['nullable', 'string', 'max:'.MetaMessagingService::GREETING_MAX_CHARS],
            'get_started' => ['nullable', 'boolean'],
            'ice_breakers' => ['nullable', 'array', 'max:'.MetaMessagingService::ICE_BREAKERS_MAX],
            'ice_breakers.*.question' => ['nullable', 'string', 'max:'.MetaMessagingService::ICE_BREAKER_QUESTION_MAX_CHARS],
            'ice_breakers.*.payload' => ['nullable', 'string', 'max:'.MetaMessagingService::ICE_BREAKER_PAYLOAD_MAX_CHARS, 'regex:/^[A-Za-z0-9_\-.:]*$/'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ice_breakers' => 'ice breakers',
            'ice_breakers.*.question' => 'ice breaker question',
            'ice_breakers.*.payload' => 'ice breaker payload',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['ice_breakers.*.payload.regex' => 'Payloads may only contain letters, numbers and _ - . :'];
    }

    /**
     * Config for MetaMessagingService::setMessengerProfile(). Instagram accounts only send ice breakers.
     *
     * @return array{greeting?: ?string, get_started?: bool, ice_breakers: list<array{question: string, payload: ?string}>}
     */
    public function profileConfig(MetaAccount $account): array
    {
        $v = $this->validated();
        $iceBreakers = [];

        foreach ((array) ($v['ice_breakers'] ?? []) as $item) {
            $question = trim((string) ($item['question'] ?? ''));

            if ($question !== '') {
                $iceBreakers[] = ['question' => $question, 'payload' => trim((string) ($item['payload'] ?? '')) ?: null];
            }
        }

        $config = ['ice_breakers' => $iceBreakers];

        if ($account->platform !== Platform::Instagram) {
            $config['greeting'] = trim((string) ($v['greeting'] ?? '')) ?: null;
            $config['get_started'] = (bool) ($v['get_started'] ?? false);
        }

        return $config;
    }
}
