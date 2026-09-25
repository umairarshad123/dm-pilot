<?php

namespace App\Services\AI;

use App\Exceptions\OpenAIException;
use App\Services\Meta\MetaGraphClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Thin client for the OpenAI Responses API (POST {base_url}/responses). */
class OpenAIService implements AiReplyProvider
{
    public function isConfigured(): bool
    {
        return filled(config('openai.api_key'));
    }

    /**
     * @param  list<array{role: string, content: string}>  $input
     * @param  array<string, mixed>  $logContext  e.g. ['conversation_id' => 1]
     *
     * @throws OpenAIException
     */
    public function reply(
        string $instructions,
        array $input,
        string $model,
        int $maxOutputTokens,
        ?float $temperature = null,
        array $logContext = [],
    ): string {
        if (! $this->isConfigured()) {
            throw new OpenAIException('OpenAI API key is not configured.');
        }

        $payload = [
            'model' => $model,
            'instructions' => $instructions,
            'input' => $input,
            'max_output_tokens' => $maxOutputTokens,
            'store' => false,
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $logContext += [
            'model' => $model,
            'instructions_chars' => mb_strlen($instructions),
            'input_items' => count($input),
        ];

        $response = $this->post($payload, $logContext);

        // Some (reasoning) models reject temperature: retry once without it.
        if ($response->status() === 400 && isset($payload['temperature']) && $this->rejectsTemperature($response)) {
            Log::channel('openai')->notice('Model rejected temperature; retrying without it.', $logContext);
            unset($payload['temperature']);
            $response = $this->post($payload, $logContext);
        }

        if (! $response->successful()) {
            throw $this->toException($response, $logContext);
        }

        $text = $this->extractText((array) $response->json());
        $status = (string) $response->json('status', 'completed');

        if ($text === '') {
            $reason = $response->json('incomplete_details.reason');
            Log::channel('openai')->warning('OpenAI returned no text.', $logContext + ['status' => $status, 'reason' => $reason]);

            throw new OpenAIException('OpenAI returned an empty reply'.($reason ? " ($reason)." : '.'));
        }

        return $text;
    }

    /** Concatenate output[type=message].content[type=output_text].text (skips reasoning/refusal items). */
    public function extractText(array $json): string
    {
        $parts = [];

        foreach ((array) ($json['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }

    private function post(array $payload, array $logContext): Response
    {
        $started = hrtime(true);

        try {
            $response = Http::withToken((string) config('openai.api_key'))
                ->when(config('openai.organization'), fn ($http, $org) => $http->withHeaders(['OpenAI-Organization' => $org]))
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('openai.timeout', 30))
                ->post(rtrim((string) config('openai.base_url'), '/').'/responses', $payload);
        } catch (ConnectionException $e) {
            Log::channel('openai')->warning('OpenAI connection failed.', $logContext + [
                'latency_ms' => $this->elapsed($started),
                'error' => MetaGraphClient::scrub($e->getMessage()),
            ]);

            throw new OpenAIException('Could not reach OpenAI (timeout or connection error).', retryable: true);
        } catch (Throwable $e) {
            throw new OpenAIException('OpenAI request error: '.MetaGraphClient::scrub($e->getMessage()), retryable: true);
        }

        Log::channel('openai')->info('OpenAI response.', $logContext + [
            'http_status' => $response->status(),
            'status' => $response->json('status'),
            'latency_ms' => $this->elapsed($started),
            'input_tokens' => $response->json('usage.input_tokens'),
            'output_tokens' => $response->json('usage.output_tokens'),
            'reasoning_tokens' => $response->json('usage.output_tokens_details.reasoning_tokens'),
            'total_tokens' => $response->json('usage.total_tokens'),
            'response_id' => $response->json('id'),
        ]);

        return $response;
    }

    private function rejectsTemperature(Response $response): bool
    {
        $param = (string) $response->json('error.param');
        $message = strtolower((string) $response->json('error.message'));

        return $param === 'temperature' || str_contains($message, 'temperature');
    }

    private function toException(Response $response, array $logContext): OpenAIException
    {
        $status = $response->status();
        $retryable = $status === 429 || $status === 408 || $status === 409 || $status >= 500;
        $message = MetaGraphClient::scrub((string) ($response->json('error.message') ?? 'HTTP '.$status));

        // Quota/billing 429s (type or code, e.g. insufficient_quota / credit_balance_exhausted) won't recover by retrying.
        $billingErrors = ['insufficient_quota', 'credit_balance_exhausted', 'billing_hard_limit_reached'];
        if (in_array($response->json('error.code'), $billingErrors, true) || in_array($response->json('error.type'), $billingErrors, true)) {
            $retryable = false;
        }

        $retryAfter = $response->header('Retry-After');

        Log::channel('openai')->error('OpenAI request failed.', $logContext + [
            'http_status' => $status,
            'error_type' => $response->json('error.type'),
            'error_code' => $response->json('error.code'),
            'retryable' => $retryable,
        ]);

        return new OpenAIException(
            sprintf('OpenAI error (HTTP %d): %s', $status, mb_substr($message, 0, 300)),
            $retryable,
            is_numeric($retryAfter) ? max(1, (int) ceil((float) $retryAfter)) : null,
            $status,
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
