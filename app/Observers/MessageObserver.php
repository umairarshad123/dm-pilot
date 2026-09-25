<?php

namespace App\Observers;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Contacts\LeadCaptureService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Contact bookkeeping on new messages. Never throws: a failure here must not break the reply pipeline.
 *
 * - Incoming customer message: unread_count + 1 (atomic query update) and email/phone lead capture.
 * - Outgoing human message (admin reply, Page Inbox, IG app): unread_count reset to 0 (a human saw it).
 * Timestamps (last_message_at, last_customer_message_at) stay owned by ConversationService.
 */
class MessageObserver
{
    public function created(Message $message): void
    {
        try {
            if ($message->direction === MessageDirection::Incoming && $message->sender_type === SenderType::Customer) {
                Conversation::query()->whereKey($message->conversation_id)->increment('unread_count');

                if (filled($message->body)) {
                    app(LeadCaptureService::class)->captureFromMessage($message);
                }

                return;
            }

            if ($message->direction === MessageDirection::Outgoing
                && $message->sender_type === SenderType::Human
                && $message->status !== MessageStatus::Failed) {
                Conversation::query()->whereKey($message->conversation_id)->where('unread_count', '>', 0)->update(['unread_count' => 0]);
            }
        } catch (Throwable $e) {
            Log::warning('Contact bookkeeping for message failed.', [
                'message_id' => $message->id, 'conversation_id' => $message->conversation_id,
                'exception' => class_basename($e), 'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }
}
