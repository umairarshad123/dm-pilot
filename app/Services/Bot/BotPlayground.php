<?php

namespace App\Services\Bot;

use App\Enums\Platform;
use App\Exceptions\AiProviderException;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;
use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\PromptBuilder;
use App\Services\Automation\AutomationMatcher;
use App\Services\Automation\ReplyTemplate;
use InvalidArgumentException;

/**
 * "Test your bot" chat: answers like the live bot would (same settings, prompt, automations and provider)
 * but with no DB writes and no Meta calls. Rule trigger counters are NOT incremented.
 * The bot_enabled switches and conversation rules (pause / takeover / 24h window) are intentionally ignored.
 */
class BotPlayground
{
    private const MAX_MESSAGE_CHARS = 4000;

    public function __construct(
        private readonly BotSettingsResolver $resolver,
        private readonly PromptBuilder $prompts,
        private readonly AutomationMatcher $automations,
        private readonly AiProviderFactory $providers,
    ) {}

    /**
     * @param  string  $platform  "facebook" | "instagram" (channel instructions + automations)
     * @param  list<array{role: string, content: string}>  $history  oldest first; roles user|assistant; last must be user
     * @param  ?string  $customerName  optional, for {first_name}/{name} in automation replies
     * @param  ?string  $payload  optional postback payload to simulate a button tap (e.g. "GET_STARTED")
     * @return array{text: string, source: 'automation'|'ai', rule_id?: int, provider: string, model: string, latency_ms: int}
     *
     * @throws InvalidArgumentException invalid platform / empty history / last message not from the user
     * @throws AiProviderException AI not configured or the provider failed (message is safe to show)
     */
    public function reply(?MetaAccount $account, string $platform, array $history, ?string $customerName = null, ?string $payload = null): array
    {
        $channel = Platform::tryFrom($platform) ?? throw new InvalidArgumentException('Unknown platform: '.$platform);
        $settings = $this->resolver->forAccount($account);
        $input = $this->normalizeHistory($history);
        $last = end($input);

        if ($last === false || $last['role'] !== 'user') {
            throw new InvalidArgumentException('The last playground message must be from the user.');
        }

        $started = hrtime(true);
        // Same rule as live: first contact until the bot has said something.
        $firstContact = array_filter($input, fn (array $item) => $item['role'] === 'assistant') === [];

        $rule = $this->automations->matchText($account, $last['content'], $payload, $firstContact);

        if ($rule !== null) {
            $text = ReplyTemplate::render($rule->reply_text, ReplyTemplate::variables($customerName, $account?->page_name));

            if ($text !== '') {
                return [
                    'text' => $text,
                    'source' => 'automation',
                    'rule_id' => $rule->id,
                    'provider' => $settings->aiProvider,
                    'model' => $settings->model,
                    'latency_ms' => $this->elapsed($started),
                ];
            }
        }

        $provider = $this->providers->for($settings);

        if (! $provider->isConfigured()) {
            throw new AiProviderException(sprintf('The %s API key is not configured.', $settings->aiProvider === AiProviderFactory::CLAUDE ? 'Anthropic' : 'OpenAI'));
        }

        $text = $provider->reply(
            instructions: $this->prompts->instructions(new PromptContext($settings, $channel)),
            input: array_slice($input, -$settings->historyLimit),
            model: $settings->model,
            maxOutputTokens: $settings->maxOutputTokens,
            temperature: $settings->temperature,
            logContext: ['playground' => true, 'account_id' => $account?->id],
        );

        return [
            'text' => $text,
            'source' => 'ai',
            'provider' => $settings->aiProvider,
            'model' => $settings->model,
            'latency_ms' => $this->elapsed($started),
        ];
    }

    /** @return list<array{role: string, content: string}> */
    private function normalizeHistory(array $history): array
    {
        $input = [];

        foreach ($history as $item) {
            $role = is_array($item) ? ($item['role'] ?? null) : null;
            $content = is_array($item) && is_scalar($item['content'] ?? null) ? trim((string) $item['content']) : '';

            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $input[] = ['role' => $role, 'content' => mb_substr($content, 0, self::MAX_MESSAGE_CHARS)];
            }
        }

        return $input;
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
