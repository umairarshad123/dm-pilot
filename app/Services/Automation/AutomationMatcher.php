<?php

namespace App\Services\Automation;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use Closure;

/**
 * Picks the automation rule (if any) whose reply replaces the AI reply for an incoming message.
 *
 * Order: keyword rules first (page-specific before global, then priority desc, then oldest), matched against
 * the message text AND the postback / quick-reply payload. If no keyword rule matches and this is the
 * customer's first contact (we have never sent them a message, or they tapped "Get Started"), the
 * welcome rule applies (page-specific overrides global).
 *
 * Read-only: never writes (see AutomationRule::recordTrigger()).
 */
class AutomationMatcher
{
    public function match(Conversation $conversation, Message $incoming): ?AutomationRule
    {
        $payload = self::postbackPayload($incoming);

        return $this->matchText(
            $conversation->metaAccount,
            $incoming->body,
            $payload,
            fn () => $this->isFirstContact($conversation, $payload),
        );
    }

    /**
     * Lower-level variant used by the bot playground (no DB conversation needed).
     *
     * @param  bool|Closure(): bool  $firstContact  lazily evaluated only when a welcome rule exists
     */
    public function matchText(?MetaAccount $account, ?string $text, ?string $payload = null, bool|Closure $firstContact = false): ?AutomationRule
    {
        if (! filter_var(config('bot.automations.enabled', true), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $rules = AutomationRule::query()->active()->applicableTo($account)->inMatchOrder()->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $candidates = array_values(array_filter([(string) $text, (string) $payload], fn (string $v) => trim($v) !== ''));

        foreach ($rules->where('trigger', AutomationRule::TRIGGER_KEYWORD) as $rule) {
            $keywords = $rule->normalizedKeywords();

            foreach ($candidates as $candidate) {
                if (KeywordMatcher::matches($rule->match_type, $keywords, $candidate)) {
                    return $rule;
                }
            }
        }

        $welcome = $rules->firstWhere('trigger', AutomationRule::TRIGGER_WELCOME);

        if ($welcome === null) {
            return null;
        }

        $isFirst = self::isGetStarted($payload) || ($firstContact instanceof Closure ? $firstContact() : $firstContact);

        return $isFirst ? $welcome : null;
    }

    /** True until we have delivered at least one message (bot or human) to this customer. */
    public function isFirstContact(Conversation $conversation, ?string $payload = null): bool
    {
        if (self::isGetStarted($payload)) {
            return true;
        }

        return $conversation->messages()
            ->where('direction', MessageDirection::Outgoing)
            ->where('status', MessageStatus::Sent)
            ->doesntExist();
    }

    public static function isGetStarted(?string $payload): bool
    {
        return $payload !== null && $payload === (string) config('bot.automations.get_started_payload', 'GET_STARTED');
    }

    /** Button payload of a postback (Get Started, ice breaker, button) or a quick reply, from the stored raw event. */
    public static function postbackPayload(Message $message): ?string
    {
        $raw = (array) $message->payload;
        $value = $raw['postback']['payload'] ?? $raw['message']['quick_reply']['payload'] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}
