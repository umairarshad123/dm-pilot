<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStage;
use App\Enums\MessageDirection;
use App\Enums\Platform;
use App\Enums\SenderType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactFilterRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Contacts\ContactDeletionService;
use App\Services\Contacts\ContactService;
use App\Support\CurrentPage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Contacts (CRM): list + board views, the profile drawer (JSON), edits, profile refresh and hard delete.
 * A contact is a Conversation row (one customer on one Page / IG account).
 */
class ContactController extends Controller
{
    public const PER_PAGE = 25;

    public const BOARD_LIMIT = 40;

    /** Quick segments: preset filters (query string). */
    public const SEGMENTS = [
        'all' => ['label' => 'All contacts', 'icon' => 'users', 'params' => []],
        'new' => ['label' => 'New leads', 'icon' => 'sparkles', 'params' => ['lead_stage' => 'new']],
        'qualified' => ['label' => 'Qualified', 'icon' => 'trending-up', 'params' => ['lead_stage' => 'qualified']],
        'customers' => ['label' => 'Customers', 'icon' => 'check-circle', 'params' => ['lead_stage' => 'customer']],
        'reachable' => ['label' => 'Has email/phone', 'icon' => 'mail', 'params' => ['reachable' => '1']],
        'unread' => ['label' => 'Unread', 'icon' => 'inbox', 'params' => ['unread' => '1']],
    ];

    /** Keys the segments control (a segment is active when these match its params exactly). */
    private const SEGMENT_KEYS = ['lead_stage', 'reachable', 'unread'];

    public function __construct(
        private readonly ContactService $contacts,
        private readonly CurrentPage $currentPage,
    ) {}

    public function index(ContactFilterRequest $request): View
    {
        $view = $request->query('view') === 'board' ? 'board' : 'list';
        $filters = $request->filters($this->currentPage);

        $contacts = null;
        $board = null;

        if ($view === 'board') {
            $board = $this->board($request, $filters);
        } else {
            $contacts = $request->contactQuery($this->contacts, $this->currentPage, $filters)
                ->with('metaAccount:id,platform,page_name,page_id,instagram_account_id')
                ->withCount('messages')
                ->paginate(self::PER_PAGE)
                ->appends($request->except(['page', 'contact']));
        }

        $summary = $this->summary();
        $initial = null;

        if (ctype_digit((string) $request->query('contact'))) {
            $found = Conversation::query()->find((int) $request->query('contact'));
            $initial = $found ? $this->present($found) : null;
        }

        return view('admin.contacts.index', [
            'view' => $view,
            'contacts' => $contacts,
            'board' => $board,
            'filters' => $filters,
            'params' => $request->activeParams(),
            'segments' => $this->segments($request, $summary),
            'summary' => $summary,
            'tags' => $this->contacts->allTags($this->currentPage->id()),
            'initialContact' => $initial,
            'hasAnyContacts' => $summary['total'] > 0,
        ]);
    }

    /** Drawer data (JSON). A plain browser visit opens the list with the drawer. */
    public function show(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('admin.contacts.index', ['contact' => $conversation->id]);
        }

        return response()->json(['data' => $this->present($conversation)]);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $fields = $request->only(['customer_name', 'first_name', 'last_name', 'email', 'phone', 'lead_stage', 'tags', 'notes']);

        if ($request->has('tags') && $request->input('tags') === null) {
            $fields['tags'] = [];
        }

