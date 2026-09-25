<?php

namespace App\Services\AI;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Exceptions\AiProviderException;
use App\Services\Meta\MetaGraphClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Claude via the official Anthropic PHP SDK (Messages API). Logs to the `openai` (AI) channel. */
class ClaudeService implements AiReplyProvider
{
    private ?Client $client = null;

    public function isConfigured(): bool
    {
        return filled(config('anthropic.api_key'));
    }

    public function reply(
        string $instructions,
        array $input,
        string $model,
        int $maxOutputTokens,
        ?float $temperature = null, // not sent: current Claude models reject sampling parameters
        array $logContext = [],
    ): string {
        if (! $this->isConfigured()) {
            throw new AiProviderException('Anthropic API key is not configured.');
        }

        // Bot settings may still name an OpenAI model after switching providers.
        if (! str_starts_with($model, 'claude')) {
            $model = (string) config('anthropic.model');
        }

        $logContext += [
            'provider' => 'claude',
            'model' => $model,
            'instructions_chars' => mb_strlen($instructions),
            'input_items' => count($input),
        ];
        $started = microtime(true);

        try {
            $message = $this->client()->beta->messages->create(
                maxTokens: max($maxOutputTokens, (int) config('anthropic.max_output_tokens')),
                messages: $input,
                model: $model,
                // Server-side refusal fallback: if the model declines, another model answers in the same call.
                fallbacks: 'default',
                outputConfig: ['effort' => (string) config('anthropic.effort', 'low')],
                system: [
                    ['type' => 'text', 'text' => $instructions, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                betas: ['server-side-fallback-2026-07-01'],
                requestOptions: ['timeout' => (float) config('anthropic.timeout', 60), 'maxRetries' => 1],
            );
        } catch (APIStatusException $e) {
            throw $this->toException($e, $logContext);
        } catch (APIConnectionException $e) {
            Log::channel('openai')->warning('Claude connection failed.', $logContext + ['error' => MetaGraphClient::scrub($e->getMessage())]);

            throw new AiProviderException('Could not reach Anthropic (timeout or connection error).', retryable: true);
        } catch (Throwable $e) {
            throw new AiProviderException('Claude request error: '.MetaGraphClient::scrub($e->getMessage()), retryable: true);
        }

        Log::channel('openai')->info('Claude response.', $logContext + [
            'served_by' => $message->model,
            'stop_reason' => $message->stopReason,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'input_tokens' => $message->usage->inputTokens,
            'cache_read_tokens' => $message->usage->cacheReadInputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ]);

        if ($message->stopReason === 'refusal') {
            throw new AiProviderException('Claude declined to answer this message.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $text = trim($text);

        if ($text === '') {
            throw new AiProviderException('Claude returned an empty reply (stop reason: '.($message->stopReason ?? 'unknown').').');
        }

        return $text;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: (string) config('anthropic.api_key'));
    }

    private function toException(APIStatusException $e, array $logContext): AiProviderException
    {
        $status = (int) $e->status;
        $retryable = $status === 429 || $status === 408 || $status === 409 || $status >= 500;
        $message = MetaGraphClient::scrub($e->getMessage());

        Log::channel('openai')->error('Claude request failed.', $logContext + [
            'http_status' => $status,
            'error_type' => $e->type?->value,
            'retryable' => $retryable,
        ]);

        return new AiProviderException(
            sprintf('Claude error (HTTP %d): %s', $status, mb_substr($message, 0, 300)),
            $retryable,
            $status === 429 ? 30 : null,
            $status,
        );
    }
}
