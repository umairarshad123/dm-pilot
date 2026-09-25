<?php

namespace App\Jobs;

use App\Exceptions\MetaApiException;
use App\Models\Conversation;
use App\Services\Contacts\ContactProfileService;
use App\Services\Meta\MetaGraphClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Enrich a contact with its Meta profile (name, IG username, profile picture).
 *   FetchContactProfile::dispatch($conversation->id);             // skipped when fetched within the TTL
 *   FetchContactProfile::dispatch($conversation->id, force: true); // always re-fetch
 * Dispatched automatically by ConversationObserver when a conversation is created.
 * Never fails loudly: privacy/permission errors are logged at info; only transient errors are retried.
 */
class FetchContactProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 30;

    public function __construct(public int $conversationId, public bool $force = false)
    {
        $this->onQueue(config('meta.queue'));
    }

    public function handle(ContactProfileService $profiles): void
    {
        $conversation = Conversation::query()->with('metaAccount')->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        $ttl = (int) config('contacts.profile_ttl_hours', 168);

        if (! $this->force && $conversation->profile_fetched_at !== null && $conversation->profile_fetched_at->gt(now()->subHours($ttl))) {
            return;
        }

        try {
            $profiles->fetch($conversation);
        } catch (MetaApiException $e) {
            if ($this->attempts() < $this->tries) {
                $this->release($e->retryAfterSeconds ?? $this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);

                return;
            }

            $this->logGiveUp($e);
        } catch (Throwable $e) {
            $this->logGiveUp($e);
        }
    }

    private function logGiveUp(Throwable $e): void
    {
        Log::channel('meta')->info('Contact profile fetch gave up.', [
            'conversation_id' => $this->conversationId,
            'exception' => class_basename($e),
            'error' => MetaGraphClient::scrub($e->getMessage()),
        ]);
    }
}
