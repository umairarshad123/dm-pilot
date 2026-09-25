<?php

namespace Tests\Feature\Automation;

use App\Exceptions\AiProviderException;
use App\Services\AI\AiReplyProvider;

/** In-memory AiReplyProvider for binding over OpenAIService / ClaudeService in tests. */
class FakeAiProvider implements AiReplyProvider
{
    /** @var list<array{instructions: string, input: array, model: string}> */
    public array $calls = [];

    public function __construct(
        public string $reply = 'Fake AI reply',
        public bool $configured = true,
        public ?AiProviderException $throw = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function reply(string $instructions, array $input, string $model, int $maxOutputTokens, ?float $temperature = null, array $logContext = []): string
    {
        $this->calls[] = compact('instructions', 'input', 'model');

        if ($this->throw !== null) {
            throw $this->throw;
        }

        return $this->reply;
    }
}
