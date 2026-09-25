<?php

namespace App\Services\Bot;

/** Effective (merged) bot settings for one account. */
final readonly class BotSettings
{
    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     * @param  array<string, string>  $channelInstructions  keyed by platform value
     */
    public function __construct(
        public bool $botEnabled,
        public string $systemPrompt,
        public ?string $businessInfo,
        public array $faqs,
        public ?string $offers,
        public array $channelInstructions,
        public string $model,
        public ?float $temperature,
        public int $maxOutputTokens,
        public int $historyLimit,
        public int $replyDelaySeconds,
        public int $humanTakeoverMinutes,
        public ?string $fallbackMessage,
        public string $aiProvider = 'openai', // "openai" | "claude" (see AiProviderFactory)
    ) {}

    public function channelInstruction(string $platform): ?string
    {
        return $this->channelInstructions[$platform] ?? null;
    }
}
