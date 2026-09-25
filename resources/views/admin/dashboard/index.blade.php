{{--
    Dashboard (admin.dashboard). Data: DashboardController@index.
    The analytics region (partials/live) is re-fetched every 60s from admin.dashboard.data and swapped in place.
--}}
@php
    $firstName = \Illuminate\Support\Str::of((string) auth()->user()?->name)->trim()->explode(' ')->first() ?: 'there';
    $account = $currentPage->account();
    $metrics = $insights['overview']['metrics'];
    $resolution = $metrics['bot_resolution_rate']['value'];
    $periodLabel = 'last '.$days.' days';
    $issues = collect($health)->reject(fn ($c) => $c['ok'])->count();
@endphp

<x-layouts.app title="Dashboard" width="wide">
    <div class="space-y-6"
        x-data="{
            url: @js($links['data'].'?period='.$days),
            hash: @js($liveHash), auto: true, busy: false, updatedAt: Date.now(), now: Date.now(),
            init() {
                setInterval(() => { this.now = Date.now() }, 15000);
                setInterval(() => { if (this.auto && document.visibilityState === 'visible') this.refresh(true) }, 60000);
            },
            get ago() {
                const s = Math.max(0, Math.round((this.now - this.updatedAt) / 1000));
                return s < 45 ? 'just now' : (s < 3600 ? Math.round(s / 60) + 'm ago' : Math.round(s / 3600) + 'h ago');
            },
            async refresh(silent = false) {
                if (this.busy) return;
                this.busy = true;
                try {
                    const res = await api(this.url);
                    if (res.hash !== this.hash) this.$refs.live.innerHTML = res.html;
                    this.hash = res.hash;
                    this.updatedAt = this.now = Date.now();
                    if (! silent) toast('Dashboard updated', 'info', 2000);
                } catch (e) {
                    if (! silent) toast(e.message || 'Could not refresh', 'error');
                } finally { this.busy = false }
            },
        }">

        {{-- Hero --}}
        <section class="relative overflow-hidden rounded-(--radius-card) border border-line bg-surface shadow-card">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(60rem_20rem_at_100%_-20%,var(--color-brand-100),transparent_60%),radial-gradient(30rem_16rem_at_-10%_120%,#ede9fe,transparent_60%)] opacity-80" aria-hidden="true"></div>
            <div class="relative flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-[13px] font-medium text-ink-muted">
                        @if ($account)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 py-0.5 pr-2.5 pl-1 ring-1 ring-line">
                                <x-ui.channel-badge :platform="$account->platform" variant="icon" size="xs" />
                                <span class="max-w-48 truncate text-slate-700">{{ $currentPage->label() }}</span>
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-0.5 ring-1 ring-line">
                                <x-ui.icon name="layers" class="size-3.5 text-slate-400" />
                                <span class="text-slate-700">All pages</span>
                                @if ($connectedPages->count())<span class="text-slate-400">· {{ $connectedPages->count() }}</span>@endif
                            </span>
                        @endif
                        <span x-data="{ d: new Date() }" x-text="d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })">{{ now()->format('l, F j') }}</span>
                    </p>
                    <h2 class="mt-3 text-2xl font-semibold tracking-tight text-ink sm:text-[28px] sm:leading-9">
                        <span x-data="{ h: new Date().getHours() }" x-text="(h < 5 ? 'Good evening' : h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening') + ', ' + @js($firstName)">Welcome back, {{ $firstName }}</span>
                    </h2>
                    <p class="mt-1.5 max-w-2xl text-[14px] text-slate-600">
                        @if (! $hasPages)
                            Let's get your first page connected. It takes about five minutes.
                        @elseif (! $hasActivity)
                            No messages in the {{ $periodLabel }} yet. Send your page a test message to see it here.
                        @else
                            Your bot handled <span class="font-semibold text-ink">{{ \App\Http\Controllers\Admin\DashboardController::compact((int) $metrics['bot_replies']['value']) }}</span> replies
                            @if ($resolution !== null)
                                and resolved <span class="font-semibold text-ink">{{ rtrim(rtrim(number_format($resolution, 1), '0'), '.') }}%</span> of conversations without a human
                            @endif
                            in the {{ $periodLabel }}.
                        @endif
                    </p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                    <nav class="inline-flex w-full rounded-lg bg-slate-100/90 p-1 ring-1 ring-line ring-inset sm:w-auto" aria-label="Period">
                        @foreach ($periods as $p)
                            <a href="{{ route('admin.dashboard', ['period' => $p]) }}" @if ($p === $days) aria-current="page" @endif
                                @class([
                                    'flex-1 rounded-md px-3 py-1.5 text-center text-[13px] font-semibold whitespace-nowrap transition sm:flex-none',
                                    'bg-white text-ink shadow-xs ring-1 ring-line' => $p === $days,
                                    'text-slate-500 hover:text-ink' => $p !== $days,
                                ])>{{ $p }}d</a>
                        @endforeach
                    </nav>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button size="sm" variant="secondary" icon="message" :href="$links['live_chat']">Open Live Chat</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" icon="bot" :href="$links['bot']">Test your bot</x-ui.button>
                        <x-ui.button size="sm" variant="primary" icon="plus" :href="$links['connect']">Connect page</x-ui.button>
                    </div>
                </div>
            </div>

            {{-- Status strip --}}
            <div class="relative flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line/80 bg-white/60 px-5 py-2.5 text-[12.5px] text-ink-muted sm:px-6">
                <button type="button" class="inline-flex items-center gap-2 rounded-full font-medium hover:text-ink"
                    x-on:click="$dispatch('toggle-health')" aria-controls="system-health">
                    @if ($issues === 0)
                        <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success-500 opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-success-500"></span></span>
                        <span class="text-success-700">All systems operational</span>
                    @else
                        <span class="inline-flex size-2 rounded-full bg-warning-500"></span>
                        <span class="text-warning-700">{{ $issues }} {{ $issues === 1 ? 'check needs' : 'checks need' }} attention</span>
                    @endif
                </button>
                @if ($stats['needs_human'] > 0)
                    <a href="{{ $links['live_chat'] }}" class="inline-flex items-center gap-1.5 font-medium text-danger-700 hover:underline">
                        <x-ui.icon name="hand" class="size-3.5" />{{ $stats['needs_human'] }} waiting for a human
                    </a>
                @endif
                <span class="inline-flex items-center gap-1.5"><x-ui.icon name="messages" class="size-3.5 text-slate-400" />{{ number_format($stats['open_conversations']) }} open conversations</span>
                <span class="ml-auto inline-flex items-center gap-2">
                    <span class="hidden sm:inline">Updated <span x-text="ago">just now</span></span>
                    <label class="inline-flex cursor-pointer items-center gap-1.5 select-none">
                        <input type="checkbox" class="size-3.5 rounded border-line-strong text-brand-600 focus:ring-brand-500" x-model="auto">
                        Auto-refresh
                    </label>
                    <button type="button" class="inline-flex size-7 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-ink"
                        x-on:click="refresh()" aria-label="Refresh now" title="Refresh now">
                        <x-ui.icon name="refresh" class="size-3.5" x-bind:class="{ 'animate-spin': busy }" />
                    </button>
                </span>
            </div>
        </section>

        @if (! $hasPages)
            @include('admin.dashboard.partials.onboarding')
        @endif

        @unless ($checklist['complete'])
            @include('admin.dashboard.partials.checklist')
        @endunless

        {{-- Live analytics (swapped by the poller) --}}
        <div x-ref="live" aria-live="polite">
            {!! $liveHtml !!}
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            @include('admin.dashboard.partials.health')
            @include('admin.dashboard.partials.connection')
        </div>

        @if ($checklist['complete'])
            <div x-data="{ open: false }" class="text-center">
                <button type="button" class="inline-flex items-center gap-1.5 text-[13px] font-medium text-ink-muted hover:text-ink" x-on:click="open = ! open" x-bind:aria-expanded="open">
                    <x-ui.icon name="check-circle" class="size-4 text-success-600" /> Setup complete · <span x-text="open ? 'Hide checklist' : 'View setup checklist'">View setup checklist</span>
                </button>
                <div x-show="open" x-collapse x-cloak class="mt-4 text-left">
                    @include('admin.dashboard.partials.checklist')
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
