{{-- Center pane: header with bot controls, state strip, messages, composer (Alpine: liveChat). --}}
@php
    $dots = 'bg-slate-50/70 bg-[radial-gradient(circle_at_1px_1px,rgb(148_163_184/0.22)_1px,transparent_0)] [background-size:22px_22px]';
@endphp
<section aria-label="Conversation" class="@container relative min-w-0 flex-1 flex-col bg-white md:flex"
    x-bind:class="{ 'flex': pane !== 'list', 'hidden': pane === 'list' }">

    {{-- Nothing selected --}}
    <div x-show="! selectedId" class="{{ $dots }} flex flex-1 flex-col items-center justify-center p-8 text-center">
        <div class="relative mb-7 h-28 w-40" aria-hidden="true">
            <div class="absolute top-0 left-4 h-16 w-32 rotate-[-6deg] rounded-2xl bg-white shadow-card ring-1 ring-line"></div>
            <div class="absolute top-3 left-6 flex h-16 w-32 rotate-[3deg] items-center gap-2.5 rounded-2xl bg-white px-3 shadow-card-hover ring-1 ring-line">
                <span class="size-8 shrink-0 rounded-full bg-gradient-to-br from-sky-100 to-violet-100"></span>
                <span class="flex-1 space-y-1.5"><span class="block h-2 w-14 rounded-full bg-slate-200"></span><span class="block h-2 w-10 rounded-full bg-slate-100"></span></span>
            </div>
            <div class="absolute right-0 bottom-0 flex size-11 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-indigo-600 text-white shadow-card-hover">
                <x-ui.icon name="messages" class="size-5" />
            </div>
        </div>
        <h2 class="text-base font-semibold text-ink">Pick a conversation</h2>
        <p class="mt-1.5 max-w-sm text-[13px] text-ink-muted">Read the thread, reply as your Page, or hand the chat back to the bot.</p>
        <div class="mt-5 hidden items-center gap-3 text-2xs text-slate-400 lg:flex">
            <span class="inline-flex items-center gap-1"><kbd class="rounded border border-line-strong bg-white px-1.5 py-px font-sans font-semibold text-slate-500">↑</kbd><kbd class="rounded border border-line-strong bg-white px-1.5 py-px font-sans font-semibold text-slate-500">↓</kbd> move</span>
            <span class="inline-flex items-center gap-1"><kbd class="rounded border border-line-strong bg-white px-1.5 py-px font-sans font-semibold text-slate-500">Enter</kbd> open</span>
            <span class="inline-flex items-center gap-1"><kbd class="rounded border border-line-strong bg-white px-1.5 py-px font-sans font-semibold text-slate-500">/</kbd> search</span>
        </div>
    </div>

    <div x-show="selectedId" x-cloak class="flex min-h-0 flex-1 flex-col">
        {{-- Header --}}
        <header class="flex min-h-16 shrink-0 items-center gap-2 border-b border-line bg-white px-2 py-2.5 sm:gap-3 sm:px-4">
            <button type="button" x-on:click="backToList()" class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-ink md:hidden" aria-label="Back to conversations">
                <x-ui.icon name="arrow-left" class="size-5" />
            </button>

            <button type="button" x-on:click="toggleContact()" class="-my-1 flex min-w-0 flex-1 items-center gap-3 rounded-xl py-1 pr-2 pl-1 text-left transition hover:bg-slate-50" x-bind:title="'Contact details for ' + (conv?.name ?? '')">
                @include('admin.inbox.partials.avatar', ['obj' => 'conv', 'size' => 'size-10 text-sm', 'badge' => 'xs'])
                <span class="min-w-0">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="truncate text-[15px] font-semibold tracking-tight text-ink" x-text="conv?.name"></span>
                        <span class="hidden shrink-0 items-center gap-1 rounded-full px-2 py-px text-2xs font-semibold @2xl:inline-flex"
                            x-bind:class="{
                                'bg-success-50 text-success-700': botState === 'active',
                                'bg-warning-50 text-warning-700': botState === 'paused',
                                'bg-violet-50 text-violet-700': botState === 'takeover',
                                'bg-slate-100 text-slate-600': botState === 'off' || botState === 'closed',
                            }">
                            <span class="size-1.5 rounded-full" x-bind:class="{
                                'bg-success-500 animate-pulse': botState === 'active',
                                'bg-warning-500': botState === 'paused',
                                'bg-violet-500': botState === 'takeover',
                                'bg-slate-400': botState === 'off' || botState === 'closed',
                            }"></span>
                            <span x-text="{ active: 'Bot active', paused: 'Bot paused', takeover: 'You’re handling', off: 'Bot off', closed: 'Closed' }[botState] ?? ''"></span>
                        </span>
                    </span>
                    <span class="flex min-w-0 items-center gap-1.5 text-xs text-ink-muted">
                        <span class="shrink-0" x-text="conv?.platform === 'instagram' ? 'Instagram' : 'Messenger'"></span>
                        <template x-if="conv?.page_name"><span class="flex min-w-0 items-center gap-1.5"><span class="text-slate-300">·</span><span class="truncate" x-text="conv.page_name"></span></span></template>
                        <template x-if="conv?.username"><span class="hidden min-w-0 items-center gap-1.5 @3xl:flex"><span class="text-slate-300">·</span><span class="truncate" x-text="'@' + conv.username"></span></span></template>
                    </span>
                </span>
            </button>

            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                {{-- Bot on/off --}}
                <button type="button" role="switch" x-bind:aria-checked="conv?.bot_enabled ? 'true' : 'false'" x-on:click="setBot(! conv.bot_enabled)"
                    class="inline-flex h-9 items-center gap-2 rounded-full border border-line bg-white pr-1.5 pl-1.5 shadow-xs transition hover:border-line-strong @sm:pr-3"
                    x-bind:title="conv?.bot_enabled ? 'The bot may reply in this conversation. Click to turn it off.' : 'The bot never replies in this conversation. Click to turn it on.'">
                    <span class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors duration-200"
                        x-bind:class="{ 'bg-success-500': conv?.bot_enabled, 'bg-slate-300': ! conv?.bot_enabled }">
                        <span class="absolute top-0.5 left-0.5 size-4 rounded-full bg-white shadow-sm transition-transform duration-200"
                            x-bind:class="{ 'translate-x-4': conv?.bot_enabled }"></span>
                    </span>
                    <span class="hidden items-center gap-1 text-[13px] font-medium text-slate-700 @sm:inline-flex">
                        <x-ui.icon name="bot" class="hidden size-4 text-slate-400 @2xl:block" />
                        <span class="@2xl:hidden">Bot</span>
                        <span class="hidden @2xl:inline" x-text="conv?.bot_enabled ? 'Bot on' : 'Bot off'"></span>
                    </span>
                </button>

                {{-- Take over / hand back --}}
                <div class="hidden @lg:block">
                    <x-ui.button size="sm" variant="primary" icon="hand" x-show="! conv?.human_takeover" x-on:click="takeOver()">Take over</x-ui.button>
                    <x-ui.button size="sm" variant="soft" icon="bot" x-show="conv?.human_takeover" x-cloak x-on:click="handBack()">Hand back to bot</x-ui.button>
                </div>
                <div class="@lg:hidden">
                    <x-ui.button size="sm" variant="primary" icon="hand" x-show="! conv?.human_takeover" x-on:click="takeOver()" aria-label="Take over" title="Take over" />
                    <x-ui.button size="sm" variant="soft" icon="bot" x-show="conv?.human_takeover" x-cloak x-on:click="handBack()" aria-label="Hand back to bot" title="Hand back to bot" />
                </div>

                {{-- Close / reopen --}}
                <div class="hidden @4xl:block">
                    <x-ui.button size="sm" icon="check-circle" x-show="conv?.status === 'open'" x-on:click="setStatus('closed')">Close</x-ui.button>
                    <x-ui.button size="sm" icon="refresh" x-show="conv?.status === 'closed'" x-cloak x-on:click="setStatus('open')">Reopen</x-ui.button>
                </div>

                <button type="button" x-on:click="toggleContact()" aria-label="Toggle contact details" x-bind:aria-pressed="panelVisible ? 'true' : 'false'"
                    class="hidden size-9 items-center justify-center rounded-lg transition @md:inline-flex"
                    x-bind:class="{ 'bg-brand-50 text-brand-700 hover:bg-brand-100': panelVisible, 'text-slate-500 hover:bg-slate-100 hover:text-ink': ! panelVisible }">
                    <x-ui.icon name="user" class="size-[18px]" />
                </button>

                <x-ui.dropdown align="right" width="w-60">
                    <x-slot:trigger>
                        <x-ui.button size="sm" variant="ghost" icon="dots-vertical" aria-label="More actions" />
                    </x-slot:trigger>
                    <x-ui.dropdown-item icon="user" x-on:click="close(); toggleContact()" class="@md:hidden">Contact details</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="check-circle" x-show="conv?.status === 'open'" x-on:click="close(); setStatus('closed')" class="@4xl:hidden">Close conversation</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="refresh" x-show="conv?.status === 'closed'" x-on:click="close(); setStatus('open')" class="@4xl:hidden">Reopen conversation</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="pause" x-show="paused" x-on:click="close(); clearPause()">Clear bot pause</x-ui.dropdown-item>
                    <x-ui.dropdown-divider />
                    <x-ui.dropdown-item icon="copy" x-on:click="close(); copyId()" description="PSID / IGSID">Copy customer ID</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="refresh" x-on:click="close(); refreshProfile()">Refresh profile from Meta</x-ui.dropdown-item>
                    <x-ui.dropdown-item icon="users" x-bind:href="contactsUrl()" href="{{ route('admin.contacts.index') }}">Open in Contacts</x-ui.dropdown-item>
                </x-ui.dropdown>
            </div>
        </header>

        {{-- State strip: why the bot will or won't answer --}}
        <div class="shrink-0" x-show="botState !== 'active' && botState !== 'none'" x-cloak>
            <div x-show="botState === 'paused'" class="flex items-center gap-3 border-b border-warning-100 bg-warning-50/80 px-4 py-2 text-[13px] text-warning-700 sm:px-5">
                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-warning-100"><x-ui.icon name="pause" class="size-3.5" /></span>
                <p class="min-w-0 flex-1"><span class="font-semibold">Bot paused</span> · resumes in <span class="font-semibold tabular-nums" x-text="duration(pausedMs)"></span><span class="hidden text-warning-700/80 @xl:inline"> because a human replied</span></p>
                <button type="button" x-on:click="clearPause()" class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold text-warning-700 ring-1 ring-warning-500/30 transition ring-inset hover:bg-warning-100">Clear pause</button>
            </div>
            <div x-show="botState === 'takeover'" class="flex items-center gap-3 border-b border-violet-100 bg-violet-50/80 px-4 py-2 text-[13px] text-violet-800 sm:px-5">
                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-100"><x-ui.icon name="hand" class="size-3.5" /></span>
                <p class="min-w-0 flex-1"><span class="font-semibold">You’re handling this conversation.</span><span class="hidden @2xl:inline"> The bot stays silent until you hand it back.</span></p>
                <button type="button" x-on:click="handBack()" class="shrink-0 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-violet-700 shadow-xs ring-1 ring-violet-200 transition ring-inset hover:bg-violet-50">Hand back to bot</button>
            </div>
            <div x-show="botState === 'off'" class="flex items-center gap-3 border-b border-line bg-slate-50 px-4 py-2 text-[13px] text-slate-600 sm:px-5">
                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-200/70"><x-ui.icon name="bot" class="size-3.5" /></span>
                <p class="min-w-0 flex-1"><span class="font-semibold text-slate-700">The bot is off</span> for this conversation. Only people reply here.</p>
                <button type="button" x-on:click="setBot(true)" class="shrink-0 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs ring-1 ring-line-strong transition ring-inset hover:bg-slate-50">Turn bot on</button>
            </div>
            <div x-show="botState === 'closed'" class="flex items-center gap-3 border-b border-line bg-slate-50 px-4 py-2 text-[13px] text-slate-600 sm:px-5">
                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-200/70"><x-ui.icon name="check" class="size-3.5" /></span>
                <p class="min-w-0 flex-1"><span class="font-semibold text-slate-700">Closed.</span> The bot won't reply until the conversation is reopened.</p>
                <button type="button" x-on:click="setStatus('open')" class="shrink-0 rounded-lg bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs ring-1 ring-line-strong transition ring-inset hover:bg-slate-50">Reopen</button>
            </div>
        </div>

        {{-- Messages --}}
        <div class="relative min-h-0 flex-1">
            <div x-ref="thread" x-on:scroll.throttle.100ms="onThreadScroll()" class="{{ $dots }} ui-scroll absolute inset-0 overflow-y-auto overscroll-contain" aria-live="polite" aria-relevant="additions">
                <div class="mx-auto flex min-h-full max-w-3xl flex-col justify-end px-3 pt-4 pb-5 sm:px-6">

                    {{-- Loading --}}
                    <div x-show="threadLoading && ! messages.length" class="space-y-4 py-4" aria-hidden="true">
                        <div class="flex items-end gap-2"><span class="ui-skeleton size-7 rounded-full"></span><span class="ui-skeleton h-10 w-56 rounded-2xl"></span></div>
                        <div class="flex justify-end"><span class="ui-skeleton h-14 w-64 rounded-2xl"></span></div>
                        <div class="flex items-end gap-2"><span class="ui-skeleton size-7 rounded-full"></span><span class="ui-skeleton h-10 w-40 rounded-2xl"></span></div>
                        <div class="flex justify-end"><span class="ui-skeleton h-10 w-48 rounded-2xl"></span></div>
                    </div>

                    {{-- Error --}}
                    <div x-show="threadError" x-cloak class="m-auto max-w-sm py-10 text-center">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-danger-50 text-danger-600"><x-ui.icon name="alert-triangle" class="size-5" /></span>
                        <p class="mt-3 text-sm font-semibold text-ink">Couldn't load this conversation</p>
                        <p class="mt-1 text-[13px] text-ink-muted" x-text="threadError"></p>
                        <x-ui.button size="sm" class="mt-4" icon="refresh" x-on:click="retryThread()">Try again</x-ui.button>
                    </div>

                    {{-- Older history --}}
                    <div x-show="hasOlder && ! threadLoading" x-cloak class="flex justify-center pb-3">
                        <button type="button" x-on:click="loadOlder()" x-bind:disabled="loadingOlder"
                            class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600 shadow-xs ring-1 ring-line transition hover:text-ink hover:ring-line-strong disabled:opacity-60">
                            <x-ui.spinner size="xs" x-show="loadingOlder" />
                            <x-ui.icon name="chevron-up" class="size-3.5" x-show="! loadingOlder" />
                            Load earlier messages
                        </button>
                    </div>

                    {{-- Start of conversation --}}
                    <div x-show="! hasOlder && ! threadLoading && ! threadError && conv" x-cloak class="flex flex-col items-center pt-6 pb-4 text-center">
                        @include('admin.inbox.partials.avatar', ['obj' => 'conv', 'size' => 'size-16 text-lg', 'badge' => 'sm'])
                        <p class="mt-3 text-[15px] font-semibold text-ink" x-text="conv?.name"></p>
                        <p class="mt-0.5 text-xs text-ink-muted">
                            <span x-text="conv?.platform === 'instagram' ? 'Instagram' : 'Messenger'"></span>
                            <template x-if="conv?.first_seen_at"><span> · first message <span x-text="longAgo(conv.first_seen_at)"></span></span></template>
                        </p>
                        <p x-show="! messages.length" class="mt-4 text-[13px] text-slate-400">No messages in this conversation yet.</p>
                    </div>

                    {{-- Messages + day separators --}}
                    <template x-for="row in threadRows" x-bind:key="row.key">
                        <div>
                            <template x-if="row.kind === 'day'">
                                <div class="sticky top-2 z-10 my-4 flex justify-center">
                                    <span class="rounded-full bg-white/90 px-3 py-1 text-2xs font-semibold text-slate-500 shadow-xs ring-1 ring-line backdrop-blur" x-text="row.label"></span>
                                </div>
                            </template>

                            <template x-if="row.kind === 'msg'">
                                <div class="flex items-end gap-2" x-bind:class="{ 'justify-end': row.out, 'mt-3': row.first, 'mt-1': ! row.first }">
                                    {{-- Customer avatar on the last bubble of a group --}}
                                    <template x-if="! row.out">
                                        <div class="w-7 shrink-0 pb-5">
                                            <template x-if="row.last">
                                                @include('admin.inbox.partials.avatar', ['obj' => 'conv', 'size' => 'size-7 text-[11px]', 'badge' => false])
                                            </template>
                                        </div>
                                    </template>

                                    <div class="flex max-w-[85%] min-w-0 flex-col gap-1 sm:max-w-[72%]" x-bind:class="{ 'items-end': row.out, 'items-start': ! row.out }">
                                        {{-- Sender label (first bubble of an outgoing group) --}}
                                        <template x-if="row.out && row.first">
                                            <span class="flex items-center gap-1 px-1 text-2xs font-semibold"
                                                x-bind:class="{ 'text-brand-600': row.m.sender_type === 'bot', 'text-violet-600': row.m.sender_type === 'human' }">
                                                <x-ui.icon name="sparkles" class="size-3" x-show="row.m.source === 'ai'" />
                                                <x-ui.icon name="zap" class="size-3" x-show="row.m.source === 'automation'" />
                                                <x-ui.icon name="info" class="size-3" x-show="row.m.source === 'fallback'" />
                                                <x-ui.icon name="bot" class="size-3" x-show="row.m.source === 'bot'" />
                                                <x-ui.icon name="user" class="size-3" x-show="row.m.source === 'admin'" />
                                                <x-ui.icon name="inbox" class="size-3" x-show="row.m.source === 'page'" />
                                                <span x-text="row.m.sender_label"></span>
                                            </span>
                                        </template>

                                        {{-- Attachments --}}
                                        <template x-for="(a, ai) in row.m.attachments" x-bind:key="ai">
                                            <div class="max-w-full">
                                                <template x-if="a.kind === 'image' && imageOk(a.url)">
                                                    <button type="button" x-on:click="lightbox = { url: a.url, label: a.label }" class="block overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-black/5 transition hover:opacity-95" x-bind:aria-label="'Open ' + a.label">
                                                        <img x-bind:src="a.url" x-bind:alt="a.label" loading="lazy" referrerpolicy="no-referrer" x-on:load="onMediaLoad()" x-on:error="imageFailed(a.url)" class="max-h-72 w-auto max-w-[min(280px,100%)] object-cover">
                                                    </button>
                                                </template>
                                                <template x-if="a.kind === 'video' && a.url">
                                                    <video x-bind:src="a.url" controls preload="metadata" x-on:loadedmetadata="onMediaLoad()" class="max-h-80 max-w-[min(300px,100%)] rounded-2xl bg-black ring-1 ring-black/5"></video>
                                                </template>
                                                <template x-if="a.kind === 'audio' && a.url">
                                                    <audio x-bind:src="a.url" controls preload="none" class="h-10 w-64 max-w-full"></audio>
                                                </template>
                                                <template x-if="(a.kind === 'file' || a.kind === 'link' || ! a.url || (a.kind === 'image' && ! imageOk(a.url)))">
                                                    <div>
                                                        <template x-if="a.url && ! (a.kind === 'image' && ! imageOk(a.url))">
                                                            <a x-bind:href="a.url" target="_blank" rel="noopener noreferrer nofollow"
                                                                class="inline-flex max-w-full items-center gap-2.5 rounded-2xl bg-white py-2 pr-3 pl-2 text-[13px] font-medium text-slate-700 shadow-xs ring-1 ring-line transition hover:text-ink hover:ring-line-strong">
                                                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                                                    <x-ui.icon name="link" class="size-4" x-show="a.kind === 'link'" />
                                                                    <x-ui.icon name="paperclip" class="size-4" x-show="a.kind !== 'link'" />
                                                                </span>
                                                                <span class="min-w-0 truncate" x-text="a.label"></span>
                                                                <x-ui.icon name="external-link" class="size-3.5 shrink-0 text-slate-400" />
                                                            </a>
                                                        </template>
                                                        <template x-if="! a.url || (a.kind === 'image' && ! imageOk(a.url))">
                                                            <span class="inline-flex items-center gap-2 rounded-2xl bg-white py-2 pr-3 pl-2 text-[13px] text-slate-500 ring-1 ring-line">
                                                                <span class="inline-flex size-8 items-center justify-center rounded-xl bg-slate-100 text-slate-400"><x-ui.icon name="image" class="size-4" /></span>
                                                                <span><span x-text="a.label"></span> <span class="text-slate-400">(no longer available)</span></span>
                                                            </span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Text bubble (kept on one line: whitespace inside is significant) --}}
                                        <template x-if="row.m.body">
                                            <div class="rounded-[20px] px-3.5 py-2 text-[14px] leading-[1.45] break-words whitespace-pre-wrap [overflow-wrap:anywhere]"
                                                x-bind:class="{
                                                    'bg-slate-100 text-ink': ! row.out,
                                                    'bg-brand-600 text-white shadow-xs': row.m.sender_type === 'bot',
                                                    'bg-violet-600 text-white shadow-xs': row.m.sender_type === 'human',
                                                    'rounded-tl-md': ! row.out && ! row.first,
                                                    'rounded-bl-md': ! row.out && ! row.last,
                                                    'rounded-tr-md': row.out && ! row.first,
                                                    'rounded-br-md': row.out && ! row.last,
                                                    'opacity-60': row.m.status === 'sending' || row.m.status === 'pending',
                                                    'ring-2 ring-danger-300 ring-offset-1 opacity-80': row.m.status === 'failed',
                                                    'opacity-60 line-through decoration-white/60': row.m.status === 'skipped',
                                                }"><template x-for="(p, pi) in linkify(row.m.body)" x-bind:key="pi"><span><template x-if="p.href"><a x-bind:href="p.href" target="_blank" rel="noopener noreferrer nofollow" class="font-medium underline decoration-current/40 underline-offset-2 hover:decoration-current" x-text="p.text"></a></template><template x-if="! p.href"><span x-text="p.text"></span></template></span></template></div>
                                        </template>

                                        {{-- Meta: time + delivery status --}}
                                        <template x-if="row.last || row.m.status === 'failed' || row.m.status === 'sending'">
                                            <div class="flex items-center gap-1.5 px-1 text-2xs text-slate-400" x-bind:class="{ 'justify-end': row.out }">
                                                <span x-text="clock(row.m.at)" x-bind:title="fullDate(row.m.at)" class="tabular-nums"></span>
                                                <template x-if="row.out && (row.m.status === 'sending' || row.m.status === 'pending')">
                                                    <span class="inline-flex items-center gap-1"><x-ui.spinner size="xs" /> Sending…</span>
                                                </template>
                                                <template x-if="row.out && row.m.status === 'sent'">
                                                    <span class="inline-flex items-center gap-0.5 text-slate-400"><x-ui.icon name="check" class="size-3" :stroke="2.5" /> Sent</span>
                                                </template>
                                                <template x-if="row.out && row.m.status === 'skipped'">
                                                    <span>Not sent</span>
                                                </template>
                                                <template x-if="row.out && row.m.status === 'failed'">
                                                    <span class="inline-flex items-center gap-2">
                                                        <span class="group/err relative">
                                                            <button type="button" class="inline-flex items-center gap-1 font-semibold text-danger-600 hover:text-danger-700">
                                                                <x-ui.icon name="alert-circle" class="size-3.5" /> Not delivered
                                                            </button>
                                                            <span role="tooltip" class="pointer-events-none absolute right-0 bottom-full z-20 mb-2 w-72 rounded-xl bg-slate-900 p-3 text-left text-xs leading-relaxed font-normal text-white opacity-0 shadow-pop transition-opacity duration-150 group-focus-within/err:pointer-events-auto group-focus-within/err:opacity-100 group-hover/err:pointer-events-auto group-hover/err:opacity-100">
                                                                <span class="block font-semibold" x-text="row.m.error_hint || 'Meta did not accept this message.'"></span>
                                                                <span x-show="row.m.error && row.m.error !== row.m.error_hint" class="mt-1.5 block font-mono text-[11px] break-words text-slate-400" x-text="row.m.error"></span>
                                                            </span>
                                                        </span>
                                                        <button type="button" x-show="! row.m.retried && (row.m.body || row.m.retryText)" x-on:click="retry(row.m)"
                                                            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold text-slate-600 ring-1 ring-line-strong hover:bg-white hover:text-ink">
                                                            <x-ui.icon name="refresh" class="size-3" /> Retry
                                                        </button>
                                                    </span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Jump to newest --}}
            <button type="button" x-show="! atBottom && messages.length" x-cloak x-on:click="scrollToBottom()"
                x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 translate-y-2" x-transition:leave="transition duration-100" x-transition:leave-end="opacity-0"
                class="absolute bottom-4 left-1/2 z-10 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full py-1.5 pr-3 pl-3.5 text-xs font-semibold shadow-pop"
                x-bind:class="{ 'bg-brand-600 text-white': unseen > 0, 'bg-white text-slate-700 ring-1 ring-line': ! unseen }">
                <span x-show="unseen > 0" x-text="unseen === 1 ? '1 new message' : unseen + ' new messages'"></span>
                <span x-show="! unseen">Jump to latest</span>
                <x-ui.icon name="chevron-down" class="size-3.5" />
            </button>
        </div>

        {{-- Composer --}}
        <div class="shrink-0 border-t border-line bg-white px-3 pt-2.5 pb-3 sm:px-5">
            <div class="mx-auto max-w-3xl space-y-2">
                <div x-show="conv && ! conv._partial && ! conv.account_active" x-cloak class="flex items-start gap-2 rounded-xl bg-danger-50 px-3 py-2 text-xs text-danger-700 ring-1 ring-danger-100 ring-inset">
                    <x-ui.icon name="alert-circle" class="mt-px size-4 shrink-0" />
                    <p><span class="font-semibold">This Page is disconnected or turned off.</span> Replies can't be sent until it's active again in <a href="{{ route('admin.meta-accounts.index') }}" class="font-semibold underline underline-offset-2">Pages &amp; Channels</a>.</p>
                </div>
                <div x-show="conv && conv.last_customer_message_at !== undefined && ! windowOpen" x-cloak class="flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2 text-xs text-warning-700 ring-1 ring-warning-100 ring-inset">
                    <x-ui.icon name="clock" class="mt-px size-4 shrink-0" />
                    <p>
                        <span class="font-semibold">Outside the 24-hour window.</span>
                        <template x-if="conv?.last_customer_message_at"><span>The customer last wrote <span x-text="longAgo(conv.last_customer_message_at)"></span>.</span></template>
                        Meta will reject messages until they write again.
                    </p>
                </div>
                <div x-show="windowOpen && windowLeftMs < 3 * 3600 * 1000" x-cloak class="flex items-center gap-1.5 px-1 text-2xs font-medium text-warning-700">
                    <x-ui.icon name="clock" class="size-3.5" />
                    Reply window closes in <span class="tabular-nums" x-text="duration(windowLeftMs)"></span>
                </div>

                <div class="rounded-2xl border border-line-strong bg-white shadow-xs transition-[border-color,box-shadow] focus-within:border-brand-500 focus-within:shadow-focus">
                    <label for="lc-composer" class="sr-only">Reply</label>
                    <textarea id="lc-composer" x-ref="composer" rows="1" x-model="draft" x-on:input="autosize()" x-on:keydown="onComposerKey($event)"
                        x-bind:placeholder="'Reply on ' + (conv?.platform === 'instagram' ? 'Instagram' : 'Messenger') + (conv?.page_name ? ' as ' + conv.page_name : '') + '…'"
                        x-bind:maxlength="maxChars" autocomplete="off"
                        class="block max-h-52 min-h-11 w-full resize-none border-0 bg-transparent px-4 pt-3 pb-1 text-sm leading-6 text-ink placeholder:text-slate-400 focus:ring-0 focus:outline-none focus-visible:outline-none"></textarea>
                    <div class="flex items-center gap-2 py-2 pr-2 pl-4">
                        <p class="min-w-0 flex-1 truncate text-2xs text-slate-400">
                            <span x-show="conv?.human_takeover" class="inline-flex items-center gap-1"><x-ui.icon name="hand" class="size-3" /> The bot stays silent while you handle this chat</span>
                            <span x-show="! conv?.human_takeover && conv?.takeover_minutes > 0" class="inline-flex items-center gap-1"><x-ui.icon name="pause" class="size-3" /> Sending pauses the bot for <span x-text="duration((conv?.takeover_minutes ?? 0) * 60000)"></span></span>
                            <span class="hidden @3xl:inline"><span x-show="conv?.human_takeover || conv?.takeover_minutes > 0"> · </span><kbd class="font-sans font-semibold">Enter</kbd> to send, <kbd class="font-sans font-semibold">Shift + Enter</kbd> for a new line</span>
                        </p>
                        <span x-show="draft.length" x-cloak class="shrink-0 text-2xs font-medium tabular-nums"
                            x-bind:class="{ 'text-slate-400': parts === 1 && ! tooLong, 'text-warning-600': parts > 1 && ! tooLong, 'text-danger-600': tooLong }"
                            x-bind:title="unit === 'bytes' ? 'Instagram counts bytes: emoji and accented letters use more than one.' : 'Messenger allows 2,000 characters per message.'">
                            <span x-text="number(used) + ' / ' + number(limit) + (unit === 'bytes' ? ' bytes' : '')"></span><span x-show="parts > 1 && ! tooLong" x-text="' · sends as ' + parts + ' messages'"></span><span x-show="tooLong"> · too long</span>
                        </span>
                        <button type="button" x-on:click="send()" x-bind:disabled="! canSend" aria-label="Send reply"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white shadow-xs transition hover:bg-brand-700 active:scale-95 disabled:bg-slate-100 disabled:text-slate-400 disabled:shadow-none">
                            <x-ui.icon name="send" class="size-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
