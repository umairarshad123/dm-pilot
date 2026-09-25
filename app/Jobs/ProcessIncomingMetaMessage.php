<?php

namespace App\Jobs;

use App\Data\IncomingMetaMessage;
use App\Models\MetaAccount;
use App\Services\Bot\BotSettingsResolver;
use App\Services\Bot\ReplyPolicy;
use App\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched by the webhook layer with one normalized message:
 *   ProcessIncomingMetaMessage::dispatch($incomingMetaMessage);
 * Stores it (deduplicated on the Meta mid), handles echoes, and schedules the bot reply.
 */
class ProcessIncomingMetaMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30];

    public function __construct(public IncomingMetaMessage $message)
    {
        $this->onQueue(config('meta.queue'));
    }

    public function handle(ConversationService $conversations, BotSettingsResolver $resolver, ReplyPolicy $policy): void
    {
        $incoming = $this->message;
        $context = [
            'platform' => $incoming->platform->value,
            'account_external_id' => $incoming->accountExternalId,
            'mid' => $incoming->messageId,
            'webhook_event_id' => $incoming->webhookEventId,
        ];

        $account = MetaAccount::findForWebhook($incoming->platform, $incoming->accountExternalId);

        if ($account === null) {
            Log::channel('meta')->warning('No active account for incoming message; ignored.', $context);

            return;
        }

        if ($incoming->isEcho) {
            $conversations->recordEcho($account, $incoming);

            return;
        }

        if ($incoming->senderId === $account->ownExternalId()) {
            Log::channel('meta')->debug('Message from our own account (not an echo); ignored.', $context);

            return;
        }

        $conversation = $conversations->findOrCreateConversation($account, $incoming->platform, $incoming->customerId());
        $message = $conversations->storeIncoming($conversation, $incoming);

        if ($message === null) {
            Log::channel('meta')->info('Duplicate message delivery ignored.', $context);

            return;
        }

        $settings = $resolver->forAccount($account);
        $decision = $policy->decide($conversation, $message, $settings);

        if (! $decision->shouldReply) {
            Log::channel('meta')->info('Bot reply skipped.', $context + [
                'conversation_id' => $conversation->id, 'reason' => $decision->reason,
            ]);

            return;
        }

        GenerateAndSendReply::dispatch($message->id)
            ->delay($settings->replyDelaySeconds > 0 ? $settings->replyDelaySeconds : null);
    }
}
