<?php

namespace App\Jobs;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Exceptions\AiProviderException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;
use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\PromptBuilder;
use App\Services\Automation\AutomationMatcher;
use App\Services\Automation\ReplyTemplate;
use App\Services\Bot\BotSettings;
use App\Services\Bot\BotSettingsResolver;
use App\Services\Bot\ReplyPolicy;
use App\Services\ConversationService;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the reply for one incoming customer message and sends it: the matching automation rule's
 * reply (keyword / welcome) when one matches, otherwise the AI reply from the page's provider.
 * Idempotent: the outgoing "claim" row (unique in_reply_to_id) guarantees at most one reply per message.
 */
class GenerateAndSendReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $incomingMessageId)
    {
        $this->onQueue(config('meta.queue'));
    }

    public function handle(
        ConversationService $conversations,
        BotSettingsResolver $resolver,
        ReplyPolicy $policy,
        PromptBuilder $prompts,
        AiProviderFactory $providers,
        AutomationMatcher $automations,
        MetaMessagingService $messaging,
    ): void {
        $incoming = Message::query()->with('conversation.metaAccount')->find($this->incomingMessageId);
        $conversation = $incoming?->conversation;
        $account = $conversation?->metaAccount;

        if ($incoming === null || $conversation === null || $account === null) {
            return;
        }

        $lock = Cache::lock('bot-reply:'.$incoming->id, $this->timeout + 30);

        if (! $lock->get()) {
            $this->release(15); // another worker is handling this message

            return;
        }

        try {
            $this->process($incoming, $conversation, $account, $conversations, $resolver, $policy, $prompts, $providers, $automations, $messaging);
        } finally {
            $lock->release();
        }
    }

    /** Final failure (exception / timeout after all tries): never leave the claim pending. */
    public function failed(?Throwable $exception): void
    {
        Message::query()
            ->where('in_reply_to_id', $this->incomingMessageId)
            ->where('status', MessageStatus::Pending)
            ->update(['status' => MessageStatus::Failed, 'error' => 'Reply job failed: '.class_basename($exception ?? 'unknown')]);

        Log::channel('meta')->error('Reply job failed permanently.', [
            'incoming_message_id' => $this->incomingMessageId,
            'exception' => $exception ? class_basename($exception) : null,
        ]);
    }

    private function process(
        Message $incoming,
        Conversation $conversation,
        MetaAccount $account,
        ConversationService $conversations,
        BotSettingsResolver $resolver,
        ReplyPolicy $policy,
        PromptBuilder $prompts,
        AiProviderFactory $providers,
        AutomationMatcher $automations,
        MetaMessagingService $messaging,
    ): void {
        $log = ['conversation_id' => $conversation->id, 'incoming_message_id' => $incoming->id, 'attempt' => $this->attempts()];
        $settings = $resolver->forAccount($account);

        $existing = Message::query()->where('in_reply_to_id', $incoming->id)->first();

        if ($existing !== null && $existing->status !== MessageStatus::Pending) {
            Log::channel('meta')->info('Reply already handled; skipping.', $log + ['status' => $existing->status->value]);

            return;
        }

        // Re-check: a human may have taken over, or the window closed, while this job waited.
        $decision = $policy->decide($conversation, $incoming, $settings);

        // Debounce: a newer customer message will be answered (with full context) by its own job.
        $reason = ! $decision->shouldReply ? $decision->reason : (
            $conversation->messages()->where('id', '>', $incoming->id)->where('direction', MessageDirection::Incoming)->exists()
                ? 'newer_message'
                : null
        );

        if ($reason !== null) {
            if ($existing !== null) {
                $existing->forceFill(['status' => MessageStatus::Skipped, 'error' => $reason])->save();
            }

            Log::channel('meta')->info('Bot reply skipped.', $log + ['reason' => $reason]);

            return;
        }

        $claim = $existing ?? $this->claim($incoming);

        if ($claim === null) {
            Log::channel('meta')->info('Reply already handled; skipping.', $log);

            return;
        }

        if ($this->attempts() === 1 && config('meta.sender_actions')) {
            $messaging->sendSenderAction($account, $conversation->external_user_id, 'mark_seen');
            $messaging->sendSenderAction($account, $conversation->external_user_id, 'typing_on');
        }

        // A previous attempt may already have generated the text (e.g. Meta failed transiently): reuse it.
        $text = $claim->body;

        if (blank($text)) {
            $text = $this->automationReply($incoming, $conversation, $account, $claim, $automations, $log);
        }

        if (blank($text)) {
            try {
                $text = $this->generate($conversation, $claim, $settings, $prompts, $providers);
            } catch (AiProviderException $e) {
                if ($e->retryable && $this->attempts() < $this->tries) {
                    Log::channel('openai')->warning('AI provider failed; will retry.', $log + ['error' => $e->getMessage()]);
                    $this->release($e->retryAfterSeconds ?? $this->backoffFor($this->attempts()));

                    return;
                }

                $this->handleAiFailure($claim, $conversation, $account, $settings, $e, $conversations, $messaging, $log);

                return;
            }

            $claim->forceFill([
                'body' => $text,
                'payload' => ['source' => 'ai', 'provider' => $settings->aiProvider, 'model' => $settings->model],
            ])->save();
        }

        $this->deliver($claim, $conversation, $account, $text, $conversations, $messaging, $log);
    }

    /**
     * Create (or on retry, reuse) the pending outgoing row for this incoming message.
     * Returns null when a reply was already sent / finalised.
     */
    private function claim(Message $incoming): ?Message
    {
        try {
            return Message::query()->create([
                'conversation_id' => $incoming->conversation_id,
                'direction' => MessageDirection::Outgoing,
                'sender_type' => SenderType::Bot,
                'status' => MessageStatus::Pending,
                'in_reply_to_id' => $incoming->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = Message::query()->where('in_reply_to_id', $incoming->id)->first();

            return $existing?->status === MessageStatus::Pending ? $existing : null;
        }
    }

    /**
     * Reply text of the matching automation rule (stored on the claim so a retry reuses it), or null.
     * Stored payload: {source: "automation", automation_rule_id, trigger}.
     */
    private function automationReply(
        Message $incoming,
        Conversation $conversation,
        MetaAccount $account,
        Message $claim,
        AutomationMatcher $automations,
        array $log,
    ): ?string {
        $rule = $automations->match($conversation, $incoming);

        if ($rule === null) {
            return null;
        }

        $text = ReplyTemplate::render($rule->reply_text, ReplyTemplate::variablesFor($conversation, $account));

        if ($text === '') {
            Log::channel('meta')->warning('Automation rule rendered an empty reply; using AI instead.', $log + ['automation_rule_id' => $rule->id]);

            return null;
        }

        $claim->forceFill([
            'body' => $text,
            'payload' => ['source' => 'automation', 'automation_rule_id' => $rule->id, 'trigger' => $rule->trigger],
        ])->save();
        $rule->recordTrigger();

        Log::channel('meta')->info('Automation rule matched.', $log + [
            'automation_rule_id' => $rule->id,
            'trigger' => $rule->trigger,
            'global' => $rule->isGlobal(),
        ]);

        return $text;
    }

    private function generate(
        Conversation $conversation,
        Message $claim,
        BotSettings $settings,
        PromptBuilder $prompts,
        AiProviderFactory $providers,
    ): string {
        $context = new PromptContext($settings, $conversation->platform, $conversation);

        return $providers->for($settings)->reply(
            instructions: $prompts->instructions($context),
            input: $prompts->history($conversation, $settings->historyLimit, [$claim->id]),
            model: $settings->model,
            maxOutputTokens: $settings->maxOutputTokens,
            temperature: $settings->temperature,
            logContext: ['conversation_id' => $conversation->id],
        );
    }

    private function deliver(
        Message $claim,
        Conversation $conversation,
        MetaAccount $account,
        string $text,
        ConversationService $conversations,
        MetaMessagingService $messaging,
        array $log,
    ): bool {
        $result = $messaging->sendText($account, $conversation->external_user_id, $text, MetaMessagingService::METADATA_BOT);

        if ($result->ok) {
            $conversations->markSent($claim, $result->messageId, $text);
            Log::channel('meta')->info('Bot reply sent.', $log + ['message_id' => $result->messageId]);

            return true;
        }

        if ($result->retryable && $this->attempts() < $this->tries) {
            Log::channel('meta')->warning('Bot reply send failed; will retry.', $log + [
                'code' => $result->errorCode, 'error' => $result->error,
            ]);
            $this->release($result->retryAfterSeconds ?? $this->backoffFor($this->attempts()));

            return false;
        }

        $conversations->markFailed($claim, (string) $result->error);
        Log::channel('meta')->error('Bot reply could not be delivered.', $log + [
            'code' => $result->errorCode, 'subcode' => $result->errorSubcode, 'error' => $result->error,
        ]);

        return false;
    }

    private function handleAiFailure(
        Message $claim,
        Conversation $conversation,
        MetaAccount $account,
        BotSettings $settings,
        AiProviderException $e,
        ConversationService $conversations,
        MetaMessagingService $messaging,
        array $log,
    ): void {
        Log::channel('openai')->error('AI reply failed.', $log + ['error' => $e->getMessage(), 'fallback' => $settings->fallbackMessage !== null]);

        if ($settings->fallbackMessage === null) {
            $conversations->markFailed($claim, $e->getMessage());

            return;
        }

        // Stored before sending so a retried send reuses the fallback instead of calling the AI again.
        $claim->forceFill(['body' => $settings->fallbackMessage])->save();

        if ($this->deliver($claim, $conversation, $account, $settings->fallbackMessage, $conversations, $messaging, $log)) {
            $claim->forceFill(['error' => 'AI failed; fallback sent: '.mb_substr($e->getMessage(), 0, 500)])->save();
        }
    }

    private function backoffFor(int $attempt): int
    {
        return $this->backoff[min($attempt - 1, count($this->backoff) - 1)];
    }
}
