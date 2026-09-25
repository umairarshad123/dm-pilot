<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiProviderFactory;
use App\Services\Automation\AutomationMatcher;

/** Decides whether the bot may auto-reply to an incoming customer message. */
class ReplyPolicy
{
    public function __construct(
        private readonly AiProviderFactory $providers,
        private readonly AutomationMatcher $automations,
    ) {}

    public function decide(Conversation $conversation, Message $incoming, BotSettings $settings): ReplyDecision
    {
        $account = $conversation->metaAccount;

        return match (true) {
            $account === null || ! $account->active => ReplyDecision::skip('account_inactive'),
            ! $settings->botEnabled => ReplyDecision::skip('bot_disabled'),
            ! $conversation->bot_enabled => ReplyDecision::skip('conversation_bot_disabled'),
            $conversation->human_takeover => ReplyDecision::skip('human_takeover'),
            $conversation->status !== 'open' => ReplyDecision::skip('conversation_closed'),
            $conversation->isPaused() => ReplyDecision::skip('bot_paused'),
            ! $conversation->botCanReply() => ReplyDecision::skip('conversation_rules'),
            ! $conversation->withinMessagingWindow() => ReplyDecision::skip('outside_messaging_window'),
            // Automation replies need no AI: only block when no rule would answer this message.
            ! $this->providers->for($settings)->isConfigured()
                && $this->automations->match($conversation, $incoming) === null => ReplyDecision::skip('ai_not_configured'),
            blank($incoming->body) && empty($incoming->attachments) => ReplyDecision::skip('empty_message'),
            default => ReplyDecision::reply(),
        };
    }
}
