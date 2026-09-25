<?php

namespace Tests\Feature\Public;

use App\Services\Meta\SignedRequest;
use Tests\TestCase;
use UnexpectedValueException;

class SignedRequestTest extends TestCase
{
    private const SECRET = 'test-app-secret';

    private function signer(string $secret = self::SECRET): SignedRequest
    {
        return new SignedRequest($secret);
    }

    /** Build a signed_request with full control over every field (algorithm included). */
    private function rawSigned(array $payload, string $secret = self::SECRET): string
    {
        $encoded = SignedRequest::base64UrlEncode(json_encode($payload));

        return SignedRequest::base64UrlEncode(hash_hmac('sha256', $encoded, $secret, true)).'.'.$encoded;
    }

    public function test_parses_valid_signed_request(): void
    {
        $payload = $this->signer()->parse($this->signer()->make(['user_id' => '218471']));

        $this->assertSame('218471', $payload['user_id']);
        $this->assertSame('HMAC-SHA256', $payload['algorithm']);
    }

    public function test_matches_meta_reference_example(): void
    {
        // Same construction as Meta's PHP sample: base64url(sig).base64url(json).
        $signed = $this->rawSigned(['algorithm' => 'HMAC-SHA256', 'expires' => 1291840400, 'issued_at' => 1291836800, 'user_id' => '218471']);

        $this->assertSame('218471', $this->signer()->parse($signed)['user_id']);
    }

    public function test_rejects_wrong_secret(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->signer()->parse($this->signer('other-secret')->make(['user_id' => '1']));
    }

    public function test_rejects_tampered_payload(): void
    {
        [$sig] = explode('.', $this->signer()->make(['user_id' => '111']));
        $forged = $sig.'.'.SignedRequest::base64UrlEncode(json_encode(['algorithm' => 'HMAC-SHA256', 'user_id' => '222']));

        $this->expectException(UnexpectedValueException::class);
        $this->signer()->parse($forged);
    }

    public function test_rejects_tampered_signature(): void
    {
        [, $payload] = explode('.', $this->signer()->make(['user_id' => '111']));

        $this->expectException(UnexpectedValueException::class);
        $this->signer()->parse(SignedRequest::base64UrlEncode(str_repeat('x', 32)).'.'.$payload);
    }

    public function test_rejects_unsupported_algorithm(): void
    {
        $this->expectExceptionMessage('algorithm');
        $this->signer()->parse($this->rawSigned(['algorithm' => 'HMAC-SHA1', 'user_id' => '1']));
    }

    public function test_rejects_missing_algorithm(): void
    {
        $this->expectExceptionMessage('algorithm');
        $this->signer()->parse($this->rawSigned(['user_id' => '1']));
    }

    public function test_rejects_expired_request_when_max_age_given(): void
    {
        $signed = $this->signer()->make(['user_id' => '1', 'issued_at' => time() - 3600]);

        $this->assertSame('1', $this->signer()->parse($signed, 7200)['user_id']);

        $this->expectExceptionMessage('Expired');
        $this->signer()->parse($signed, 60);
    }

    public function test_rejects_malformed_input(): void
    {
        foreach ([null, '', 'no-dot', 'a.b.c', '!!!.???', 'abc.'.SignedRequest::base64UrlEncode('not json')] as $input) {
            try {
                $this->signer()->parse($input);
                $this->fail('Expected rejection for '.var_export($input, true));
            } catch (UnexpectedValueException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_requires_app_secret(): void
    {
        $this->expectExceptionMessage('META_APP_SECRET');
        (new SignedRequest(''))->parse('a.b');
    }
}
