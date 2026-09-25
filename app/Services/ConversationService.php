<?php

namespace App\Services;

use App\Data\IncomingMetaMessage;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\Platform;
use App\Enums\SenderType;
use App\Exceptions\MetaApiException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Services\Bot\BotSettingsResolver;
use App\Services\Meta\MetaMessagingService;
use App\Support\PayloadSanitizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Conversation / message persistence and bot-state rules.
 */
class ConversationService
{
    public function __construct(
        private readonly MetaMessagingService $messaging,
        private readonly BotSettingsResolver $settings,
    ) {}

    /**
     * Send a manual reply from an admin through Meta, store it (sender_type=human) and pause the bot per settings.
     *
     * @throws MetaApiException when the account is unusable or Meta rejects the message (message is admin-safe)
     */
    public function sendHumanReply(Conversation $conversation, string $text, ?User $user = null): Message
    {
        $text = trim($text);
        $account = $conversation->metaAccount;

        if ($text === '') {
            throw new MetaApiException('Cannot send an empty message.');
        }

        if ($account === null || ! $account->active) {
            throw new MetaApiException('The Meta account for this conversation is missing or inactive.');
        }

        // Stored as pending first so the echo webhook for this send is recognised as ours.
        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Outgoing,
            'sender_type' => SenderType::Human,
            'body' => $text,
            'status' => MessageStatus::Pending,
            'payload' => ['sent_by_user_id' => $user?->id],
        ]);

        $result = $this->messaging->sendText($account, $conversation->external_user_id, $text, MetaMessagingService::METADATA_HUMAN);

        if (! $result->ok) {
            $this->markFailed($message, (string) $result->error);

            throw MetaApiException::fromSendResult($result);
        }

        $this->markSent($message, $result->messageId);
        $this->pauseForHuman($conversation, $account);

        Log::channel('meta')->info('Human reply sent from admin.', [
            'conversation_id' => $conversation->id, 'message_id' => $message->id, 'user_id' => $user?->id,
        ]);

        return $message->refresh();
    }

    public function setBotEnabled(Conversation $conversation, bool $enabled): Conversation
    {
        $conversation->forceFill(['bot_enabled' => $enabled])->save();

        return $conversation;
    }

    /** Turn human takeover on/off. Turning it off also clears any timed pause. */
    public function setHumanTakeover(Conversation $conversation, bool $enabled): Conversation
    {
        $conversation->forceFill([
            'human_takeover' => $enabled,
            'bot_paused_until' => $enabled ? $conversation->bot_paused_until : null,
        ])->save();

        return $conversation;
    }

    /** Race-safe find-or-create on (meta_account_id, external_user_id). */
    public function findOrCreateConversation(MetaAccount $account, Platform $platform, string $externalUserId): Conversation
    {
        $conversation = Conversation::query()->createOrFirst(
            ['meta_account_id' => $account->id, 'external_user_id' => $externalUserId],
            ['platform' => $platform],
        );

        if ($conversation->wasRecentlyCreated) {
            $conversation->refresh(); // load DB defaults (status, bot_enabled, …)
        }

        return $conversation->setRelation('metaAccount', $account);
    }

    /** Store an incoming customer message. Returns null when this mid was already stored (duplicate delivery). */
    public function storeIncoming(Conversation $conversation, IncomingMetaMessage $incoming): ?Message
    {
        $sentAt = $this->timestamp($incoming);

        try {
            $message = $conversation->messages()->create([
                'external_message_id' => $incoming->messageId,
                'direction' => MessageDirection::Incoming,
                'sender_type' => SenderType::Customer,
                'body' => $incoming->text,
                'attachments' => $incoming->attachments ?: null,
                'payload' => PayloadSanitizer::sanitize($incoming->raw),
                'status' => MessageStatus::Received,
                'sent_at' => $sentAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $lastCustomer = $conversation->last_customer_message_at;
        $conversation->forceFill([
            'last_message_at' => $this->later($conversation->last_message_at, $sentAt),
            'last_customer_message_at' => $this->later($lastCustomer, $sentAt),
        ])->save();

        return $message;
    }

    /**
     * Handle an echo (a message sent BY our page/account). Our own bot/admin sends are already stored and
     * ignored; other sends through our app are stored as bot; anything else is a human replying from the
     * Page Inbox / Business Suite / IG app, which is stored and pauses the bot.
     */
    public function recordEcho(MetaAccount $account, IncomingMetaMessage $echo): ?Message
    {
        if (Message::query()->where('external_message_id', $echo->messageId)->exists()) {
            return null;
        }

        $conversation = $this->findOrCreateConversation($account, $echo->platform, $echo->customerId());

        if ($this->isOwnSend($conversation, $echo)) {
            return null;
        }

        $fromOurApp = $echo->appId !== null && filled(config('meta.app_id')) && $echo->appId === (string) config('meta.app_id');
        $sentAt = $this->timestamp($echo);

        try {
            $message = $conversation->messages()->create([
                'external_message_id' => $echo->messageId,
                'direction' => MessageDirection::Outgoing,
                'sender_type' => $fromOurApp ? SenderType::Bot : SenderType::Human,
                'body' => $echo->text,
                'attachments' => $echo->attachments ?: null,
                'payload' => PayloadSanitizer::sanitize($echo->raw),
                'status' => MessageStatus::Sent,
                'sent_at' => $sentAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $conversation->forceFill(['last_message_at' => $this->later($conversation->last_message_at, $sentAt)])->save();

        if (! $fromOurApp) {
            $minutes = $this->pauseForHuman($conversation, $account);

            Log::channel('meta')->info('Human reply detected outside the app; bot paused.', [
                'conversation_id' => $conversation->id, 'app_id' => $echo->appId, 'pause_minutes' => $minutes,
            ]);
        }

        return $message;
    }

    /** Mark an outgoing row as delivered. Resolves the race where its echo was stored first. */
    public function markSent(Message $message, ?string $externalId, ?string $body = null): Message
    {
        $message->forceFill(array_filter([
            'external_message_id' => $externalId,
            'body' => $body,
        ], fn ($v) => $v !== null) + [
            'status' => MessageStatus::Sent,
            'error' => null,
            'sent_at' => Carbon::now(),
        ]);

        try {
            $message->save();
        } catch (UniqueConstraintViolationException) {
            Message::query()
                ->where('external_message_id', $externalId)
                ->whereKeyNot($message->id)
                ->whereNull('in_reply_to_id')
                ->where('direction', MessageDirection::Outgoing)
                ->delete();
            $message->save();
        }

        $conversation = $message->conversation;
        $conversation->forceFill(['last_message_at' => Carbon::now()])->save();

        return $message;
    }

    public function markFailed(Message $message, string $error): Message
    {
        $message->forceFill(['status' => MessageStatus::Failed, 'error' => mb_substr($error, 0, 1000)])->save();

        return $message;
    }

    private function pauseForHuman(Conversation $conversation, MetaAccount $account): int
    {
        $minutes = $this->settings->forAccount($account)->humanTakeoverMinutes;

        if ($minutes > 0) {
            $conversation->pauseBotFor($minutes);
        }

        return $minutes;
    }

    /** Echo of something our own pipeline sent (stored/being stored by the sender)? */
    private function isOwnSend(Conversation $conversation, IncomingMetaMessage $echo): bool
    {
        $metadata = $echo->raw['message']['metadata'] ?? null;

        if (is_string($metadata) && str_starts_with($metadata, 'chatbot:')) {
            return true;
        }

        $recent = $conversation->messages()
            ->where('direction', MessageDirection::Outgoing)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->latest('id')
            ->limit(10)
            ->get();

        $text = trim((string) $echo->text);

        return $recent->contains(fn (Message $m) => ($m->status === MessageStatus::Pending && $m->created_at->gte(Carbon::now()->subMinutes(2)))
            || ($text !== '' && $m->status === MessageStatus::Sent && str_contains((string) $m->body, $text)));
    }

    private function timestamp(IncomingMetaMessage $message): Carbon
    {
        return $message->timestampMs > 0 ? Carbon::createFromTimestampMs($message->timestampMs, config('app.timezone')) : Carbon::now();
    }

    private function later(?Carbon $current, Carbon $candidate): Carbon
    {
        return $current !== null && $current->gt($candidate) ? $current : $candidate;
    }
}
