<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\SenderType;
use App\Exceptions\MetaApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConversationIndexRequest;
use App\Http\Requests\Admin\ConversationStatusRequest;
use App\Http\Requests\Admin\ReplyRequest;
use App\Http\Requests\Admin\ToggleRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;
use App\Support\Inbox\InboxPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live Chat JSON API (session auth + admin; CSRF on POST/PATCH).
 */
class ConversationApiController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly InboxPresenter $presenter,
    ) {}

    /**
     * Conversation list. Without `since`: one page of rows + meta. With `since`: only rows changed since then
     * (any tab), each flagged with `matches` (whether it belongs in the current tab/filters).
     */
    public function index(ConversationIndexRequest $request): JsonResponse
    {
        $serverTime = InboxPresenter::now();

        if ($request->isDelta()) {
            $ids = $request->changedIds();
            $changed = $ids === [] ? collect() : ConversationIndexRequest::withPreview(Conversation::query())->whereIn('conversations.id', $ids)->get();
            $matching = $ids === [] ? [] : $request->filteredQuery()->whereIn('conversations.id', $ids)->pluck('conversations.id')->all();

            return response()->json([
                'data' => $changed->map(fn (Conversation $c) => $this->presenter->row($c) + ['matches' => in_array($c->id, $matching, true)])->values(),
                'counts' => $request->counts(),
                'delta' => true,
                'server_time' => $serverTime,
            ]);
        }

        $page = $request->filteredQuery()->paginate($request->perPage());

        return response()->json([
            'data' => collect($page->items())->map(fn (Conversation $c) => $this->presenter->row($c))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'counts' => $request->counts(),
            'delta' => false,
            'server_time' => $serverTime,
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        return response()->json(['data' => $this->presenter->detail($conversation)]);
    }

    /**
     * Messages oldest to newest.
     *   ?after_id=  only newer messages (polling)      ?since=  also messages updated since (status changes)
     *   ?before_id= older history page                 ?limit=  page size (default 100)
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'since' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($validated['limit'] ?? 100);
        $afterId = $validated['after_id'] ?? null;
        $since = isset($validated['since']) ? Carbon::parse($validated['since'])->subSeconds(2) : null;

        $messages = $conversation->messages()
            ->when($validated['before_id'] ?? null, fn ($q, $id) => $q->where('id', '<', $id))
            ->when($afterId !== null && $afterId > 0, function ($q) use ($afterId, $since) {
                $q->where(fn ($w) => $w->where('id', '>', $afterId)
                    ->when($since, fn ($s) => $s->orWhere('updated_at', '>=', $since)));
            })
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        $messages = $messages->take($limit)->reverse()->values();

        return response()->json([
            'conversation' => $this->presenter->detail($conversation),
            'data' => $this->presenter->messages($messages, $request->user()),
            'has_more' => $hasMore,
            'server_time' => InboxPresenter::now(),
        ]);
    }

    /** Send a manual reply through Meta. 422 with an admin-safe message (and the failed message row) when Meta rejects it. */
    public function reply(ReplyRequest $request, Conversation $conversation): JsonResponse
    {
        $beforeId = (int) $conversation->messages()->max('id');

        try {
            $message = $this->conversations->sendHumanReply($conversation, $request->validated('text'), $request->user());
        } catch (Throwable $e) {
            Log::warning('Admin manual reply failed', ['conversation_id' => $conversation->id, 'exception' => $e::class]);

            $failed = $conversation->messages()
                ->where('id', '>', $beforeId)
                ->where('sender_type', SenderType::Human->value)
                ->latest('id')
                ->first();

            return response()->json([
                'message' => $e instanceof MetaApiException ? $e->getMessage() : 'Could not reach Meta. Please try again.',
                'data' => $failed ? $this->presenter->message($failed, $request->user()) : null,
                'conversation' => $this->presenter->detail($conversation->refresh()),
            ], 422);
        }

        return response()->json([
            'data' => $message instanceof Message && $message->exists ? $this->presenter->message($message, $request->user()) : null,
            'conversation' => $this->presenter->detail($conversation->refresh()),
        ]);
    }

    /** The admin opened / is viewing the conversation: reset the unread counter. */
    public function read(Conversation $conversation): JsonResponse
    {
        $conversation->markRead();

        return response()->json(['data' => ['id' => $conversation->id, 'unread_count' => 0]]);
    }

    public function bot(ToggleRequest $request, Conversation $conversation): JsonResponse
    {
        return $this->respond($this->conversations->setBotEnabled($conversation, $request->enabled()));
    }

    public function takeover(ToggleRequest $request, Conversation $conversation): JsonResponse
    {
        return $this->respond($this->conversations->setHumanTakeover($conversation, $request->enabled()));
    }

    public function clearPause(Conversation $conversation): JsonResponse
    {
        $conversation->forceFill(['bot_paused_until' => null])->save();

        return $this->respond($conversation);
    }

    public function status(ConversationStatusRequest $request, Conversation $conversation): JsonResponse
    {
        $conversation->forceFill(['status' => $request->validated('status')])->save();

        return $this->respond($conversation);
    }

    private function respond(Conversation $conversation): JsonResponse
    {
        return response()->json(['data' => $this->presenter->detail($conversation->refresh())]);
    }

    /**
     * Minimal conversation state (kept for callers of the original API).
     *
     * @return array<string, mixed>
     */
    public static function conversationData(Conversation $c): array
    {
        return [
            'id' => $c->id,
            'meta_account_id' => $c->meta_account_id,
            'platform' => $c->platform?->value,
            'external_user_id' => $c->external_user_id,
            'customer_name' => $c->customer_name,
            'status' => $c->status,
            'bot_enabled' => $c->bot_enabled,
            'human_takeover' => $c->human_takeover,
            'bot_paused_until' => $c->bot_paused_until?->toIso8601String(),
            'bot_can_reply' => $c->botCanReply(),
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'last_customer_message_at' => $c->last_customer_message_at?->toIso8601String(),
        ];
    }
}
