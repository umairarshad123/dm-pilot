<?php

namespace App\Services\AI;

use App\Exceptions\AiProviderException;

/** An LLM backend that turns instructions + DM history into one reply. Selected by config('ai.provider'). */
interface AiReplyProvider
{
    public function isConfigured(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $input  oldest first; roles user|assistant
     * @param  array<string, mixed>  $logContext
     *
     * @throws AiProviderException
     */
    public function reply(
        string $instructions,
        array $input,
        string $model,
        int $maxOutputTokens,
        ?float $temperature = null,
        array $logContext = [],
    ): string;
}
