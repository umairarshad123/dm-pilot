<?php

namespace Tests\Unit\Webhook;

use App\Support\PayloadSanitizer;
use PHPUnit\Framework\TestCase;

class PayloadSanitizerTest extends TestCase
{
    public function test_redacts_sensitive_keys_recursively(): void
    {
        $clean = PayloadSanitizer::sanitize([
            'access_token' => 'EAAB...',
            'nested' => ['page_access_token' => 'x', 'App_Secret' => 'y', 'password' => 'z', 'keep' => 'ok'],
            'list' => [['token' => 't', 'text' => 'hello']],
            'payload' => ['url' => 'https://x'],
        ]);

        $this->assertSame(PayloadSanitizer::REDACTED, $clean['access_token']);
        $this->assertSame(PayloadSanitizer::REDACTED, $clean['nested']['page_access_token']);
        $this->assertSame(PayloadSanitizer::REDACTED, $clean['nested']['App_Secret']);
        $this->assertSame(PayloadSanitizer::REDACTED, $clean['nested']['password']);
        $this->assertSame('ok', $clean['nested']['keep']);
        $this->assertSame(PayloadSanitizer::REDACTED, $clean['list'][0]['token']);
        $this->assertSame('hello', $clean['list'][0]['text']);
        $this->assertSame(['url' => 'https://x'], $clean['payload']);
    }

    public function test_truncates_long_strings_for_logs_only(): void
    {
        $long = str_repeat('a', 500);

        $this->assertSame($long, PayloadSanitizer::sanitize(['text' => $long])['text']);
        $truncated = PayloadSanitizer::forLog(['text' => $long], 10)['text'];
        $this->assertStringStartsWith('aaaaaaaaaa…', $truncated);
        $this->assertStringContainsString('500 chars', $truncated);
        $this->assertSame(5, PayloadSanitizer::forLog(5));
    }
}
