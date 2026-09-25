<?php

namespace Tests\Feature\Webhook;

use App\Jobs\ProcessIncomingMetaMessage;
use App\Jobs\ProcessMetaWebhookEvent;
use App\Models\WebhookEvent;
use App\Services\Meta\MetaWebhookParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ProcessMetaWebhookEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['meta.queue' => 'meta']);
        Queue::fake();
    }

    private function makeEvent(string $fixture): WebhookEvent
    {
        $body = file_get_contents(base_path("tests/Fixtures/meta/{$fixture}.json"));
        $payload = json_decode($body, true);

        return WebhookEvent::create([
            'object' => $payload['object'],
            'payload_hash' => hash('sha256', $body),
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    public function test_dispatches_one_job_per_message_and_marks_processed(): void
    {
        $event = $this->makeEvent('mixed_batch');

        (new ProcessMetaWebhookEvent($event->id))->handle(new MetaWebhookParser);

        Queue::assertPushedTimes(ProcessIncomingMetaMessage::class, 2);
        Queue::assertPushedOn('meta', ProcessIncomingMetaMessage::class,
            fn (ProcessIncomingMetaMessage $job) => $job->message->messageId === 'm_1' && $job->message->webhookEventId === $event->id);

        $event->refresh();
        $this->assertSame('processed', $event->status);
        $this->assertSame(2, $event->messages_count);
        $this->assertNotNull($event->processed_at);
    }

    public function test_event_without_messages_is_marked_ignored(): void
    {
        $event = WebhookEvent::create([
            'object' => 'page',
            'payload_hash' => str_repeat('a', 64),
            'payload' => ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [['read' => ['watermark' => 1]]]]]],
        ]);

        (new ProcessMetaWebhookEvent($event->id))->handle(new MetaWebhookParser);

        Queue::assertNothingPushed();
        $this->assertSame('ignored', $event->fresh()->status);
    }

    public function test_already_processed_event_is_not_reprocessed(): void
    {
        $event = $this->makeEvent('messenger_text');
        $event->update(['status' => 'processed']);

        (new ProcessMetaWebhookEvent($event->id))->handle(new MetaWebhookParser);

        Queue::assertNothingPushed();
    }

    public function test_missing_event_is_a_no_op(): void
    {
        (new ProcessMetaWebhookEvent(999))->handle(new MetaWebhookParser);

        Queue::assertNothingPushed();
    }

    public function test_exception_marks_event_failed_and_rethrows(): void
    {
        $event = $this->makeEvent('messenger_text');
        $parser = new class extends MetaWebhookParser
        {
            public function parse(array $payload, ?int $webhookEventId = null): array
            {
                throw new RuntimeException('boom');
            }
        };

        try {
            (new ProcessMetaWebhookEvent($event->id))->handle($parser);
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $event->refresh();
        $this->assertSame('failed', $event->status);
        $this->assertStringContainsString('boom', $event->error);
    }

    public function test_job_is_configured_for_retries(): void
    {
        $job = new ProcessMetaWebhookEvent(1);

        $this->assertSame(3, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }
}
