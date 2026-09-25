<?php

namespace App\Services\Meta;

use App\Exceptions\MetaApiException;
use App\Models\MetaAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Low-level Graph API client (graph.facebook.com / graph.instagram.com).
 *
 * Token handling: POSTs carry access_token (+ appsecret_proof) in the JSON body, GETs use the
 * Authorization: Bearer header. Tokens never go into the URL, so they cannot leak into exception
 * messages or logs. Every error text leaving this class is scrubbed.
 */
class MetaGraphClient
{
    /** Graph error codes that are transient (throttling / temporary errors). */
    private const RETRYABLE_CODES = [1, 2, 4, 17, 32, 341, 613, 1200];

    /** @return array<string, mixed> decoded JSON */
    public function post(MetaAccount $account, string $path, array $data = []): array
    {
        $token = (string) $account->access_token;
        $body = $data + ['access_token' => $token] + $this->proof($account, $token);

        return $this->send(fn (PendingRequest $http) => $http->asJson()->post($this->url($account, $path), $body));
    }

    /** DELETE with a JSON body (token in the body, like post()). @return array<string, mixed> decoded JSON */
    public function delete(MetaAccount $account, string $path, array $data = []): array
    {
        $token = (string) $account->access_token;
        $body = $data + ['access_token' => $token] + $this->proof($account, $token);

        return $this->send(fn (PendingRequest $http) => $http->asJson()->delete($this->url($account, $path), $body));
    }

    /** @return array<string, mixed> decoded JSON */
    public function get(MetaAccount $account, string $path, array $query = []): array
    {
        $token = (string) $account->access_token;
        $query += $this->proof($account, $token);

        return $this->send(fn (PendingRequest $http) => $http->withToken($token)->get($this->url($account, $path), $query));
    }

    /**
     * GET with an explicit query (for endpoints like debug_token that require tokens as params).
     * The URL is never logged or placed into an exception message.
     *
     * @return array<string, mixed>
     */
    public function getWithQuery(string $baseUrl, string $path, array $query): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->versioned($baseUrl, $path), $query));
    }

    public function baseUrl(MetaAccount $account): string
    {
        return $account->auth_type === MetaAccount::AUTH_INSTAGRAM_LOGIN
            ? (string) config('meta.instagram_graph_url')
            : (string) config('meta.graph_url');
    }

    public function url(MetaAccount $account, string $path): string
    {
        return $this->versioned($this->baseUrl($account), $path);
    }

    /** Remove anything that looks like a Meta/OpenAI token or a token query param. */
    public static function scrub(?string $text): string
    {
        $text = (string) $text;
        $text = preg_replace('/\b(EAA|IGQ|IGAA|IGA)[A-Za-z0-9_\-]{10,}/', '[redacted]', $text) ?? '';
        $text = preg_replace('/\bsk-[A-Za-z0-9_\-]{10,}/', '[redacted]', $text) ?? '';
        $text = preg_replace('/(access_token|input_token|appsecret_proof)=[^&\s"\']+/i', '$1=[redacted]', $text) ?? '';
        $text = preg_replace('/\b\d{6,}\|[A-Za-z0-9_\-]{16,}/', '[redacted]', $text) ?? ''; // app_id|app_secret

        return mb_substr($text, 0, 1000);
    }

    private function versioned(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.config('meta.graph_version').'/'.ltrim($path, '/');
    }

    /** appsecret_proof is only understood by graph.facebook.com. */
    private function proof(MetaAccount $account, string $token): array
    {
        $secret = config('meta.app_secret');

        if ($account->auth_type === MetaAccount::AUTH_INSTAGRAM_LOGIN || ! $secret) {
            return [];
        }

        return ['appsecret_proof' => hash_hmac('sha256', $token, (string) $secret)];
    }

    /** @param callable(PendingRequest): Response $call */
    private function send(callable $call): array
    {
        try {
            $response = $call(Http::acceptJson()->timeout((int) config('meta.http_timeout', 15)));
        } catch (ConnectionException $e) {
            throw new MetaApiException('Connection to Meta failed: '.self::scrub($e->getMessage()), retryable: true);
        } catch (Throwable $e) {
            throw new MetaApiException('Meta request error: '.self::scrub($e->getMessage()), retryable: true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        throw $this->toException($response);
    }

    private function toException(Response $response): MetaApiException
    {
        $error = (array) ($response->json('error') ?? []);
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $subcode = isset($error['error_subcode']) ? (int) $error['error_subcode'] : null;
        $status = $response->status();

        $retryable = $status >= 500
            || $status === 429
            || ($code !== null && (in_array($code, self::RETRYABLE_CODES, true) || ($code >= 80000 && $code < 80100)));

        // Permanent regardless of HTTP status.
        if (in_array($code, [10, 100, 190, 200, 551], true)) {
            $retryable = false;
        }

        $message = sprintf(
            'Graph error%s%s: %s',
            $code !== null ? ' '.$code : '',
            $subcode !== null ? '/'.$subcode : '',
            self::scrub($error['message'] ?? ('HTTP '.$status)),
        );

        return new MetaApiException(
            $message,
            $code,
            $subcode,
            $status,
            $retryable,
            $retryable ? $this->retryAfter($response) : null,
            isset($error['fbtrace_id']) ? (string) $error['fbtrace_id'] : null,
        );
    }

    /** Seconds until access is regained, from Retry-After or Meta usage headers (minutes). */
    private function retryAfter(Response $response): ?int
    {
        if (is_numeric($response->header('Retry-After'))) {
            return max(1, (int) $response->header('Retry-After'));
        }

        $minutes = 0;

        foreach (['X-Business-Use-Case-Usage', 'X-App-Usage', 'X-Page-Usage'] as $header) {
            $decoded = json_decode((string) $response->header($header), true);

            if (! is_array($decoded)) {
                continue;
            }

            array_walk_recursive($decoded, function ($value, $key) use (&$minutes) {
                if ($key === 'estimated_time_to_regain_access' && is_numeric($value)) {
                    $minutes = max($minutes, (int) $value);
                }
            });
        }

        return $minutes > 0 ? $minutes * 60 : null;
    }
}
