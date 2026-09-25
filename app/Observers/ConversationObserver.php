<?php

namespace App\Observers;

use App\Enums\LeadStage;
use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * - creating: new contacts start in lead stage New.
 * - created:  queue FetchContactProfile (after commit) when contacts.fetch_profiles is on. Skipped on the
 *   "sync" queue unless contacts.fetch_profiles_on_sync_queue (it would run inline before the bot reply).
 */
class ConversationObserver
{
    public function creating(Conversation $conversation): void
    {
        $conversation->lead_stage ??= LeadStage::New;
    }

    public function created(Conversation $conversation): void
    {
        if (! self::shouldFetchProfiles()) {
            return;
        }

        try {
            FetchContactProfile::dispatch($conversation->id)->afterCommit();
        } catch (Throwable $e) {
            Log::channel('meta')->info('Could not queue contact profile fetch.', [
                'conversation_id' => $conversation->id, 'exception' => class_basename($e),
            ]);
        }
    }

    public static function shouldFetchProfiles(): bool
    {
        if (! config('contacts.fetch_profiles', true)) {
            return false;
        }

        $connection = config('queue.default');
        $driver = config("queue.connections.{$connection}.driver", $connection);

        return $driver !== 'sync' || (bool) config('contacts.fetch_profiles_on_sync_queue', false);
    }
}
