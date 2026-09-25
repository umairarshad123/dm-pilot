<?php

namespace App\Support\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\Platform;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Bot\BotSettingsResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * JSON shapes for Live Chat (page boot + admin.api.conversations.*).
 *
 * All values are plain data; the browser renders them with x-text / attribute bindings (never as HTML),
 * so customer text is escaped by construction.
 */
class InboxPresenter
{
    /** @var array<int, string> user id => name, memoized per request */
    private array $userNames = [];

    public function __construct(private readonly BotSettingsResolver $settings) {}

    /**
     * One conversation list row. Expects the preview columns added by ConversationIndexRequest::filteredQuery()
     * (last_message_*); falls back to a query when they are missing.
     *
     * @return array<string, mixed>
     */
    public function row(Conversation $c): array
    {
        if (! array_key_exists('last_message_body', $c->getAttributes())) {
            $last = $c->messages()->latest('id')->first();
            $c->setAttribute('last_message_body', $last?->body);
            $c->setAttribute('last_message_direction', $last?->direction?->value);
            $c->setAttribute('last_message_sender', $last?->sender_type?->value);
            $c->setAttribute('last_message_attachments', $last?->getRawOriginal('attachments'));
            $c->setAttribute('last_outgoing_status', $c->messages()->where('direction', MessageDirection::Outgoing)->latest('id')->value('status'));
        }

        $sender = self::enumValue($c->getAttribute('last_message_sender'));
        $body = (string) $c->getAttribute('last_message_body');
        $attachments = self::decodeJson($c->getAttribute('last_message_attachments'));
        $excerpt = Conversation::excerpt($body, 90);

        if ($excerpt === '' && $attachments !== []) {
            $excerpt = self::attachmentSummary($attachments);
        }

        $paused = $c->isPaused();
        $lastFailed = self::enumValue($c->getAttribute('last_outgoing_status')) === MessageStatus::Failed->value;

        return [
            'id' => $c->id,
            'meta_account_id' => $c->meta_account_id,
            'platform' => $c->platform?->value,
            'external_user_id' => $c->external_user_id,
            'customer_name' => $c->customer_name,
            'name' => $c->displayName(),
            'initials' => $c->initials(),
            'avatar' => self::safeUrl($c->profile_pic_url),
            'username' => $c->username,
            'page_name' => $c->metaAccount?->page_name,
            'status' => $c->status,
            'bot_enabled' => (bool) $c->bot_enabled,
            'human_takeover' => (bool) $c->human_takeover,
            'bot_paused_until' => $paused ? $c->bot_paused_until?->toIso8601String() : null,
            'bot_can_reply' => $c->botCanReply(),
            'last_failed' => $lastFailed,
            'needs_human' => $c->status === 'open' && ($c->human_takeover || $paused || $lastFailed),
            'unread_count' => (int) $c->unread_count,
            'lead_stage' => $c->leadStage()->value,
            'tags' => $c->tagList(),
            'last_message' => $c->getAttribute('last_message_body'),
            'excerpt' => $excerpt,
            'last_sender' => $sender,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'last_customer_message_at' => $c->last_customer_message_at?->toIso8601String(),
        ];
    }

    /**
     * Full conversation + contact data for the thread header and contact panel.
     *
     * @return array<string, mixed>
     */
    public function detail(Conversation $c): array
    {
        $c->loadMissing('metaAccount');
        $account = $c->metaAccount;
        $platform = $c->platform ?? Platform::Facebook;
        $captured = collect((array) (($c->meta ?? [])['captured'] ?? []))
            ->filter(fn ($e) => is_array($e) && isset($e['type'], $e['value']))
            ->map(fn (array $e) => [
                'type' => (string) $e['type'],
                'value' => (string) $e['value'],
                'message_id' => isset($e['message_id']) ? (int) $e['message_id'] : null,
                'at' => isset($e['at']) ? (string) $e['at'] : null,
            ])
            ->reverse()->values()->all();

        $takeoverMinutes = 0;

        if ($account !== null) {
            try {
                $takeoverMinutes = $this->settings->forAccount($account)->humanTakeoverMinutes;
            } catch (Throwable) {
                $takeoverMinutes = 0;
            }
        }

        return $this->row($c) + [
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'notes' => $c->notes,
            'lead_stage_label' => $c->leadStage()->label(),
            'captured' => $captured,
            'lead_captured_at' => $c->lead_captured_at?->toIso8601String(),
            'first_seen_at' => $c->created_at?->toIso8601String(),
            'messages_count' => $c->messages()->count(),
            'within_window' => $c->withinMessagingWindow(),
            'window_closes_at' => $c->last_customer_message_at?->copy()->addHours(24)->toIso8601String(),
            'takeover_minutes' => $takeoverMinutes,
            'char_limit' => (int) config('meta.max_message_length.'.$platform->value, $platform === Platform::Instagram ? 1000 : 2000),
            'char_unit' => (string) config('meta.message_length_unit.'.$platform->value, $platform === Platform::Instagram ? 'bytes' : 'chars'),
            'account_active' => (bool) ($account?->active ?? false),
            'profile_fetched_at' => $c->profile_fetched_at?->toIso8601String(),
            'show_url' => route('admin.conversations.show', $c),
        ];
    }

