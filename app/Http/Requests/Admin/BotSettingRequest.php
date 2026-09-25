<?php

namespace App\Http\Requests\Admin;

use App\Services\AI\AiProviderFactory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bot settings form (global row or per-account override). Blank fields are stored as NULL,
 * which means "inherit": per-account → global row → config/bot.php + provider config.
 *
 * FAQs arrive either as a list (faqs[i][question], faqs[i][answer]; Bot Studio) or as "Q:/A:" text
 * (faqs_text; legacy). The list wins when both are present.
 */
class BotSettingRequest extends FormRequest
{
    public const MAX_FAQS = 200;

    /** Which Bot Studio tab each field lives on (used to flag tabs that have validation errors). */
    public const TABS = [
        'persona' => ['bot_enabled', 'system_prompt', 'channel_instructions'],
        'knowledge' => ['business_info', 'faqs', 'faqs_text', 'offers'],
        'model' => ['ai_provider', 'model', 'temperature', 'max_output_tokens'],
        'timing' => ['history_limit', 'reply_delay_seconds', 'human_takeover_minutes', 'fallback_message'],
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bot_enabled' => ['required', 'boolean'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'business_info' => ['nullable', 'string', 'max:50000'],
            'faqs_text' => ['nullable', 'string', 'max:50000'],
            'faqs' => ['nullable', 'array', 'max:'.self::MAX_FAQS],
            'faqs.*' => ['array'],
            'faqs.*.question' => ['nullable', 'string', 'max:1000', 'required_with:faqs.*.answer'],
            'faqs.*.answer' => ['nullable', 'string', 'max:5000', 'required_with:faqs.*.question'],
            'offers' => ['nullable', 'string', 'max:20000'],
            'channel_instructions' => ['nullable', 'array'],
            'channel_instructions.facebook' => ['nullable', 'string', 'max:5000'],
            'channel_instructions.instagram' => ['nullable', 'string', 'max:5000'],
            'ai_provider' => ['nullable', 'string', Rule::in(AiProviderFactory::keys())],
            'model' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-\/]+$/'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:32000'],
            'history_limit' => ['nullable', 'integer', 'min:0', 'max:200'],
            'reply_delay_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'human_takeover_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'fallback_message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'faqs.*.question' => 'FAQ question',
            'faqs.*.answer' => 'FAQ answer',
            'channel_instructions.facebook' => 'Messenger instructions',
            'channel_instructions.instagram' => 'Instagram instructions',
            'ai_provider' => 'AI provider',
            'max_output_tokens' => 'max output tokens',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'faqs.*.question.required_with' => 'Every FAQ answer needs a question.',
            'faqs.*.answer.required_with' => 'Every FAQ question needs an answer.',
            'model.regex' => 'The model id may only contain letters, numbers and . _ : - /',
        ];
    }

    /** Validated input mapped to bot_settings columns. */
    public function settingAttributes(): array
    {
        $v = $this->validated();
        $blankToNull = fn (?string $s): ?string => ($s = trim((string) $s)) === '' ? null : $s;

        $channels = array_filter([
            'facebook' => $blankToNull($v['channel_instructions']['facebook'] ?? null),
            'instagram' => $blankToNull($v['channel_instructions']['instagram'] ?? null),
        ], fn ($value) => $value !== null);

        $faqs = isset($v['faqs']) && is_array($v['faqs'])
            ? self::normalizeFaqList($v['faqs'])
            : self::parseFaqs($v['faqs_text'] ?? null);

        return [
            'bot_enabled' => $this->boolean('bot_enabled'),
            'system_prompt' => $blankToNull($v['system_prompt'] ?? null),
            'business_info' => $blankToNull($v['business_info'] ?? null),
            'faqs' => $faqs === [] ? null : $faqs,
            'offers' => $blankToNull($v['offers'] ?? null),
            'channel_instructions' => $channels === [] ? null : $channels,
            'ai_provider' => AiProviderFactory::normalize($v['ai_provider'] ?? null),
            'model' => $blankToNull($v['model'] ?? null),
            'temperature' => isset($v['temperature']) ? (float) $v['temperature'] : null,
            'max_output_tokens' => isset($v['max_output_tokens']) ? (int) $v['max_output_tokens'] : null,
            'history_limit' => isset($v['history_limit']) ? (int) $v['history_limit'] : null,
            'reply_delay_seconds' => isset($v['reply_delay_seconds']) ? (int) $v['reply_delay_seconds'] : null,
            'human_takeover_minutes' => isset($v['human_takeover_minutes']) ? (int) $v['human_takeover_minutes'] : null,
            'fallback_message' => $blankToNull($v['fallback_message'] ?? null),
        ];
    }

    /**
     * Trim a submitted FAQ list and drop empty rows (order preserved).
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function normalizeFaqList(array $faqs): array
    {
        $items = [];

        foreach ($faqs as $faq) {
            $question = trim((string) (is_array($faq) ? ($faq['question'] ?? '') : ''));
            $answer = trim((string) (is_array($faq) ? ($faq['answer'] ?? '') : ''));

            if ($question !== '' && $answer !== '') {
                $items[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $items;
    }

    /**
     * Parse "Q: ... / A: ..." text into [{question, answer}]. Lines without a prefix continue the
     * current question/answer. Entries missing either side are dropped.
     *
     * @return list<array{question: string, answer: string}>
     */
    public static function parseFaqs(?string $text): array
    {
        $items = [];
        $current = null;
        $field = null;

        foreach (preg_split('/\R/', (string) $text) as $line) {
            if (preg_match('/^\s*Q\s*[:.]\s*(.*)$/i', $line, $m)) {
                if ($current !== null) {
                    $items[] = $current;
                }
                $current = ['question' => $m[1], 'answer' => ''];
                $field = 'question';
            } elseif ($current !== null && preg_match('/^\s*A\s*[:.]\s*(.*)$/i', $line, $m)) {
                $current['answer'] = $m[1];
                $field = 'answer';
            } elseif ($current !== null && $field !== null) {
                $current[$field] .= "\n".$line;
            }
        }

        if ($current !== null) {
            $items[] = $current;
        }

        return array_values(array_filter(
            array_map(fn (array $i) => ['question' => trim($i['question']), 'answer' => trim($i['answer'])], $items),
            fn (array $i) => $i['question'] !== '' && $i['answer'] !== '',
        ));
    }

    /** Inverse of parseFaqs() for pre-filling a textarea. */
    public static function formatFaqs(?array $faqs): string
    {
        return collect($faqs ?? [])
            ->filter(fn ($i) => is_array($i))
            ->map(fn (array $i) => 'Q: '.($i['question'] ?? '')."\nA: ".($i['answer'] ?? ''))
            ->implode("\n\n");
    }

    /**
     * Tabs that contain at least one of the given error keys.
     *
     * @param  iterable<string>  $errorKeys
     * @return list<string>
     */
    public static function tabsWithErrors(iterable $errorKeys): array
    {
        $tabs = [];

        foreach ($errorKeys as $key) {
            $root = explode('.', (string) $key)[0];

            foreach (self::TABS as $tab => $fields) {
                if (in_array($root, $fields, true) && ! in_array($tab, $tabs, true)) {
                    $tabs[] = $tab;
                }
            }
        }

        return $tabs;
    }
}
