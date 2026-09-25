<?php

namespace App\Services\Contacts;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hard-deletes contacts (a Conversation row + all its messages). Shared by the Contacts page (single + bulk
 * delete, e.g. a customer's "delete my data" request) and the `contacts:prune` retention command.
 *
 * - Messages are deleted in id batches to keep lock times short, then the conversation row.
 * - Manual deletes also purge raw webhook events that contain the customer's PSID/IGSID (they hold message
 *   text too). Retention pruning skips that: webhook events have their own, shorter retention.
 * - Every delete writes an audit line to the `meta` log channel with ids and counts only: never message
 *   content, names, emails, phones or the customer's platform id.
 */
class ContactDeletionService
{
    public const BATCH = 1000;

    /**
     * Delete one contact and everything stored about it.
     *
     * @param  string  $reason  manual | bulk | retention (audit only)
     * @return array{conversations: int, messages: int, webhook_events: int}
     */
    public function delete(Conversation $conversation, string $reason = 'manual', ?int $actorId = null, bool $purgeWebhookEvents = true): array
    {
        $externalId = (string) $conversation->external_user_id;

        $counts = DB::transaction(function () use ($conversation) {
            $messages = $this->deleteMessagesOf([$conversation->id]);
            $conversations = Conversation::query()->whereKey($conversation->id)->delete();

            return ['conversations' => $conversations, 'messages' => $messages];
        });

        $counts['webhook_events'] = $purgeWebhookEvents ? $this->purgeWebhookEvents($externalId) : 0;

        Log::channel('meta')->info('Contact deleted.', [
            'reason' => $reason,
            'conversation_id' => $conversation->id,
            'meta_account_id' => $conversation->meta_account_id,
            'platform' => $conversation->platform?->value,
            'messages_deleted' => $counts['messages'],
            'webhook_events_deleted' => $counts['webhook_events'],
            'actor_user_id' => $actorId,
        ]);

        return $counts;
    }

    /**
     * Delete every contact matched by the query (processed in id chunks).
     *
     * @return array{conversations: int, messages: int, webhook_events: int}
     */
    public function deleteMatching(Builder $query, string $reason = 'bulk', ?int $actorId = null, bool $purgeWebhookEvents = true): array
    {
        $totals = ['conversations' => 0, 'messages' => 0, 'webhook_events' => 0];

        // Collect ids first: deleting while paging through the same query would skip rows.
        $ids = (clone $query)->reorder()->pluck('conversations.id');

        foreach ($ids->chunk(200) as $chunk) {
            Conversation::query()->whereKey($chunk->all())->get()->each(function (Conversation $conversation) use (&$totals, $reason, $actorId, $purgeWebhookEvents) {
                foreach ($this->delete($conversation, $reason, $actorId, $purgeWebhookEvents) as $key => $n) {
                    $totals[$key] += $n;
                }
            });
        }

        return $totals;
    }

    /** Contacts whose last activity is before the cutoff (never-active rows fall back to created_at). */
    public function staleQuery(Carbon $cutoff): Builder
    {
        return Conversation::query()->where(function (Builder $q) use ($cutoff) {
            $q->where('last_message_at', '<', $cutoff)
                ->orWhere(fn (Builder $n) => $n->whereNull('last_message_at')->where('created_at', '<', $cutoff));
        });
    }

    /**
     * Retention pruning: delete contacts inactive since before the cutoff.
     *
     * @return array{conversations: int, messages: int}
     */
    public function prune(Carbon $cutoff, bool $dryRun = false, int $chunk = 200): array
    {
        $query = $this->staleQuery($cutoff);

        if ($dryRun) {
            return [
                'conversations' => (clone $query)->count(),
                'messages' => Message::query()->whereIn('conversation_id', (clone $query)->select('id'))->count(),
            ];
        }

        $totals = ['conversations' => 0, 'messages' => 0];

        do {
            $ids = (clone $query)->orderBy('id')->limit(max(1, $chunk))->pluck('id')->all();

            if ($ids === []) {
                break;
            }

            $deleted = DB::transaction(fn () => [
                'messages' => $this->deleteMessagesOf($ids),
                'conversations' => Conversation::query()->whereKey($ids)->delete(),
            ]);

            $totals['messages'] += $deleted['messages'];
            $totals['conversations'] += $deleted['conversations'];
        } while (count($ids) > 0 && $deleted['conversations'] > 0);

        Log::channel('meta')->info('Contacts pruned (retention).', [
            'reason' => 'retention',
            'cutoff' => $cutoff->toIso8601String(),
            'conversations_deleted' => $totals['conversations'],
            'messages_deleted' => $totals['messages'],
        ]);

        return $totals;
    }

    /** @param  list<int>  $conversationIds */
    private function deleteMessagesOf(array $conversationIds): int
    {
        $deleted = 0;

        do {
            $ids = Message::query()->whereIn('conversation_id', $conversationIds)->orderBy('id')->limit(self::BATCH)->pluck('id');
            $count = $ids->isEmpty() ? 0 : Message::query()->whereKey($ids->all())->delete();
            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }

    /** Raw webhook payloads store Meta ids as JSON strings; only purely numeric ids are matched (keeps LIKE safe). */
    private function purgeWebhookEvents(string $externalId): int
    {
        if ($externalId === '' || ! ctype_digit($externalId)) {
            return 0;
        }

        return WebhookEvent::query()->where('payload', 'like', '%"'.$externalId.'"%')->delete();
    }
}