    /**
     * @param  iterable<Message>  $messages
     * @return list<array<string, mixed>>
     */
    public function messages(iterable $messages, ?User $viewer = null): array
    {
        $messages = $messages instanceof Collection ? $messages : collect($messages);
        $this->loadUserNames($messages);

        return $messages->map(fn (Message $m) => $this->message($m, $viewer))->values()->all();
    }

    /** @return array<string, mixed> */
    public function message(Message $m, ?User $viewer = null): array
    {
        $source = $m->sourceMarker();
        $userId = is_array($m->payload) ? ($m->payload['sent_by_user_id'] ?? null) : null;

        $label = match ($m->sender_type) {
            SenderType::Customer => null,
            SenderType::Bot => match ($source) {
                'automation' => 'Automation',
                'fallback' => 'Fallback',
                'ai' => 'AI',
                default => 'Bot',
            },
            SenderType::Human => match (true) {
                $userId !== null && $viewer !== null && (int) $userId === $viewer->id => 'You',
                $userId !== null => $this->userNames[(int) $userId] ?? 'Agent',
                default => 'Page inbox',
            },
            default => null,
        };

        return [
            'id' => $m->id,
            'direction' => $m->direction?->value,
            'sender_type' => $m->sender_type?->value,
            'sender_label' => $label,
            'source' => $m->sender_type === SenderType::Bot ? ($source ?? 'bot') : ($m->sender_type === SenderType::Human ? ($userId !== null ? 'admin' : 'page') : null),
            'body' => $m->body,
            'attachments' => self::attachments($m->attachments),
            'status' => $m->status?->value,
            'error' => $m->error,
            'error_hint' => $m->status === MessageStatus::Failed ? self::errorHint($m->error) : null,
            'at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /**
     * Normalized attachments with only http(s) URLs.
     *
     * @return list<array{type: string, kind: string, url: ?string, label: string}>
     */
    public static function attachments(mixed $attachments): array
    {
        $out = [];

        foreach ((array) $attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $type = is_string($attachment['type'] ?? null) ? $attachment['type'] : 'file';
            $url = self::safeUrl($attachment['url'] ?? ($attachment['payload']['url'] ?? null));
            $isSticker = isset($attachment['payload']['sticker_id']);

            $kind = match (true) {
                in_array($type, ['image', 'sticker', 'animated_image'], true) || $isSticker => 'image',
                in_array($type, ['video', 'ig_reel', 'reel'], true) => 'video',
                $type === 'audio' => 'audio',
                in_array($type, ['fallback', 'share', 'story_mention', 'ig_post', 'template'], true) => 'link',
                default => 'file',
            };

            $label = match ($type) {
                'image' => $isSticker ? 'Sticker' : 'Photo',
                'sticker' => 'Sticker',
                'video' => 'Video',
                'audio' => 'Voice message',
                'file' => 'File',
                'story_mention' => 'Story mention',
                'ig_reel', 'reel' => 'Reel',
                'share', 'ig_post' => 'Shared post',
                'fallback' => (string) ($attachment['payload']['title'] ?? 'Link'),
                default => Str::headline($type),
            };

            $out[] = ['type' => $type, 'kind' => $kind, 'url' => $url, 'label' => Str::limit($label, 80)];
        }

        return $out;
    }

    /** "Photo", "2 attachments"… for list excerpts of messages without text. */
    public static function attachmentSummary(array $attachments): string
    {
        $items = self::attachments($attachments);

        if (count($items) > 1) {
            return count($items).' attachments';
        }

        return $items === [] ? 'Attachment' : $items[0]['label'];
    }

    /** Plain-language meaning of a Meta send error, for the failed-bubble tooltip. */
    public static function errorHint(?string $error): string
    {
        $e = mb_strtolower((string) $error);

        return match (true) {
            str_contains($e, '24') && (str_contains($e, 'window') || str_contains($e, 'hour')),
            str_contains($e, '(#10)'), str_contains($e, 'outside') => 'Meta only allows replies within 24 hours of the customer\'s last message. Wait for them to write again.',
            str_contains($e, '(#551)'), str_contains($e, 'not available') => 'This person can\'t receive messages right now (they may have blocked the Page or deleted their account).',
            str_contains($e, '(#190)'), str_contains($e, 'token') => 'The Page connection expired. Reconnect it in Pages & Channels.',
            str_contains($e, '(#200)'), str_contains($e, 'permission') => 'The app is missing the messaging permission for this Page. Reconnect it in Pages & Channels.',
            str_contains($e, 'inactive'), str_contains($e, 'missing') => 'This Page is disconnected or turned off in Pages & Channels.',
            str_contains($e, 'rate') || str_contains($e, 'limit') => 'Meta is rate-limiting this Page. Try again in a minute.',
            default => 'Meta did not accept this message. Try again, or reply from the Meta Business Suite inbox.',
        };
    }

    public static function safeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    private function loadUserNames(Collection $messages): void
    {
        $ids = $messages
            ->map(fn (Message $m) => is_array($m->payload) ? ($m->payload['sent_by_user_id'] ?? null) : null)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => isset($this->userNames[$id]))
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        foreach (User::query()->whereIn('id', $ids)->get(['id', 'name', 'email']) as $user) {
            $this->userNames[$user->id] = (string) (Str::of((string) $user->name)->trim()->before(' ')->value() ?: $user->email);
        }
    }

    private static function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Server time for polling cursors. */
    public static function now(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
