{{-- Left pane: search, tabs, channel filter, conversation rows (Alpine: liveChat). --}}
@php
    $tabs = [
        'all' => 'All',
        'unread' => 'Unread',
        'human' => 'Needs human',
        'bot' => 'Bot active',
        'closed' => 'Closed',
    ];
@endphp
<aside data-lc-list aria-label="Conversations"
    class="w-full min-w-0 shrink-0 flex-col border-r border-line bg-surface md:flex md:w-[300px] lg:w-[320px] 2xl:w-[340px]"
    x-bind:class="{ 'flex': pane === 'list', 'hidden': pane !== 'list' }">

    {{-- Search + channel filter --}}
    <div class="space-y-3 border-b border-line px-3 pt-3 pb-2.5">
        <div class="flex items-center gap-2">
            <label class="relative block min-w-0 flex-1">
                <span class="sr-only">Search conversations</span>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input type="search" x-ref="search" x-model="q" x-on:input.debounce.300ms="reloadList()"
                    x-on:keydown.escape="q = ''; reloadList(); $el.blur()"
                    placeholder="Search name, email, phone…" autocomplete="off"
                    class="ui-input h-9 rounded-xl border-transparent bg-slate-100/80 pr-8 pl-9 text-[13px] shadow-none hover:border-transparent focus:bg-white">
                <kbd x-show="! q" class="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 rounded border border-line-strong bg-white px-1.5 text-2xs font-medium text-slate-400 sm:block">/</kbd>
            </label>

            <x-ui.dropdown align="right" width="w-52">
                <x-slot:trigger>
                    <button type="button" aria-label="Filter by channel"
                        class="relative inline-flex size-9 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-ink"
                        x-bind:class="{ 'bg-brand-50 text-brand-700 hover:bg-brand-100': platform !== '' }">
                        <x-ui.icon name="filter" class="size-4" />
                        <span x-show="platform !== ''" x-cloak class="absolute top-1.5 right-1.5 size-2 rounded-full bg-brand-600 ring-2 ring-white"></span>
                    </button>
                </x-slot:trigger>
                <x-slot:header>
                    <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Channel</p>
                </x-slot:header>
                <button type="button" role="menuitem" tabindex="-1" x-on:click="setPlatform(''); close(true)"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none">
                    <span class="inline-flex size-4 items-center justify-center rounded-full bg-slate-900 text-white"><x-ui.icon name="layers" class="size-2.5" /></span>
                    <span class="flex-1">All channels</span>
                    <x-ui.icon name="check" class="size-4 text-brand-600" x-show="platform === ''" />
                </button>
                <button type="button" role="menuitem" tabindex="-1" x-on:click="setPlatform('facebook'); close(true)"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none">
                    <x-ui.channel-badge platform="facebook" variant="icon" size="xs" class="!ring-0" />
                    <span class="flex-1">Messenger</span>
                    <x-ui.icon name="check" class="size-4 text-brand-600" x-show="platform === 'facebook'" />
                </button>
                <button type="button" role="menuitem" tabindex="-1" x-on:click="setPlatform('instagram'); close(true)"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none">
                    <x-ui.channel-badge platform="instagram" variant="icon" size="xs" class="!ring-0" />
                    <span class="flex-1">Instagram</span>
                    <x-ui.icon name="check" class="size-4 text-brand-600" x-show="platform === 'instagram'" />
                </button>
            </x-ui.dropdown>
        </div>

        {{-- Tabs --}}
        <div class="-mx-3 flex gap-0.5 overflow-x-auto px-3 pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist" aria-label="Filter conversations">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" x-on:click="setView('{{ $key }}'); $el.scrollIntoView({ inline: 'nearest', block: 'nearest', behavior: 'smooth' })"
                    x-init="view === '{{ $key }}' && $nextTick(() => $el.scrollIntoView({ inline: 'nearest', block: 'nearest' }))"
                    x-bind:aria-selected="view === '{{ $key }}'"
                    class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full px-2 text-xs font-semibold whitespace-nowrap transition"
                    x-bind:class="{ 'bg-slate-900 text-white shadow-xs': view === '{{ $key }}', 'text-slate-600 hover:bg-slate-100 hover:text-ink': view !== '{{ $key }}' }">
                    {{ $label }}
                    @if ($key === 'unread')
                        <span x-show="counts.unread > 0" x-cloak x-text="counts.unread > 99 ? '99+' : counts.unread"
                            class="rounded-full px-1.5 text-2xs leading-4 tabular-nums"
                            x-bind:class="{ 'bg-white/20 text-white': view === 'unread', 'bg-brand-600 text-white': view !== 'unread' }"></span>
                    @elseif ($key === 'human')
                        <span x-show="counts.human > 0" x-cloak x-text="counts.human > 99 ? '99+' : counts.human"
                            class="rounded-full px-1.5 text-2xs leading-4 tabular-nums"
                            x-bind:class="{ 'bg-white/20 text-white': view === 'human', 'bg-danger-500 text-white': view !== 'human' }"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Rows --}}
    <div x-ref="list" x-on:scroll.throttle.150ms="onListScroll($el)" class="ui-scroll relative min-h-0 flex-1 overflow-y-auto overscroll-contain">
        <div x-show="listLoading" x-cloak class="absolute inset-x-0 top-0 z-10 h-0.5 overflow-hidden bg-brand-100">
            <div class="h-full w-full animate-pulse bg-brand-500"></div>
        </div>

        {{-- Empty: nothing at all / nothing for this filter --}}
        <div x-show="! items.length && ! listLoading" x-cloak class="px-6 py-14 text-center">
            <template x-if="view === 'all' && ! q.trim() && ! platform">
                <div class="flex flex-col items-center">
                    {{-- Illustration: two chat bubbles --}}
                    <div class="relative mb-6 h-24 w-32" aria-hidden="true">
                        <div class="absolute inset-0 -m-4 rounded-full bg-gradient-to-b from-brand-50 to-transparent"></div>
                        <div class="absolute top-1 left-0 flex h-11 w-20 items-center gap-1 rounded-2xl rounded-bl-md bg-white px-3 shadow-card ring-1 ring-line">
                            <span class="size-1.5 animate-pulse rounded-full bg-slate-300"></span>
                            <span class="size-1.5 animate-pulse rounded-full bg-slate-300 [animation-delay:150ms]"></span>
                            <span class="size-1.5 animate-pulse rounded-full bg-slate-300 [animation-delay:300ms]"></span>
                        </div>
                        <div class="absolute right-0 bottom-1 flex h-11 w-24 items-center justify-center rounded-2xl rounded-br-md bg-gradient-to-br from-brand-500 to-indigo-600 text-white shadow-card-hover">
                            <x-ui.icon name="sparkles" class="size-5" />
                        </div>
                    </div>
                    <h3 class="text-[15px] font-semibold text-ink">No conversations yet</h3>
                    <p class="mt-1.5 max-w-60 text-[13px] text-ink-muted">Send a DM to your Page to see it here. Replies from the bot show up live.</p>
                    <template x-if="! hasPages">
                        <x-ui.button variant="primary" size="sm" icon="plus" :href="route('admin.meta-accounts.connect')" class="mt-5">Connect a page</x-ui.button>
                    </template>
                </div>
            </template>
            <template x-if="! (view === 'all' && ! q.trim() && ! platform)">
                <div class="flex flex-col items-center">
                    <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        <x-ui.icon name="inbox" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-[15px] font-semibold text-ink" x-text="emptyTitle()"></h3>
                    <p class="mt-1 max-w-64 text-[13px] text-ink-muted" x-text="emptyText()"></p>
                    <button type="button" x-show="q.trim() || platform" x-on:click="q = ''; platform = ''; reloadList()" class="ui-link mt-4 text-[13px] font-medium">Clear filters</button>
                </div>
            </template>
        </div>

        <ul role="listbox" aria-label="Conversations" class="space-y-px p-1.5">
            <template x-for="(c, i) in items" x-bind:key="c.id">
                <li>
                    <button type="button" data-lc-row role="option" x-bind:id="'lc-row-' + c.id" x-bind:aria-selected="c.id === selectedId"
                        x-on:click="open(c.id)" x-on:focus="cursor = i"
                        class="group relative flex w-full items-start gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors focus-visible:outline-offset-[-2px]"
                        x-bind:class="{ 'bg-brand-50/80': c.id === selectedId, 'hover:bg-slate-50': c.id !== selectedId, 'bg-slate-50': c.id !== selectedId && i === cursor }">
                        <span x-show="c.id === selectedId" class="absolute inset-y-3 left-0 w-[3px] rounded-r-full bg-brand-600" aria-hidden="true"></span>

                        @include('admin.inbox.partials.avatar', ['obj' => 'c', 'size' => 'size-11 text-sm', 'badge' => 'xs'])

                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline gap-2">
                                <span class="min-w-0 flex-1 truncate text-[13.5px] text-ink"
                                    x-bind:class="{ 'font-semibold': c.unread_count > 0, 'font-medium': ! c.unread_count }" x-text="c.name"></span>
                                <span class="shrink-0 text-2xs tabular-nums" x-bind:title="fullDate(c.last_message_at)"
                                    x-bind:class="{ 'font-semibold text-brand-600': c.unread_count > 0, 'text-slate-400': ! c.unread_count }"
                                    x-text="shortAgo(c.last_message_at)"></span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-[13px]"
                                    x-bind:class="{ 'text-ink': c.unread_count > 0, 'text-ink-muted': ! c.unread_count }">
                                    <span x-show="c.last_sender === 'human'" class="font-medium text-slate-500">You: </span>
                                    <span x-show="c.last_sender === 'bot'" class="font-medium text-brand-600/80">Bot: </span>
                                    <span x-text="c.excerpt || 'No messages yet'" x-bind:class="{ 'italic text-slate-400': ! c.excerpt }"></span>
                                </span>
                                <span x-show="c.unread_count > 0" x-cloak x-text="c.unread_count > 99 ? '99+' : c.unread_count"
                                    class="inline-flex h-[18px] min-w-[18px] shrink-0 items-center justify-center rounded-full bg-brand-600 px-1.5 text-2xs font-semibold text-white tabular-nums"></span>
                            </span>
                            <span x-show="c.human_takeover || c.bot_paused_until || c.last_failed || c.status === 'closed' || ! c.bot_enabled" x-cloak class="mt-1.5 flex flex-wrap items-center gap-1">
                                <span x-show="c.last_failed" class="inline-flex items-center gap-1 rounded-md bg-danger-50 px-1.5 py-px text-2xs font-semibold text-danger-700 ring-1 ring-danger-100 ring-inset">
                                    <x-ui.icon name="alert-circle" class="size-3" /> Failed
                                </span>
                                <span x-show="c.human_takeover" class="inline-flex items-center gap-1 rounded-md bg-violet-50 px-1.5 py-px text-2xs font-semibold text-violet-700 ring-1 ring-violet-100 ring-inset">
                                    <x-ui.icon name="hand" class="size-3" /> Human
                                </span>
                                <span x-show="c.bot_paused_until && ! c.human_takeover" class="inline-flex items-center gap-1 rounded-md bg-warning-50 px-1.5 py-px text-2xs font-semibold text-warning-700 ring-1 ring-warning-100 ring-inset">
                                    <x-ui.icon name="pause" class="size-3" /> Paused
                                </span>
                                <span x-show="! c.bot_enabled" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-px text-2xs font-semibold text-slate-600 ring-1 ring-slate-200 ring-inset">
                                    <x-ui.icon name="bot" class="size-3" /> Bot off
                                </span>
                                <span x-show="c.status === 'closed'" class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-1.5 py-px text-2xs font-semibold text-slate-600 ring-1 ring-slate-200 ring-inset">
                                    <x-ui.icon name="check" class="size-3" /> Closed
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </template>
        </ul>

        <div x-show="loadingMore" x-cloak class="flex justify-center py-4"><x-ui.spinner size="sm" class="text-slate-400" /></div>
        <p x-show="items.length > 8 && listPage >= listLastPage && ! loadingMore" x-cloak class="py-5 text-center text-2xs text-slate-400">That's everything</p>
    </div>

    {{-- Page context --}}
    <button type="button" x-on:click="$dispatch('open-page-switcher')"
        class="flex items-center gap-2 border-t border-line px-4 py-2.5 text-left text-xs text-ink-muted transition hover:bg-slate-50 hover:text-ink">
        <x-ui.icon name="layers" class="size-3.5 text-slate-400" />
        <span class="min-w-0 flex-1 truncate">Showing <span class="font-medium text-slate-700" x-text="pageLabel"></span></span>
        <span class="font-medium text-brand-600">Change</span>
    </button>
</aside>
