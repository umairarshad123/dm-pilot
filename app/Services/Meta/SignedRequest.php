<?php

namespace App\Services\Meta;

use UnexpectedValueException;

/**
 * Parses and verifies a Meta `signed_request` (Data Deletion / Deauthorize callbacks, Facebook Login).
 *
 * Format: "<base64url(signature)>.<base64url(json payload)>", where signature = HMAC-SHA256(encoded payload, app secret).
 *
 * @see https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback
 */
class SignedRequest
{
    private readonly ?string $appSecret;

    /** @param  string|null  $appSecret  Defaults to config('meta.app_secret') (META_APP_SECRET). */
    public function __construct(?string $appSecret = null)
    {
        $this->appSecret = $appSecret ?? config('meta.app_secret');
    }

    /**
     * @param  int|null  $maxAgeSeconds  Reject payloads whose `issued_at` is older than this (null = no check).
     * @return array<string, mixed> The verified payload (algorithm, issued_at, user_id, ...).
     *
     * @throws UnexpectedValueException When the request is malformed, unsigned, tampered or too old.
     */
    public function parse(?string $signedRequest, ?int $maxAgeSeconds = null): array
    {
        if ($this->appSecret === null || $this->appSecret === '') {
            throw new UnexpectedValueException('META_APP_SECRET is not configured.');
        }

        $signedRequest = trim((string) $signedRequest);

        if ($signedRequest === '' || substr_count($signedRequest, '.') !== 1) {
            throw new UnexpectedValueException('Malformed signed_request.');
        }

        [$encodedSignature, $encodedPayload] = explode('.', $signedRequest, 2);

        $signature = self::base64UrlDecode($encodedSignature);
        $json = self::base64UrlDecode($encodedPayload);

        if ($signature === null || $json === null || $signature === '') {
            throw new UnexpectedValueException('Malformed signed_request encoding.');
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new UnexpectedValueException('Malformed signed_request payload.');
        }

        if (strtoupper((string) ($payload['algorithm'] ?? '')) !== 'HMAC-SHA256') {
            throw new UnexpectedValueException('Unsupported signed_request algorithm.');
        }

        $expected = hash_hmac('sha256', $encodedPayload, $this->appSecret, true);

        if (! hash_equals($expected, $signature)) {
            throw new UnexpectedValueException('Invalid signed_request signature.');
        }

        if ($maxAgeSeconds !== null && isset($payload['issued_at'])
            && (int) $payload['issued_at'] < time() - $maxAgeSeconds) {
            throw new UnexpectedValueException('Expired signed_request.');
        }

        return $payload;
    }

    /** Build a signed_request (used by tests and local tooling). */
    public function make(array $payload): string
    {
        $payload += ['algorithm' => 'HMAC-SHA256', 'issued_at' => time()];
        $encodedPayload = self::base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, (string) $this->appSecret, true);

        return self::base64UrlEncode($signature).'.'.$encodedPayload;
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): ?string
    {
        if ($data === '' || preg_match('/^[A-Za-z0-9\-_]+={0,2}$/', $data) !== 1) {
            return null;
        }

        $data = rtrim($data, '=');
        $data .= str_repeat('=', (4 - strlen($data) % 4) % 4);
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