        $this->contacts->update($conversation, $fields);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Contact saved.');
        }

        return response()->json([
            'message' => array_keys($fields) === ['lead_stage']
                ? 'Moved to '.$conversation->leadStage()->label().'.'
                : 'Contact saved.',
            'data' => $this->present($conversation->refresh()),
        ]);
    }

    public function refresh(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $this->contacts->refreshProfile($conversation);
        $message = 'Profile refresh requested. New details appear in a few seconds.';

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    public function destroy(Request $request, Conversation $conversation, ContactDeletionService $deletion): JsonResponse|RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:DELETE']], [
            'confirm.required' => 'Type DELETE to confirm.',
            'confirm.in' => 'Type DELETE to confirm.',
        ]);

        $name = $conversation->displayName();
        $counts = $deletion->delete($conversation, 'manual', $request->user()?->id);
        $message = sprintf('%s and %d message%s deleted.', $name, $counts['messages'], $counts['messages'] === 1 ? '' : 's');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'deleted' => $counts])
            : redirect()->route('admin.contacts.index')->with('success', $message);
    }

    // ---------------------------------------------------------------------------------------------
    // Presentation
    // ---------------------------------------------------------------------------------------------

    /** Full drawer payload. */
    public function present(Conversation $c): array
    {
        $c->loadMissing('metaAccount:id,platform,page_name,page_id,instagram_account_id')
            ->loadCount([
                'messages',
                'messages as incoming_count' => fn (Builder $q) => $q->where('direction', MessageDirection::Incoming),
                'messages as outgoing_count' => fn (Builder $q) => $q->where('direction', MessageDirection::Outgoing),
            ]);

        $recent = $c->messages()->latest('id')->limit(5)->get()->reverse()->values();
        $firstMessageAt = $c->messages()->min('created_at');

        $captured = collect((array) (($c->meta ?? [])['captured'] ?? []))
            ->filter(fn ($e) => is_array($e) && isset($e['type'], $e['value']))
            ->reverse()
            ->map(fn (array $e) => [
                'type' => $e['type'] === 'phone' ? 'phone' : 'email',
                'value' => (string) $e['value'],
                'message_id' => $e['message_id'] ?? null,
                'at' => self::human($e['at'] ?? null),
                'at_full' => self::full($e['at'] ?? null),
            ])->values();

        return self::card($c) + [
            'customer_name' => $c->customer_name,
            'first_name' => $c->first_name,
            'last_name' => $c->last_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'notes' => $c->notes,
            'external_user_id' => $c->external_user_id,
            'status' => $c->status,
            'stats' => [
                'messages' => (int) $c->messages_count,
                'incoming' => (int) $c->getAttribute('incoming_count'),
                'outgoing' => (int) $c->getAttribute('outgoing_count'),
                'unread' => (int) $c->unread_count,
                'first_seen' => self::human($firstMessageAt ?? $c->created_at),
                'first_seen_full' => self::full($firstMessageAt ?? $c->created_at),
                'lead_captured' => self::human($c->lead_captured_at),
                'profile_fetched' => self::human($c->profile_fetched_at),
            ],
            'captured' => $captured,
            'messages' => $recent->map(fn (Message $m) => [
                'id' => $m->id,
                'mine' => $m->direction === MessageDirection::Outgoing,
                'sender' => match ($m->sender_type) {
                    SenderType::Bot => 'Bot',
                    SenderType::Human => 'Team',
                    default => 'Customer',
                },
                'body' => Conversation::excerpt($m->body, 280),
                'attachment' => count((array) $m->attachments) > 0,
                'at' => self::human($m->created_at),
                'at_full' => self::full($m->created_at),
                'failed' => $m->status?->value === 'failed',
            ]),
            'urls' => [
                'show' => route('admin.contacts.show', $c),
                'update' => route('admin.contacts.update', $c),
                'refresh' => route('admin.contacts.refresh', $c),
                'destroy' => route('admin.contacts.destroy', $c),
                'live_chat' => route('admin.conversations.show', $c),
            ],
        ];
    }

    /** Compact payload (board cards + drawer header). */
    public static function card(Conversation $c): array
    {
        $stage = $c->leadStage();
        $bot = self::botState($c);

        return [
            'id' => $c->id,
            'name' => $c->displayName(),
            'initials' => $c->initials(),
            'avatar_class' => self::avatarClass($c->displayName()),
            'picture' => $c->profile_pic_url,
            'platform' => $c->platform?->value ?? 'facebook',
            'channel' => $c->platform === Platform::Instagram ? 'Instagram' : 'Messenger',
            'username' => $c->username,
            'page' => $c->metaAccount?->page_name ?: ($c->metaAccount ? 'Page #'.$c->metaAccount->id : null),
            'lead_stage' => $stage->value,
            'tags' => $c->tagList(),
            'has_email' => filled($c->email),
            'has_phone' => filled($c->phone),
            'unread' => (int) $c->unread_count,
            'bot' => $bot,
            'last_active' => self::human($c->last_message_at),
            'last_active_full' => self::full($c->last_message_at),
        ];
    }

    /** on | off | paused | takeover */
    public static function botState(Conversation $c): string
    {
        return match (true) {
            (bool) $c->human_takeover => 'takeover',
            $c->isPaused() => 'paused',
            ! $c->bot_enabled => 'off',
            default => 'on',
        };
    }

    /** Same deterministic colour as <x-ui.avatar> (classes are defined in that component). */
    public static function avatarClass(string $name): string
    {
        $palette = [
            'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700', 'bg-emerald-100 text-emerald-700',
            'bg-amber-100 text-amber-800', 'bg-rose-100 text-rose-700', 'bg-indigo-100 text-indigo-700',
            'bg-teal-100 text-teal-700', 'bg-fuchsia-100 text-fuchsia-700', 'bg-orange-100 text-orange-700',
            'bg-blue-100 text-blue-700', 'bg-lime-100 text-lime-800', 'bg-cyan-100 text-cyan-800',
        ];
        $label = trim($name) !== '' ? trim($name) : '?';

        return $palette[crc32(mb_strtolower($label)) % count($palette)];
    }

    // ---------------------------------------------------------------------------------------------
    // Queries
    // ---------------------------------------------------------------------------------------------

    /** KPI + segment counts in one aggregate query (page scoped). */
    private function summary(): array
    {
        $new = LeadStage::New->value;
        $row = $this->currentPage->scope(Conversation::query())
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN lead_stage IS NULL OR lead_stage = ? THEN 1 ELSE 0 END) as stage_new', [$new])
            ->selectRaw('SUM(CASE WHEN lead_stage = ? THEN 1 ELSE 0 END) as stage_qualified', [LeadStage::Qualified->value])
            ->selectRaw('SUM(CASE WHEN lead_stage = ? THEN 1 ELSE 0 END) as stage_customer', [LeadStage::Customer->value])
            ->selectRaw("SUM(CASE WHEN (email IS NOT NULL AND email <> '') OR (phone IS NOT NULL AND phone <> '') THEN 1 ELSE 0 END) as reachable")
            ->selectRaw('SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END) as unread')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_week', [Carbon::now()->subDays(7)])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'new' => (int) ($row->stage_new ?? 0),
            'qualified' => (int) ($row->stage_qualified ?? 0),
            'customers' => (int) ($row->stage_customer ?? 0),
            'reachable' => (int) ($row->reachable ?? 0),
            'unread' => (int) ($row->unread ?? 0),
            'new_week' => (int) ($row->new_week ?? 0),
        ];
    }

    private function segments(ContactFilterRequest $request, array $summary): array
    {
        $current = array_filter(
            array_map(fn ($k) => $request->query($k), array_combine(self::SEGMENT_KEYS, self::SEGMENT_KEYS)),
            fn ($v) => filled($v),
        );
        $counts = [
            'all' => $summary['total'], 'new' => $summary['new'], 'qualified' => $summary['qualified'],
            'customers' => $summary['customers'], 'reachable' => $summary['reachable'], 'unread' => $summary['unread'],
        ];
        $keep = array_filter(['view' => $request->query('view') === 'board' ? 'board' : null]);

        $segments = [];

        foreach (self::SEGMENTS as $key => $segment) {
            if ($request->query('view') === 'board' && isset($segment['params']['lead_stage'])) {
                continue; // board columns are the stages already
            }

            $segments[$key] = $segment + [
                'key' => $key,
                'count' => $counts[$key],
                'href' => route('admin.contacts.index', $keep + $segment['params']),
                'active' => $current == $segment['params'],
            ];
        }

        return $segments;
    }

    /** @return list<array{stage: string, label: string, count: int, cards: list<array>}> */
    private function board(ContactFilterRequest $request, array $filters): array
    {
        unset($filters['lead_stage']);
        $columns = [];

        foreach (LeadStage::cases() as $stage) {
            $query = $request->contactQuery($this->contacts, $this->currentPage, $filters)->leadStage($stage);

            $columns[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => (clone $query)->count(),
                'cards' => (clone $query)
                    ->with('metaAccount:id,platform,page_name,page_id,instagram_account_id')
                    ->limit(self::BOARD_LIMIT)
                    ->get()
                    ->map(fn (Conversation $c) => self::card($c))
                    ->all(),
                'href' => route('admin.contacts.index', ['lead_stage' => $stage->value] + $request->activeParams()),
            ];
        }

        return $columns;
    }

    private static function human(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->diffForHumans(['short' => false, 'parts' => 1]);
    }

    private static function full(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('M j, Y · H:i');
    }
}
