{{-- Scope banner: what is being edited (global defaults vs one page's overrides) + scope switcher. --}}
<div @class([
    'flex flex-col gap-4 rounded-card border p-4 shadow-card sm:flex-row sm:items-center sm:p-5',
    'border-line bg-surface' => $isPage,
    'border-brand-100 bg-gradient-to-br from-brand-50 via-white to-indigo-50/60' => ! $isPage,
])>
    <div class="flex min-w-0 flex-1 items-start gap-3.5">
        @if ($isPage)
            <x-ui.avatar :name="$pageName" :channel="$account->platform" size="lg" />
        @else
            <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-xs">
                <x-ui.icon name="layers" class="size-6" />
            </span>
        @endif
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-base font-semibold text-ink">{{ $isPage ? $pageName : 'Global defaults' }}</h2>
                @if ($isPage)
                    <x-ui.channel-badge :platform="$account->platform" size="sm" />
                    @if ($currentOverrides > 0)
                        <x-ui.badge tone="brand" size="sm">{{ $currentOverrides }} {{ \Illuminate\Support\Str::plural('override', $currentOverrides) }}</x-ui.badge>
                    @else
                        <x-ui.badge tone="neutral" size="sm" icon="link">Inherits everything</x-ui.badge>
                    @endif
                @else
                    <x-ui.badge tone="dark" size="sm">All pages</x-ui.badge>
                @endif
            </div>
            <p class="mt-1 text-[13px] text-ink-muted">
                @if ($isPage)
                    Page overrides. Blank fields inherit the <button type="button" class="ui-link" onclick="document.getElementById('scope-global').requestSubmit()">global defaults</button>; anything you fill in here wins for this page only.
                @else
                    Applies to every page unless overridden. Select a page to give it its own prompt, knowledge, model or timing.
                @endif
            </p>
            @unless ($configEnabled)
                <p class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-danger-50 px-2.5 py-1 text-xs font-medium text-danger-700">
                    <x-ui.icon name="alert-triangle" class="size-3.5" /> BOT_ENABLED=false in the server environment: the bot is off everywhere regardless of these settings.
                </p>
            @endunless
        </div>
    </div>

    <div class="flex shrink-0 flex-wrap items-center gap-2">
        @if ($isPage && $account->botSetting)
            <x-ui.button size="sm" variant="ghost" icon="refresh" x-data x-on:click="$dispatch('open-modal', 'reset-overrides')">Reset to global</x-ui.button>
        @endif

        <form id="scope-global" method="POST" action="{{ route('admin.page-switch') }}" class="hidden" data-no-loading>
            @csrf
            <input type="hidden" name="meta_account_id" value="">
        </form>

        <x-ui.dropdown align="right" width="w-80">
            <x-slot:trigger>
                <x-ui.button size="sm" icon-right="chevrons-up-down">
                    <span class="text-ink-muted">Editing:</span> {{ $isPage ? \Illuminate\Support\Str::limit($pageName, 22) : 'Global defaults' }}
                </x-ui.button>
            </x-slot:trigger>
            <x-slot:header>
                <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Edit settings for</p>
            </x-slot:header>
            <form method="POST" action="{{ route('admin.page-switch') }}" data-no-loading>
                @csrf
                <input type="hidden" name="meta_account_id" value="">
                <x-ui.dropdown-item type="submit" icon="layers" :active="! $isPage" description="Applies to every page unless overridden">Global defaults</x-ui.dropdown-item>
            </form>
            @if ($pages->isNotEmpty())
                <x-ui.dropdown-divider label="Pages" />
                @foreach ($pages as $p)
                    @php($n = $overrideCount($p->botSetting))
                    <form method="POST" action="{{ route('admin.page-switch') }}" data-no-loading>
                        @csrf
                        <input type="hidden" name="meta_account_id" value="{{ $p->id }}">
                        <x-ui.dropdown-item type="submit" :icon="$p->platform->value === 'instagram' ? 'instagram' : 'messenger'" :active="$isPage && $account->id === $p->id"
                            :description="($p->botSetting && ! $p->botSetting->bot_enabled ? 'Bot off · ' : '').($n > 0 ? $n.' '.\Illuminate\Support\Str::plural('override', $n) : 'Inherits global')">
                            {{ $p->page_name ?: 'Page #'.$p->id }}
                        </x-ui.dropdown-item>
                    </form>
                @endforeach
            @else
                <x-ui.dropdown-divider />
                <x-ui.dropdown-item :href="route('admin.meta-accounts.connect')" icon="plus">Connect a page</x-ui.dropdown-item>
            @endif
        </x-ui.dropdown>
    </div>
</div>
