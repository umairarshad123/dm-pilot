<?php

namespace App\Services\Contacts;

use App\Enums\LeadStage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saves emails / phone numbers found in customer messages onto the contact (conversation).
 *
 * - conversations.email / phone are only filled when empty (manual edits are never overwritten).
 * - Every distinct value found is appended to meta['captured'] (history, max 50 entries):
 *   [{type: "email"|"phone", value, message_id, at: ISO-8601}].
 * - First capture sets lead_captured_at; a contact in stage New (or NULL) moves to Qualified,
 *   since sharing reachable contact details is a buying signal. Later stages are never downgraded.
 */
class LeadCaptureService
{
    public const MAX_HISTORY = 50;

    public function __construct(private readonly LeadExtractor $extractor) {}

    /** @return array{emails: list<string>, phones: list<string>} what was found in the message */
    public function captureFromMessage(Message $message): array
    {
        $found = $this->extractor->extract($message->body);

        if ($found['emails'] === [] && $found['phones'] === []) {
            return $found;
        }

        DB::transaction(function () use ($message, $found) {
            // Fresh, locked row: never works on (or clobbers) a stale in-memory instance of the pipeline.
            $conversation = Conversation::query()->lockForUpdate()->find($message->conversation_id);

            if ($conversation === null) {
                return;
            }

            $meta = (array) ($conversation->meta ?? []);
            $history = array_values((array) ($meta['captured'] ?? []));
            $known = array_map(fn ($e) => ($e['type'] ?? '').':'.($e['value'] ?? ''), $history);
            $now = Carbon::now()->toIso8601String();
            $changes = [];

            foreach (['email' => $found['emails'], 'phone' => $found['phones']] as $type => $values) {
                foreach ($values as $value) {
                    if (! in_array($type.':'.$value, $known, true)) {
                        $history[] = ['type' => $type, 'value' => $value, 'message_id' => $message->id, 'at' => $now];
                        $known[] = $type.':'.$value;
                    }
                }

                if ($values !== [] && blank($conversation->{$type})) {
                    $changes[$type] = $values[0];
                }
            }

            $meta['captured'] = array_slice($history, -self::MAX_HISTORY);
            $changes['meta'] = $meta;

            if ($conversation->lead_captured_at === null) {
                $changes['lead_captured_at'] = Carbon::now();
            }

            if ($conversation->leadStage() === LeadStage::New) {
                $changes['lead_stage'] = LeadStage::Qualified;
            }

            $conversation->forceFill($changes)->save();
        });

        return $found;
    }
}
