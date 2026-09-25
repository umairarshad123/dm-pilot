{{--
    One channel (Messenger Page or Instagram account) inside a channel card.
    Vars: $card (array from MetaAccountController::index), $compact (bool: nested IG row under its Page)
--}}
@php
    $account = $card['account'];
    $compact ??= false;
    $isIg = $account->platform === \App\Enums\Platform::Instagram;
    $pills = [
        'active' => ['success', 'Active'],
        'paused' => ['neutral', 'Paused'],
        'token_problem' => ['danger', 'Token problem'],
        'not_subscribed' => ['warning', 'Not subscribed'],
    ];
    [$tone, $statusLabel] = $pills[$card['status']] ?? $pills['active'];
    $username = $account->setting('username') ?: ($isIg ? $account->page_name : null);
    $subscribed = $account->setting('webhook_subscribed');
    $tokenProblem = $card['status'] === 'token_problem';
    $tokenError = $account->setting('token_error') ?: ($account->setting('deauthorized_at') ? 'The Facebook user removed the app. Reconnect this Page.' : null);
    $expired = $account->token_expires_at?->isPast();
    $name = $account->page_name ?: ($account->ownExternalId() ?: 'Account #'.$account->id);
@endphp

<div @class(['min-w-0', 'opacity-75' => ! $account->active && ! $compact])>
    {{-- Identity --}}
    <div class="flex items-start gap-3.5">
        @include('admin.meta-accounts._avatar', ['account' => $account, 'size' => $compact ? 'md' : 'lg', 'picture' => $card['picture']])
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <h3 @class(['truncate font-semibold text-ink', 'text-[15px]' => ! $compact, 'text-sm' => $compact])>
                    {{ $isIg && $username ? '@'.ltrim($username, '@') : $name }}
                </h3>
                <x-ui.badge :tone="$tone" size="sm" dot>{{ $statusLabel }}</x-ui.badge>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-ink-muted">
                <x-ui.channel-badge :platform="$account->platform" size="xs" />
                <span class="font-mono text-[11px] text-slate-400" title="{{ $isIg ? 'Instagram account ID' : 'Page ID' }}">{{ $account->ownExternalId() }}</span>
                @if ($account->auth_type === \App\Models\MetaAccount::AUTH_INSTAGRAM_LOGIN)
                    <x-ui.badge size="sm" tone="purple">Instagram Login</x-ui.badge>
                @endif
            </div>
        </div>
        <div class="-mt-1 -mr-2 shrink-0">
            @include('admin.meta-accounts._actions', ['account' => $account])
        </div>
    </div>

    @if ($tokenProblem && $tokenError)
        <x-ui.alert tone="danger" class="mt-4 !py-2.5 text-[13px]" :icon="false">
            <span class="font-semibold">Token problem:</span> {{ \Illuminate\Support\Str::limit($tokenError, 180) }}
            <x-slot:actions>
                <x-ui.button size="xs" variant="secondary" icon="refresh" :href="route('admin.meta-accounts.connect')">Reconnect</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @endif

    {{-- Health --}}
    <dl @class(['mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-line ring-1 ring-line sm:grid-cols-4', 'mt-3' => $compact])>
        <div class="bg-white px-3 py-2.5">
            <dt class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Token</dt>
            <dd class="mt-1 flex items-center gap-1.5 text-[13px] font-medium text-ink">
                @if ($tokenProblem || $expired)
                    <span class="size-1.5 shrink-0 rounded-full bg-danger-500"></span> Needs attention
                @elseif ($account->token_checked_at)
                    <span class="size-1.5 shrink-0 rounded-full bg-success-500"></span> Valid
                @else
                    <span class="size-1.5 shrink-0 rounded-full bg-slate-300"></span> Not checked
                @endif
            </dd>
            <p class="mt-0.5 truncate text-2xs text-ink-muted" title="Checked {{ $account->token_checked_at?->toDayDateTimeString() ?? 'never' }}">
                {{ $account->token_expires_at ? ($expired ? 'Expired ' : 'Expires ').$account->token_expires_at->diffForHumans(short: true) : 'Never expires' }}
            </p>
        </div>
        <div class="bg-white px-3 py-2.5">
            <dt class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Webhooks</dt>
            <dd class="mt-1 flex items-center gap-1.5 text-[13px] font-medium text-ink">
                @if ($subscribed === true)
                    <span class="size-1.5 shrink-0 rounded-full bg-success-500"></span> Subscribed
                @elseif ($subscribed === false)
                    <span class="size-1.5 shrink-0 rounded-full bg-warning-500"></span> Not subscribed
                @elseif ($card['last_inbound_at'])
                    <span class="size-1.5 shrink-0 rounded-full bg-success-500"></span> Receiving
                @else
                    <span class="size-1.5 shrink-0 rounded-full bg-slate-300"></span> Unknown
                @endif
            </dd>
            <p class="mt-0.5 truncate text-2xs text-ink-muted" title="{{ $account->setting('webhook_error') }}">
                {{ $subscribed === false ? 'Re-subscribe from the menu' : ($account->setting('webhook_checked_at') ? 'Checked '.\Illuminate\Support\Carbon::parse($account->setting('webhook_checked_at'))->diffForHumans(short: true) : 'Run Test connection') }}
            </p>
        </div>
        <div class="bg-white px-3 py-2.5">
            <dt class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Last message</dt>
            <dd class="mt-1 truncate text-[13px] font-medium text-ink" title="{{ $card['last_inbound_at']?->toDayDateTimeString() }}">
                {{ $card['last_inbound_at']?->diffForHumans(short: true) ?? 'None yet' }}
            </dd>
            <p class="mt-0.5 truncate text-2xs text-ink-muted">{{ number_format($account->conversations_count) }} {{ \Illuminate\Support\Str::plural('conversation', $account->conversations_count) }}</p>
        </div>
        <div class="bg-white px-3 py-2.5">
            <dt class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Messages 7d</dt>
            <dd class="mt-1 text-[13px] font-semibold text-ink tabular-nums">{{ number_format($card['messages_7d']) }}</dd>
            <p class="mt-0.5 truncate text-2xs text-ink-muted">in + out</p>
        </div>
    </dl>

    {{-- Bot --}}
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-[13px]">
            @if ($card['bot_enabled'] && $account->active)
                <span class="inline-flex size-6 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-ui.icon name="bot" class="size-3.5" /></span>
                <span class="font-medium text-ink">AI bot on</span>
            @else
                <span class="inline-flex size-6 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-ui.icon name="bot" class="size-3.5" /></span>
                <span class="font-medium text-ink-muted">{{ $account->active ? 'AI bot off' : 'Channel paused' }}</span>
            @endif
            <span class="text-2xs text-slate-400">{{ $card['bot_override'] ? '· custom settings' : '· global settings' }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <form method="POST" action="{{ route('admin.meta-accounts.open', $account) }}">
                @csrf <input type="hidden" name="to" value="chat">
                <x-ui.button type="submit" size="xs" variant="ghost" icon="message">Live Chat</x-ui.button>
            </form>
            <form method="POST" action="{{ route('admin.meta-accounts.open', $account) }}">
                @csrf <input type="hidden" name="to" value="bot">
                <x-ui.button type="submit" size="xs" variant="soft" icon="bot">Bot Studio</x-ui.button>
            </form>
        </div>
    </div>
</div>
