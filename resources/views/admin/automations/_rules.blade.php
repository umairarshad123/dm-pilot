{{-- Keyword rules list (client-rendered from $rules; reorder / toggle / duplicate / delete over JSON). --}}
<section class="ui-card">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
        <div class="flex min-w-0 items-start gap-3">
            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-ui.icon name="zap" class="size-[18px]" /></span>
            <div class="min-w-0">
                <h2 class="flex items-center gap-2 text-[15px] font-semibold text-ink">
                    Keyword rules
                    <span class="rounded-full bg-slate-100 px-1.5 text-2xs font-semibold text-slate-600 tabular-nums" x-text="rules.length">{{ count($rules) }}</span>
                </h2>
                <p class="mt-0.5 text-[13px] text-ink-muted">When a message matches, the reply is sent instantly and the AI is skipped. Top rule wins: drag or use the arrows to change the order.</p>
            </div>
        </div>
        <x-ui.button size="sm" variant="primary" icon="plus" x-on:click="openCreate()">New rule</x-ui.button>
    </header>

    {{-- Empty state with starters --}}
    <div x-show="rules.length === 0" @if (count($rules)) x-cloak @endif class="p-5">
        <div class="rounded-xl border border-dashed border-line-strong p-6 text-center">
            <span class="mx-auto inline-flex size-11 items-center justify-center rounded-full bg-violet-50 text-violet-600"><x-ui.icon name="zap" class="size-5" /></span>
            <h3 class="mt-3 text-sm font-semibold text-ink">No keyword rules {{ $isPage ? 'for this page' : 'yet' }}</h3>
            <p class="mx-auto mt-1 max-w-md text-[13px] text-ink-muted">Answer the questions you get every day instantly, word for word. Start from a template:</p>
            <div class="mt-5 grid gap-2 text-left sm:grid-cols-2">
                @foreach ($starters as $i => $s)
                    <button type="button" x-on:click="openCreate(starters[{{ $i }}])"
                        class="group flex items-center gap-3 rounded-xl border border-line bg-white p-3 shadow-xs transition hover:border-violet-200 hover:bg-violet-50/40 hover:shadow-card-hover">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-violet-100 group-hover:text-violet-700"><x-ui.icon :name="$s['icon']" class="size-4" /></span>
                        <span class="min-w-0">
                            <span class="block text-[13px] font-semibold text-ink">{{ $s['title'] }}</span>
                            <span class="block truncate text-xs text-ink-muted">{{ $s['hint'] }}</span>
                        </span>
                        <x-ui.icon name="plus" class="ml-auto size-4 text-slate-300 group-hover:text-violet-600" />
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    @foreach (($isPage ? ['page' => 'This page', 'global' => 'All pages (also apply here)'] : ['global' => null]) as $group => $groupLabel)
        <div x-show="group(@js($group)).length > 0" @if (count($rules) === 0) x-cloak @endif>
            @if ($groupLabel)
                <div class="flex items-center justify-between gap-2 border-b border-line bg-slate-50/70 px-5 py-2">
                    <p class="text-2xs font-semibold tracking-wider text-slate-500 uppercase">{{ $groupLabel }}</p>
                    @if ($group === 'global')
                        <p class="text-2xs text-slate-400">Checked after this page's rules</p>
                    @endif
                </div>
            @endif
            <ul class="divide-y divide-line">
                <template x-for="(rule, i) in group(@js($group))" :key="rule.id">
                    <li class="group/rule relative flex gap-3 px-3 py-4 transition sm:px-5"
                        x-bind:class="{ 'bg-brand-50/40': drag && drag !== rule && drag.meta_account_id === rule.meta_account_id, 'opacity-40': drag === rule, 'hover:bg-slate-50/60': !drag }"
                        x-on:dragover.prevent x-on:drop.prevent="dropOn(rule)">
                        {{-- order --}}
                        <div class="flex shrink-0 flex-col items-center gap-0.5 pt-0.5">
                            <span class="cursor-grab rounded p-0.5 text-slate-300 hover:text-slate-500" draggable="true"
                                x-on:dragstart="drag = rule; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="drag = null" title="Drag to reorder" aria-hidden="true">
                                <x-ui.icon name="dots-vertical" class="size-4" />
                            </span>
                            <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-25" x-on:click="move(rule, -1)" x-bind:disabled="i === 0" x-bind:aria-label="'Move ' + rule.name + ' up'"><x-ui.icon name="chevron-up" class="size-4" /></button>
                            <button type="button" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-25" x-on:click="move(rule, 1)" x-bind:disabled="i === group(@js($group)).length - 1" x-bind:aria-label="'Move ' + rule.name + ' down'"><x-ui.icon name="chevron-down" class="size-4" /></button>
                        </div>

                        {{-- body --}}
                        <div class="min-w-0 flex-1" x-bind:class="{ 'opacity-60': !rule.active }">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="truncate text-left text-[14px] font-semibold text-ink hover:text-brand-700" x-on:click="openEdit(rule)" x-text="rule.name"></button>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-2xs font-semibold ring-1 ring-inset"
                                    x-bind:class="{ 'bg-slate-800 text-white ring-slate-800': rule.meta_account_id === null, 'bg-brand-50 text-brand-700 ring-brand-100': rule.meta_account_id !== null }"
                                    x-text="rule.scope_label"></span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-2xs font-medium text-slate-600" x-text="matchLabel(rule.match_type)"></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <template x-for="k in rule.keywords.slice(0, 8)" :key="k">
                                    <span class="rounded-md bg-violet-50 px-1.5 py-0.5 text-xs font-medium text-violet-700 ring-1 ring-violet-100 ring-inset"
                                        x-bind:class="{ 'font-mono': rule.match_type === 'regex' }" x-text="k"></span>
                                </template>
                                <span x-show="rule.keywords.length > 8" class="px-1 text-xs text-ink-muted" x-text="'+' + (rule.keywords.length - 8) + ' more'"></span>
                            </div>
                            <p class="mt-2 flex items-start gap-1.5 text-[13px] text-slate-600">
                                <x-ui.icon name="arrow-right" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
                                <span class="line-clamp-2 break-words" x-text="rule.reply_text"></span>
                            </p>
                            <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                                <span class="inline-flex items-center gap-1 tabular-nums"><x-ui.icon name="activity" class="size-3.5" /><span x-text="rule.trigger_count.toLocaleString() + (rule.trigger_count === 1 ? ' reply sent' : ' replies sent')"></span></span>
                                <span class="inline-flex items-center gap-1" x-show="rule.last_triggered_at"><x-ui.icon name="clock" class="size-3.5" /><span x-text="'Last ' + timeAgo(rule.last_triggered_at)" x-bind:title="rule.last_triggered_at"></span></span>
                                <span x-show="!rule.last_triggered_at">Never triggered</span>
                                <span x-show="rule.priority !== 0" class="tabular-nums" x-text="'Priority ' + rule.priority"></span>
                            </p>
                        </div>

                        {{-- actions --}}
                        <div class="flex shrink-0 items-start gap-1">
                            <label class="relative inline-flex h-5 w-9 cursor-pointer items-center" x-bind:title="rule.active ? 'On' : 'Off'">
                                <input type="checkbox" role="switch" class="peer absolute inset-0 z-10 h-full w-full cursor-pointer appearance-none rounded-full opacity-0"
                                    x-bind:checked="rule.active" x-on:change="toggle(rule)" x-bind:aria-label="'Rule ' + rule.name + ' active'">
                                <span class="h-5 w-9 rounded-full bg-slate-200 transition-colors peer-checked:bg-brand-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-500"></span>
                                <span class="absolute top-0.5 left-0.5 size-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4"></span>
                            </label>
                            <x-ui.dropdown align="right" width="w-44">
                                <x-slot:trigger>
                                    <x-ui.button variant="ghost" size="xs" icon="dots" aria-label="Rule actions" />
                                </x-slot:trigger>
                                <x-ui.dropdown-item icon="edit" x-on:click="openEdit(rule); close()">Edit</x-ui.dropdown-item>
                                <x-ui.dropdown-item icon="copy" x-on:click="duplicate(rule); close()">Duplicate</x-ui.dropdown-item>
                                <x-ui.dropdown-divider />
                                <x-ui.dropdown-item icon="trash" danger x-on:click="confirmDelete(rule); close()">Delete</x-ui.dropdown-item>
                            </x-ui.dropdown>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    @endforeach

    @if (! $isPage && $otherPages->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 border-t border-line bg-slate-50/70 px-5 py-3 text-xs text-ink-muted">
            <x-ui.icon name="info" class="size-3.5" />
            <span>Page-specific rules:</span>
            @foreach ($otherPages as $p)
                <form method="POST" action="{{ route('admin.page-switch') }}" class="inline" data-no-loading>
                    @csrf
                    <input type="hidden" name="meta_account_id" value="{{ $p['id'] }}">
                    <button type="submit" class="rounded-full bg-white px-2 py-0.5 font-medium text-slate-700 ring-1 ring-line hover:bg-slate-100">{{ $p['name'] }} · {{ $p['rules'] }}</button>
                </form>
            @endforeach
        </div>
    @endif
</section>
