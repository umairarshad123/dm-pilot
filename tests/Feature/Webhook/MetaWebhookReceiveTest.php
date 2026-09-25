<?php

namespace Tests\Feature\Webhook;

use App\Jobs\ProcessMetaWebhookEvent;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MetaWebhookReceiveTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['meta.app_secret' => self::SECRET, 'meta.verify_signature' => true, 'meta.queue' => 'meta']);
        Queue::fake();
    }

    private function fixtureBody(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/meta/{$name}.json"));
    }

    private function postRaw(string $body, ?string $signature = 'auto'): TestResponse
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => '*/*'];

        if ($signature === 'auto') {
            $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);
        }

        if ($signature !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = $signature;
        }

        return $this->call('POST', '/webhooks/meta', [], [], [], $headers, $body);
    }

    public function test_valid_signature_stores_event_and_dispatches_job(): void
    {
        $response = $this->postRaw($this->fixtureBody('messenger_text'));

        $response->assertOk();
        $this->assertSame('EVENT_RECEIVED', $response->getContent());
        $response->assertCookieMissing(config('session.cookie'));

        $event = WebhookEvent::sole();
        $this->assertSame('page', $event->object);
        $this->assertSame('pending', $event->status);
        $this->assertSame(hash('sha256', $this->fixtureBody('messenger_text')), $event->payload_hash);
        $this->assertSame('m_messenger_text_1', $event->payload['entry'][0]['messaging'][0]['message']['mid']);

        Queue::assertPushedOn('meta', ProcessMetaWebhookEvent::class, fn (ProcessMetaWebhookEvent $job) => $job->webhookEventId === $event->id);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->postRaw($this->fixtureBody('messenger_text'), 'sha256='.str_repeat('0', 64))->assertForbidden();

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_signature_over_different_body_is_rejected(): void
    {
        $sig = 'sha256='.hash_hmac('sha256', '{"object":"page","entry":[]}', self::SECRET);

        $this->postRaw($this->fixtureBody('messenger_text'), $sig)->assertForbidden();
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postRaw($this->fixtureBody('messenger_text'), null)->assertForbidden();

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_rejected_when_app_secret_not_configured(): void
    {
        config(['meta.app_secret' => '']);
        $body = $this->fixtureBody('messenger_text');

        $this->postRaw($body, 'sha256='.hash_hmac('sha256', $body, ''))->assertForbidden();
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_signature_check_can_be_disabled(): void
    {
        config(['meta.verify_signature' => false, 'meta.app_secret' => null]);

        $this->postRaw($this->fixtureBody('instagram_text'), null)->assertOk();

        $this->assertSame('instagram', WebhookEvent::sole()->object);
        Queue::assertPushed(ProcessMetaWebhookEvent::class);
    }

    public function test_duplicate_delivery_is_ignored(): void
    {
        $body = $this->fixtureBody('messenger_text');

        $this->postRaw($body)->assertOk();
        $this->postRaw($body)->assertOk()->assertSeeText('EVENT_RECEIVED');

        $this->assertSame(1, WebhookEvent::count());
        Queue::assertPushedTimes(ProcessMetaWebhookEvent::class, 1);
    }

    public function test_unsupported_object_is_stored_as_ignored(): void
    {
        $this->postRaw(json_encode(['object' => 'whatsapp_business_account', 'entry' => []]))->assertOk();

        $event = WebhookEvent::sole();
        $this->assertSame('ignored', $event->status);
        $this->assertNotNull($event->processed_at);
        Queue::assertNothingPushed();
    }

    public function test_garbage_body_returns_400(): void
    {
        $this->postRaw('not json')->assertStatus(400);
        $this->postRaw('[1,2,3]')->assertStatus(400);
        $this->postRaw('{"entry":[]}')->assertStatus(400);

        $this->assertSame(0, WebhookEvent::count());
        Queue::assertNothingPushed();
    }

    public function test_stored_payload_is_sanitized(): void
    {
        $this->postRaw(json_encode(['object' => 'page', 'entry' => [['id' => 'P', 'access_token' => 'SECRET_VALUE']]]))->assertOk();

        $this->assertSame('[REDACTED]', WebhookEvent::sole()->payload['entry'][0]['access_token']);
    }
}
