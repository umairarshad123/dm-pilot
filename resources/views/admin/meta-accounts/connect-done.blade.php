<x-layouts.app title="Channels connected" width="narrow">
    @include('admin.meta-accounts._stepper', ['step' => 3])

    <div class="ui-card relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,var(--color-success-50),transparent_60%)]" aria-hidden="true"></div>
        <div class="relative px-6 pt-10 pb-6 text-center sm:px-10">
            <span class="mx-auto inline-flex size-14 items-center justify-center rounded-full bg-success-500 text-white shadow-sm ring-8 ring-success-100">
                <x-ui.icon name="check" class="size-7" :stroke="2.5" />
            </span>
            <h2 class="mt-5 text-xl font-semibold tracking-tight text-ink">You're connected</h2>
            <p class="mt-1.5 text-sm text-ink-muted">
                {{ $accounts->count() }} {{ \Illuminate\Support\Str::plural('channel', $accounts->count()) }} ready. New DMs now reach your AI assistant.
            </p>
        </div>

        <ul class="relative divide-y divide-line border-t border-line">
            @foreach ($accounts as $account)
                @php($r = $results[$account->id] ?? [])
                <li class="flex items-center gap-3.5 px-5 py-3.5 sm:px-6">
                    @include('admin.meta-accounts._avatar', ['account' => $account, 'size' => 'md', 'picture' => $account->setting('picture_url')])
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-ink">{{ $account->platform === \App\Enums\Platform::Instagram ? '@'.$account->page_name : $account->page_name }}</p>
                        <x-ui.channel-badge :platform="$account->platform" variant="plain" size="xs" />
                    </div>
                    @if (($r['subscribed'] ?? null) === true)
                        <x-ui.badge tone="success" dot>Webhooks on</x-ui.badge>
                    @elseif (($r['subscribed'] ?? null) === false)
                        <x-ui.tooltip :text="$r['error'] ?? 'Subscribe failed'" position="left">
                            <x-ui.badge tone="warning" icon="alert-triangle">Subscribe failed</x-ui.badge>
                        </x-ui.tooltip>
                    @else
                        <x-ui.badge tone="neutral">Saved</x-ui.badge>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    <h3 class="mt-8 mb-3 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Next steps</h3>
    <div class="grid gap-3 sm:grid-cols-3">
        @php($first = $accounts->first())
        <form method="POST" action="{{ route('admin.meta-accounts.open', $first) }}" class="contents">
            @csrf <input type="hidden" name="to" value="bot">
            <button type="submit" class="ui-card group flex flex-col items-start p-4 text-left transition hover:shadow-card-hover">
                <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-ui.icon name="bot" class="size-4" /></span>
                <span class="mt-3 text-sm font-semibold text-ink">Set up Bot Studio</span>
                <span class="mt-0.5 text-[13px] text-ink-muted">Tone, business info and FAQs.</span>
            </button>
        </form>
        <div class="ui-card flex flex-col items-start p-4">
            <span class="inline-flex size-9 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-ui.icon name="send" class="size-4" /></span>
            <span class="mt-3 text-sm font-semibold text-ink">Send a test DM</span>
            <span class="mt-0.5 text-[13px] text-ink-muted">Message your Page from a personal account and watch the reply.</span>
        </div>
        <form method="POST" action="{{ route('admin.meta-accounts.open', $first) }}" class="contents">
            @csrf <input type="hidden" name="to" value="chat">
            <button type="submit" class="ui-card group flex flex-col items-start p-4 text-left transition hover:shadow-card-hover">
                <span class="inline-flex size-9 items-center justify-center rounded-lg bg-success-50 text-success-600"><x-ui.icon name="message" class="size-4" /></span>
                <span class="mt-3 text-sm font-semibold text-ink">Open Live Chat</span>
                <span class="mt-0.5 text-[13px] text-ink-muted">See conversations as they arrive.</span>
            </button>
        </form>
    </div>

    <div class="mt-8 flex justify-center">
        <x-ui.button :href="route('admin.meta-accounts.index')" icon-right="arrow-right">Go to Pages & Channels</x-ui.button>
    </div>
</x-layouts.app>
