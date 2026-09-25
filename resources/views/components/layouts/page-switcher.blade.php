{{--
    Sidebar page switcher (internal to the app layout).
    Lists connected MetaAccounts; submitting posts to admin.page-switch which stores the choice in the session
    (App\Support\CurrentPage). Opens on the window event "open-page-switcher" (used by the top-bar context chip).
    Props: currentPage (App\Support\CurrentPage), pages (Collection<MetaAccount>)
--}}
@props(['currentPage', 'pages'])

@php
    $current = $currentPage?->account();
@endphp

<div x-data="{ open: false, q: '' }"
    x-on:open-page-switcher.window="open = true; $nextTick(() => $refs.search?.focus())"
    x-on:keydown.escape.stop="open = false"
    x-on:click.outside="open = false"
    class="relative">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="listbox"
        class="group flex w-full items-center gap-3 rounded-xl border border-line bg-white px-2.5 py-2 text-left shadow-xs transition hover:border-line-strong hover:bg-slate-50">
        @if ($current)
            <x-ui.avatar :name="$current->page_name ?: 'Page'" size="sm" square :channel="$current->platform" />
        @else
            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white">
                <x-ui.icon name="layers" class="size-3.5" />
            </span>
        @endif
        <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] leading-5 font-semibold text-ink">{{ $currentPage?->label() ?? 'All pages' }}</span>
            <span class="block truncate text-2xs leading-4 text-ink-muted">
                @if ($current)
                    {{ $current->platform?->value === 'instagram' ? 'Instagram' : 'Messenger' }} · {{ $current->active ? 'Active' : 'Paused' }}
                @else
                    {{ $pages->count() }} {{ \Illuminate\Support\Str::plural('channel', $pages->count()) }} connected
                @endif
            </span>
        </span>
        <x-ui.icon name="chevrons-up-down" class="size-4 text-slate-400 group-hover:text-slate-600" />
    </button>

    <div x-show="open" x-cloak
        x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-75 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-line bg-white shadow-pop">
        <form method="POST" action="{{ route('admin.page-switch') }}" data-no-loading>
            @csrf
            @if ($pages->count() > 6)
                <div class="border-b border-line p-2">
                    <x-ui.input name="" x-ref="search" x-model="q" icon="search" size="sm" placeholder="Find a page…" x-on:keydown.enter.prevent="" aria-label="Find a page" autocomplete="off" />
                </div>
            @endif
            <div class="ui-scroll max-h-80 overflow-y-auto p-1.5" role="listbox" aria-label="Pages">
                <button type="submit" name="meta_account_id" value="" role="option" aria-selected="{{ $current ? 'false' : 'true' }}"
                    x-show="q === ''"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100">
                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white"><x-ui.icon name="layers" class="size-3.5" /></span>
                    <span class="flex-1">All pages</span>
                    @unless ($current)<x-ui.icon name="check" class="size-4 text-brand-600" />@endunless
                </button>

                @if ($pages->isNotEmpty())
                    <p class="px-2 pt-2.5 pb-1 text-2xs font-semibold tracking-wider text-slate-400 uppercase" x-show="q === ''">Pages & accounts</p>
                @endif

                @foreach ($pages as $page)
                    @php($pageName = $page->page_name ?: 'Page #'.$page->id)
                    <button type="submit" name="meta_account_id" value="{{ $page->id }}" role="option" aria-selected="{{ $current?->id === $page->id ? 'true' : 'false' }}"
                        x-show="q === '' || @js(mb_strtolower($pageName)).includes(q.toLowerCase())"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100">
                        <x-ui.avatar :name="$pageName" size="sm" square :channel="$page->platform" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $pageName }}</span>
                            <span class="block text-2xs font-normal text-ink-muted">{{ $page->platform?->value === 'instagram' ? 'Instagram' : 'Messenger' }}@unless ($page->active) · Paused @endunless</span>
                        </span>
                        @if ($current?->id === $page->id)<x-ui.icon name="check" class="size-4 text-brand-600" />@endif
                    </button>
                @endforeach

                @if ($pages->isEmpty())
                    <p class="px-2 py-3 text-[13px] text-ink-muted">No pages connected yet.</p>
                @endif
            </div>
        </form>
        <div class="border-t border-line p-1.5">
            <a href="{{ route('admin.meta-accounts.connect') }}" class="flex items-center gap-2.5 rounded-lg px-2 py-2 text-[13px] font-medium text-brand-600 hover:bg-brand-50">
                <span class="inline-flex size-7 items-center justify-center rounded-lg border border-dashed border-brand-300"><x-ui.icon name="plus" class="size-3.5" /></span>
                Connect a page
            </a>
        </div>
    </div>
</div>
