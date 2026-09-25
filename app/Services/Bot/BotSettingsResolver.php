<?php

namespace App\Services\Bot;

use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;

/**
 * Effective settings = config/bot.php + provider config defaults ← global BotSetting row ← per-account row.
 * Non-blank values override. bot_enabled is a kill switch at every level (config AND global AND account).
 *
 * AI provider: account row → global row → config('ai.provider'). Model / temperature / max_output_tokens
 * defaults come from the resolved provider's config (openai.php or anthropic.php). A stored model that
 * belongs to the other provider (e.g. "gpt-…" while the page uses Claude) is replaced by the provider default.
 */
class BotSettingsResolver
{
    private const FIELDS = [
        'system_prompt', 'business_info', 'faqs', 'offers', 'model', 'temperature', 'max_output_tokens',
        'history_limit', 'reply_delay_seconds', 'human_takeover_minutes', 'fallback_message',
    ];

    public function forAccount(?MetaAccount $account): BotSettings
    {
        $rows = BotSetting::query()
            ->whereNull('meta_account_id')
            ->when($account, fn ($q) => $q->orWhere('meta_account_id', $account->id))
            ->get()
            ->sortBy(fn (BotSetting $row) => $row->meta_account_id === null ? 0 : 1); // global first

        $provider = AiProviderFactory::defaultProvider();

        foreach ($rows as $row) {
            $provider = AiProviderFactory::normalize($row->ai_provider) ?? $provider;
        }

        $aiConfig = AiProviderFactory::configKey($provider);

        $values = [
            'system_prompt' => config('bot.system_prompt'),
            'business_info' => null,
            'faqs' => [],
            'offers' => null,
            'model' => config($aiConfig.'.model'),
            'temperature' => config($aiConfig.'.temperature'),
            'max_output_tokens' => config($aiConfig.'.max_output_tokens'),
            'history_limit' => config('bot.history_limit'),
            'reply_delay_seconds' => config('bot.reply_delay_seconds'),
            'human_takeover_minutes' => config('bot.human_takeover_minutes'),
            'fallback_message' => config('bot.fallback_message'),
        ];
        $channels = array_filter((array) config('bot.channel_instructions', []), $this->filled(...));
        $enabled = filter_var(config('bot.enabled', true), FILTER_VALIDATE_BOOL);

        foreach ($rows as $row) {
            $enabled = $enabled && $row->bot_enabled;

            foreach (self::FIELDS as $field) {
                if ($this->filled($row->{$field})) {
                    $values[$field] = $row->{$field};
                }
            }

            $channels = array_merge($channels, array_filter((array) $row->channel_instructions, $this->filled(...)));
        }

        $model = (string) $values['model'];
        $modelProvider = AiProviderFactory::providerForModel($model);

        if ($modelProvider !== null && $modelProvider !== $provider) {
            $model = AiProviderFactory::defaultModel($provider);
        }

        return new BotSettings(
            botEnabled: $enabled,
            systemPrompt: (string) $values['system_prompt'],
            businessInfo: $this->filled($values['business_info']) ? (string) $values['business_info'] : null,
            faqs: $this->normalizeFaqs((array) $values['faqs']),
            offers: $this->filled($values['offers']) ? (string) $values['offers'] : null,
            channelInstructions: array_map('strval', $channels),
            model: $model,
            temperature: $values['temperature'] === null ? null : (float) $values['temperature'],
            maxOutputTokens: max(16, (int) $values['max_output_tokens']),
            historyLimit: max(1, (int) $values['history_limit']),
            replyDelaySeconds: max(0, (int) $values['reply_delay_seconds']),
            humanTakeoverMinutes: max(0, (int) $values['human_takeover_minutes']),
            fallbackMessage: $this->filled($values['fallback_message']) ? (string) $values['fallback_message'] : null,
            aiProvider: $provider,
        );
    }

    private function filled(mixed $value): bool
    {
        return match (true) {
            $value === null => false,
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            default => true,
        };
    }

    /** @return list<array{question: string, answer: string}> */
    private function normalizeFaqs(array $faqs): array
    {
        $normalized = [];

        foreach ($faqs as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $normalized[] = ['question' => $question, 'answer' => $answer];
            }
        }

        return $normalized;
    }
}
