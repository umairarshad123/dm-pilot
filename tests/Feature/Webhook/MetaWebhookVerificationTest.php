<?php

namespace Tests\Feature\Webhook;

use Tests\TestCase;

class MetaWebhookVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['meta.verify_token' => 'my-verify-token']);
    }

    public function test_returns_challenge_for_valid_token(): void
    {
        $response = $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=my-verify-token&hub.challenge=CHALLENGE_123');

        $response->assertOk();
        $this->assertSame('CHALLENGE_123', $response->getContent());
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $response->assertCookieMissing(config('session.cookie'));
    }

    public function test_accepts_underscore_query_keys(): void
    {
        $this->get(route('meta.webhook.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'my-verify-token', 'hub_challenge' => '42']))
            ->assertOk()
            ->assertSeeText('42');
    }

    public function test_rejects_wrong_token(): void
    {
        $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc')->assertForbidden();
    }

    public function test_rejects_wrong_mode_or_missing_token(): void
    {
        $this->get('/webhooks/meta?hub.mode=unsubscribe&hub.verify_token=my-verify-token&hub.challenge=abc')->assertForbidden();
        $this->get('/webhooks/meta?hub.mode=subscribe&hub.challenge=abc')->assertForbidden();
    }

    public function test_rejects_when_verify_token_not_configured(): void
    {
        config(['meta.verify_token' => null]);

        $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=&hub.challenge=abc')->assertForbidden();
        $this->get('/webhooks/meta?hub.mode=subscribe&hub.verify_token=anything&hub.challenge=abc')->assertForbidden();
    }
}
