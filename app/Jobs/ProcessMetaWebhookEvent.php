<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Meta\MetaWebhookParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parses a stored webhook event into normalized messages and fans them out to ProcessIncomingMetaMessage.
 */
class ProcessMetaWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public int $webhookEventId) {}

    public function handle(MetaWebhookParser $parser): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if ($event === null) {
            Log::channel('meta')->warning('Webhook event not found.', ['webhook_event_id' => $this->webhookEventId]);

            return;
        }

        if (in_array($event->status, ['processed', 'ignored'], true)) {
            return;
        }

        try {
            $messages = $parser->parse(is_array($event->payload) ? $event->payload : [], $event->id);

            foreach ($messages as $message) {
                ProcessIncomingMetaMessage::dispatch($message)->onQueue(config('meta.queue'));
            }

            $event->forceFill([
                'status' => count($messages) > 0 ? 'processed' : 'ignored',
                'messages_count' => count($messages),
                'error' => null,
                'processed_at' => now(),
            ])->save();

            Log::channel('meta')->info('Webhook event processed.', [
                'webhook_event_id' => $event->id,
                'messages' => count($messages),
            ]);
        } catch (Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'error' => Str::limit(get_class($e).': '.$e->getMessage(), 1000),
            ])->save();

            Log::channel('meta')->error('Webhook event processing failed.', [
                'webhook_event_id' => $event->id,
                'exception' => get_class($e),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }
}
