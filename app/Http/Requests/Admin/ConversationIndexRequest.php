<?php

namespace App\Http\Requests\Admin;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\Platform;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\CurrentPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Live Chat list filters (page + JSON API).
 *
 *   view      all (default) | unread | human (needs a human) | bot (bot active) | closed
 *   platform  facebook | instagram
 *   account   meta_accounts.id (on top of the sidebar page context)
 *   status / takeover / bot   legacy filters (still supported)
 *   q         name, @username, email, phone, PSID
 *   since     ISO time: JSON delta mode (only rows changed since then, each with `matches`)
 */
class ConversationIndexRequest extends FormRequest
{
    public const VIEWS = ['all', 'unread', 'human', 'bot', 'closed'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'platform' => ['nullable', Rule::enum(Platform::class)],
            'account' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['open', 'closed'])],
            'takeover' => ['nullable', Rule::in(['1', '0'])],
            'bot' => ['nullable', Rule::in(['1', '0'])],
            'q' => ['nullable', 'string', 'max:255'],
            'since' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** The active tab. */
    public function view(): string
    {
        return $this->validated('view') ?? 'all';
    }

    /** Conversations query with the validated filters + last-message preview columns, newest activity first. */
    public function filteredQuery(): Builder
    {
        $filters = $this->validated();

        return self::applyView(self::withPreview(app(CurrentPage::class)->scope(Conversation::query())), $filters['view'] ?? 'all')
            ->when($filters['platform'] ?? null, fn (Builder $q, string $v) => $q->where('platform', $v))
            ->when($filters['account'] ?? null, fn (Builder $q, $v) => $q->where('meta_account_id', $v))
            ->when($filters['status'] ?? null, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when(isset($filters['takeover']), fn (Builder $q) => $q->where('human_takeover', $filters['takeover'] === '1'))
            ->when(isset($filters['bot']), fn (Builder $q) => $q->where('bot_enabled', $filters['bot'] === '1'))
            ->when(filled($filters['q'] ?? null), fn (Builder $q) => $q->search($filters['q']))
            ->orderByDesc('last_message_at')
            ->orderByDesc('conversations.id');
    }

    /** Counts per tab (same page / channel / search scope, ignoring the active tab). */
    public function counts(): array
    {
        $filters = $this->validated();
        $base = fn () => app(CurrentPage::class)->scope(Conversation::query())
            ->when($filters['platform'] ?? null, fn (Builder $q, string $v) => $q->where('platform', $v))
            ->when($filters['account'] ?? null, fn (Builder $q, $v) => $q->where('meta_account_id', $v))
            ->when(filled($filters['q'] ?? null), fn (Builder $q) => $q->search($filters['q']));

        $counts = [];

        foreach (self::VIEWS as $view) {
            $counts[$view] = self::applyView($base(), $view)->count();
        }

        return $counts;
    }

    /** Delta mode: ids of conversations (in the page scope) that changed since the cursor. */
    public function changedIds(): array
    {
        $since = Carbon::parse($this->validated('since'))->subSeconds(2);

        $fromConversations = app(CurrentPage::class)->scope(Conversation::query())
            ->where('updated_at', '>=', $since)
            ->limit(200)
            ->pluck('id');

        // Message status changes (e.g. a reply that failed) do not touch the conversation row.
        // Bounded by primary key so this never scans the whole table.
        $maxId = (int) Message::query()->max('id');
        $fromMessages = Message::query()
            ->where('id', '>', $maxId - 500)
            ->where('updated_at', '>=', $since)
            ->distinct()
            ->pluck('conversation_id');

        return $fromConversations->merge($fromMessages)->unique()->values()->all();
    }

    public function isDelta(): bool
    {
        return filled($this->validated('since'));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 30);
    }

    public static function withPreview(Builder $query): Builder
    {
        $lastMessage = fn (string $column) => Message::select($column)
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->orderByDesc('id')
            ->limit(1);

        return $query
            ->with('metaAccount:id,page_name,platform,active')
            ->select('conversations.*')
            ->addSelect([
                'last_message_body' => $lastMessage('body'),
                'last_message_direction' => $lastMessage('direction'),
                'last_message_sender' => $lastMessage('sender_type'),
                'last_message_attachments' => $lastMessage('attachments'),
                'last_outgoing_status' => Message::select('status')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('direction', MessageDirection::Outgoing->value)
                    ->orderByDesc('id')
                    ->limit(1),
            ]);
    }

    public static function applyView(Builder $query, string $view): Builder
    {
        $lastOutgoingFailed = fn (Builder $q) => $q->where(
            Message::select('status')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('direction', MessageDirection::Outgoing->value)
                ->orderByDesc('id')
                ->limit(1),
            MessageStatus::Failed->value,
        );

        return match ($view) {
            'unread' => $query->where('unread_count', '>', 0),
            'human' => $query->where('status', 'open')->where(fn (Builder $q) => $q
                ->where('human_takeover', true)
                ->orWhere('bot_paused_until', '>', Carbon::now())
                ->orWhere($lastOutgoingFailed)),
            'bot' => $query->where('status', 'open')->where('bot_enabled', true)->where('human_takeover', false)
                ->where(fn (Builder $q) => $q->whereNull('bot_paused_until')->orWhere('bot_paused_until', '<=', Carbon::now())),
            'closed' => $query->where('status', 'closed'),
            default => $query,
        };
    }
}
