<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageDirection;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MetaAccountRequest;
use App\Models\BotSetting;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\Insights\InsightsService;
use App\Services\Meta\MetaMessagingService;
use App\Services\Meta\MetaTokenService;
use App\Support\CurrentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Throwable;

class MetaAccountController extends Controller
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_TOKEN = 'token_problem';

    public const STATUS_UNSUBSCRIBED = 'not_subscribed';

    public function index(InsightsService $insights): View
    {
        $accounts = MetaAccount::withCount('conversations')->orderBy('page_name')->get();

        $messages7d = collect($insights->byPage(null, 7))
            ->mapWithKeys(fn (array $row) => [$row['meta_account_id'] => $row['incoming'] + $row['bot'] + $row['human']]);

        $lastInbound = Message::query()
            ->join('conversations as c', 'c.id', '=', 'messages.conversation_id')
            ->where('messages.direction', MessageDirection::Incoming)
            ->selectRaw('c.meta_account_id as k, MAX(messages.created_at) as last_at')
            ->groupBy('c.meta_account_id')
            ->toBase()->pluck('last_at', 'k');

        $botSettings = BotSetting::query()->get(['meta_account_id', 'bot_enabled']);
        $globalBot = (bool) ($botSettings->firstWhere('meta_account_id', null)?->bot_enabled ?? true);
        $botOverrides = $botSettings->whereNotNull('meta_account_id')->pluck('bot_enabled', 'meta_account_id');

        $cards = $accounts->mapWithKeys(fn (MetaAccount $a) => [$a->id => [
            'account' => $a,
            'status' => self::status($a),
            'messages_7d' => (int) ($messages7d[$a->id] ?? 0),
            'last_inbound_at' => isset($lastInbound[$a->id]) ? Carbon::parse($lastInbound[$a->id]) : null,
            'bot_enabled' => $botOverrides->has($a->id) && $botOverrides[$a->id] !== null ? (bool) $botOverrides[$a->id] : $globalBot,
            'bot_override' => $botOverrides->has($a->id),
            'picture' => $a->setting('picture_url'),
        ]]);

        return view('admin.meta-accounts.index', [
            'groups' => self::groups($accounts, $cards),
            'total' => $accounts->count(),
            'stats' => [
                'channels' => $accounts->count(),
                'active' => $accounts->where('active', true)->count(),
                'attention' => $cards->filter(fn ($c) => in_array($c['status'], [self::STATUS_TOKEN, self::STATUS_UNSUBSCRIBED], true))->count(),
                'messages_7d' => $messages7d->sum(),
            ],
            'botStudioRoute' => Route::has('admin.bot-settings.edit'),
        ]);
    }

    public function create(): View
    {
        return view('admin.meta-accounts.form', [
            'account' => new MetaAccount(['platform' => Platform::Facebook, 'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN, 'active' => true]),
        ]);
    }

    public function store(MetaAccountRequest $request): RedirectResponse
    {
        $account = MetaAccount::create($request->accountAttributes());
        $account->putSetting('connected_via', 'manual');

        return redirect()->route('admin.meta-accounts.index')->with('success', ($account->page_name ?: "Account #{$account->id}").' added.');
    }

    public function edit(MetaAccount $metaAccount): View
    {
        return view('admin.meta-accounts.form', ['account' => $metaAccount]);
    }

    public function update(MetaAccountRequest $request, MetaAccount $metaAccount): RedirectResponse
    {
        $attributes = $request->accountAttributes();
        $metaAccount->update($attributes);

        if (array_key_exists('access_token', $attributes)) {
            $metaAccount->putSetting('token_error', null);
        }

        return redirect()->route('admin.meta-accounts.index')->with('success', ($metaAccount->page_name ?: "Account #{$metaAccount->id}").' updated.');
    }

    public function destroy(MetaAccount $metaAccount, CurrentPage $currentPage): RedirectResponse
    {
        if ($currentPage->id() === $metaAccount->id) {
            $currentPage->set(null);
        }

        $name = $metaAccount->page_name ?: "Account #{$metaAccount->id}";
        $metaAccount->delete();

        return redirect()->route('admin.meta-accounts.index')->with('success', "{$name} disconnected (with its conversations and messages).");
    }

    public function toggle(MetaAccount $metaAccount): RedirectResponse
    {
        $metaAccount->forceFill(['active' => ! $metaAccount->active])->save();

        $name = $metaAccount->page_name ?: 'Account';

        return back()->with('success', $metaAccount->active ? "{$name} is active: the bot replies again." : "{$name} paused: incoming messages are ignored.");
    }

    public function test(MetaAccount $metaAccount, MetaMessagingService $meta, MetaTokenService $tokens): RedirectResponse
    {
        $result = self::runTest($meta, $metaAccount);

        if (! $result['ok']) {
            $metaAccount->putSetting('token_error', mb_substr((string) ($result['error'] ?? 'unknown error'), 0, 300));

            return back()->with('error', 'Connection failed: '.($result['error'] ?? 'unknown error'));
        }

        $metaAccount->putSetting('token_error', null);
        $this->refreshDetails($metaAccount, $tokens);

        $expires = $metaAccount->token_expires_at?->toDayDateTimeString() ?? 'never';
        $scopes = isset($result['scopes']) ? ' Scopes: '.implode(', ', (array) $result['scopes']).'.' : '';

        return back()->with('success', 'Connected as '.($result['name'] ?? '?').' ('.($result['id'] ?? '?').'). Token expires: '.$expires.'.'.$scopes);
    }

    public function subscribe(MetaAccount $metaAccount, MetaMessagingService $meta): RedirectResponse
    {
        try {
            $result = $meta->subscribeApp($metaAccount);
        } catch (Throwable $e) {
            Log::warning('Meta subscribeApp threw', ['meta_account_id' => $metaAccount->id, 'exception' => $e::class]);
            $result = ['ok' => false, 'error' => 'Unexpected error ('.class_basename($e).').'];
        }

        self::recordSubscription($metaAccount, $result);

        return $result['ok']
            ? back()->with('success', 'Webhooks subscribed: '.implode(', ', (array) ($result['fields'] ?? [])))
            : back()->with('error', 'Subscribe failed: '.($result['error'] ?? 'unknown error'));
    }

    /** Select this account as the page context, then open Bot Studio / Live Chat for it. */
    public function open(Request $request, MetaAccount $metaAccount, CurrentPage $currentPage): RedirectResponse
    {
        $targets = [
            'bot' => 'admin.bot-settings.edit',
            'chat' => 'admin.conversations.index',
            'contacts' => 'admin.contacts.index',
            'dashboard' => 'admin.dashboard',
        ];
        $route = $targets[(string) $request->input('to')] ?? 'admin.dashboard';

        $currentPage->set($metaAccount->id);

        return redirect()->route(Route::has($route) ? $route : 'admin.dashboard')
            ->with('success', 'Now viewing '.$currentPage->label().'.');
    }

    /** Remember the outcome of subscribeApp() for the channel cards. */
    public static function recordSubscription(MetaAccount $account, array $result): void
    {
        $account->putSetting('webhook_subscribed', (bool) $result['ok']);
        $account->putSetting('webhook_checked_at', now()->toIso8601String());

        if ($result['ok']) {
            $account->putSetting('webhook_fields', array_values((array) ($result['fields'] ?? [])));
            $account->putSetting('webhook_error', null);
        } else {
            $account->putSetting('webhook_error', mb_substr((string) ($result['error'] ?? 'unknown error'), 0, 300));
        }
    }

    /** Card status: paused > token problem > not subscribed > active. */
    public static function status(MetaAccount $account): string
    {
        return match (true) {
            filled($account->setting('deauthorized_at')), filled($account->setting('token_error')) => self::STATUS_TOKEN,
            ! $account->active => self::STATUS_PAUSED,
            $account->token_expires_at !== null && $account->token_expires_at->isPast() => self::STATUS_TOKEN,
            $account->setting('webhook_subscribed') === false => self::STATUS_UNSUBSCRIBED,
            default => self::STATUS_ACTIVE,
        };
    }

    /**
     * Group accounts into "Page + Instagram" pairs: each Facebook Page with the IG accounts linked to it
     * (same page_id); IG accounts without a connected Page stand alone.
     *
     * @return list<array{page: ?array, instagram: list<array>}>
     */
    public static function groups(Collection $accounts, Collection $cards): array
    {
        $groups = [];
        $claimed = [];

        foreach ($accounts->where('platform', Platform::Facebook) as $page) {
            $linked = $accounts->filter(fn (MetaAccount $a) => $a->platform === Platform::Instagram && $a->page_id && $a->page_id === $page->page_id);
            $claimed = [...$claimed, ...$linked->pluck('id')->all()];
            $groups[] = ['page' => $cards[$page->id], 'instagram' => $linked->map(fn ($a) => $cards[$a->id])->values()->all()];
        }

        foreach ($accounts->where('platform', Platform::Instagram)->whereNotIn('id', $claimed) as $ig) {
            $groups[] = ['page' => null, 'instagram' => [$cards[$ig->id]]];
        }

        return $groups;
    }

    /**
     * testConnection() + record token_checked_at / token_expires_at. Shared with `meta:accounts:test`.
     *
     * @return array{ok: bool, id?: string, name?: string, expires_at?: ?string, scopes?: array, error?: string}
     */
    public static function runTest(MetaMessagingService $meta, MetaAccount $account): array
    {
        try {
            $result = $meta->testConnection($account);
        } catch (Throwable $e) {
            Log::warning('Meta testConnection threw', ['meta_account_id' => $account->id, 'exception' => $e::class]);

            return ['ok' => false, 'error' => 'Unexpected error ('.class_basename($e).').'];
        }

        $updates = ['token_checked_at' => now()];
        if ($result['ok'] && array_key_exists('expires_at', $result)) {
            try {
                $updates['token_expires_at'] = $result['expires_at'] ? Carbon::parse($result['expires_at']) : null;
            } catch (Throwable) {
                // unparseable date: leave as is
            }
        }
        $account->forceFill($updates)->save();

        return $result;
    }

    /** Picture + live webhook subscription state (best effort, never fails the request). */
    private function refreshDetails(MetaAccount $account, MetaTokenService $tokens): void
    {
        try {
            $details = $tokens->accountDetails($account);
        } catch (Throwable $e) {
            Log::warning('Meta account details failed', ['meta_account_id' => $account->id, 'exception' => $e::class]);

            return;
        }

        if ($details['picture_url']) {
            $account->putSetting('picture_url', $details['picture_url']);
        }
        if ($details['username'] && $account->platform === Platform::Instagram) {
            $account->putSetting('username', $details['username']);
        }
        if ($details['subscribed'] !== null) {
            $account->putSetting('webhook_subscribed', $details['subscribed']);
            $account->putSetting('webhook_checked_at', now()->toIso8601String());
            if ($details['subscribed']) {
                $account->putSetting('webhook_fields', $details['subscribed_fields']);
                $account->putSetting('webhook_error', null);
            }
        }
    }
}
