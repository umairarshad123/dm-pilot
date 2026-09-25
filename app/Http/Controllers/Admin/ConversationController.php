<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConversationIndexRequest;
use App\Http\Requests\Admin\ConversationStatusRequest;
use App\Http\Requests\Admin\ReplyRequest;
use App\Http\Requests\Admin\ToggleRequest;
use App\Models\Conversation;
use App\Services\Contacts\ContactService;
use App\Services\ConversationService;
use App\Support\CurrentPage;
use App\Support\Inbox\InboxPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use RuntimeException;

/**
 * Live Chat page (3 panes: conversation list, thread, contact) + form fallbacks for the actions.
 * The page boots from inline JSON and then talks to Api\ConversationApiController / InboxContactController.
 */
class ConversationController extends Controller
{
    /** Messages rendered when a conversation opens (older ones load on demand). */
    public const THREAD_PAGE = 60;

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly InboxPresenter $presenter,
    ) {}

    public function index(ConversationIndexRequest $request, CurrentPage $currentPage, ContactService $contacts): View
    {
        return $this->page($request, $currentPage, $contacts);
    }

    /** Deep link: the same page with this conversation open (marks it read). */
    public function show(ConversationIndexRequest $request, Conversation $conversation, CurrentPage $currentPage, ContactService $contacts): View
    {
        $conversation->markRead();

        return $this->page($request, $currentPage, $contacts, $conversation);
    }

    public function reply(ReplyRequest $request, Conversation $conversation): RedirectResponse
    {
        try {
            $this->conversations->sendHumanReply($conversation, $request->validated('text'), $request->user());
        } catch (RuntimeException $e) {
            Log::warning('Admin manual reply failed', ['conversation_id' => $conversation->id, 'exception' => $e::class]);

            return back()->withInput()->with('error', 'Reply failed: '.$e->getMessage());
        }

        return back()->with('success', 'Reply sent.');
    }

    public function bot(ToggleRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->conversations->setBotEnabled($conversation, $request->enabled());

        return back()->with('success', $request->enabled() ? 'Bot enabled for this conversation.' : 'Bot disabled for this conversation.');
    }

    public function takeover(ToggleRequest $request, Conversation $conversation): RedirectResponse
    {
        $this->conversations->setHumanTakeover($conversation, $request->enabled());

        return back()->with('success', $request->enabled()
            ? 'Human takeover on: the bot will stay silent.'
            : 'Human takeover off: the bot may reply again.');
    }

    public function clearPause(Conversation $conversation): RedirectResponse
    {
        $conversation->forceFill(['bot_paused_until' => null])->save();

        return back()->with('success', 'Bot pause cleared.');
    }

    public function status(ConversationStatusRequest $request, Conversation $conversation): RedirectResponse
    {
        $conversation->forceFill(['status' => $request->validated('status')])->save();

        return back()->with('success', $conversation->status === 'open' ? 'Conversation reopened.' : 'Conversation closed.');
    }

    private function page(ConversationIndexRequest $request, CurrentPage $currentPage, ContactService $contacts, ?Conversation $selected = null): View
    {
        $page = $request->filteredQuery()->paginate($request->perPage());
        $filters = $request->validated();
        $user = $request->user();

        $thread = [];
        $hasOlder = false;

        if ($selected !== null) {
            $messages = $selected->messages()->orderByDesc('id')->limit(self::THREAD_PAGE + 1)->get();
            $hasOlder = $messages->count() > self::THREAD_PAGE;
            $thread = $this->presenter->messages($messages->take(self::THREAD_PAGE)->reverse()->values(), $user);
        }

        $placeholder = '__ID__';
        $boot = [
            'list' => [
                'data' => collect($page->items())->map(fn (Conversation $c) => $this->presenter->row($c))->values()->all(),
                'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'counts' => $request->counts(),
                'server_time' => InboxPresenter::now(),
            ],
            'filters' => [
                'view' => $request->view(),
                'platform' => $filters['platform'] ?? '',
                'q' => $filters['q'] ?? '',
                // Legacy query filters (?account=, ?status=, ?takeover=, ?bot=) are carried through polling.
                'extra' => array_filter([
                    'account' => $filters['account'] ?? null,
                    'status' => $filters['status'] ?? null,
                    'takeover' => $filters['takeover'] ?? null,
                    'bot' => $filters['bot'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
            ],
            'selected' => $selected ? $this->presenter->detail($selected->refresh()) : null,
            'messages' => $thread,
            'has_older' => $hasOlder,
            'tags' => $contacts->allTags($currentPage->id()),
            'me' => ['id' => $user?->id, 'name' => $user?->name],
            'page_label' => $currentPage->label(),
            'has_pages' => $currentPage->connectedPages()->isNotEmpty(),
            'routes' => [
                'index' => route('admin.conversations.index'),
                'show' => route('admin.conversations.show', $placeholder),
                'list' => route('admin.api.conversations.index'),
                'detail' => route('admin.api.conversations.show', $placeholder),
                'messages' => route('admin.api.conversations.messages', $placeholder),
                'reply' => route('admin.api.conversations.reply', $placeholder),
                'read' => route('admin.api.conversations.read', $placeholder),
                'bot' => route('admin.api.conversations.bot', $placeholder),
                'takeover' => route('admin.api.conversations.takeover', $placeholder),
                'clear_pause' => route('admin.api.conversations.clear-pause', $placeholder),
                'status' => route('admin.api.conversations.status', $placeholder),
                'contact' => route('admin.api.conversations.contact', $placeholder),
                'refresh_profile' => route('admin.api.conversations.refresh-profile', $placeholder),
                'contacts' => Route::has('admin.contacts.show') ? route('admin.contacts.show', $placeholder) : route('admin.contacts.index'),
            ],
        ];

        return view('admin.inbox.index', [
            'boot' => $boot,
            'selected' => $selected,
            'stages' => LeadStage::cases(),
        ]);
    }
}
