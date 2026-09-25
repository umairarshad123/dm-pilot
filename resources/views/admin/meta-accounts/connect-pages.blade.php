<x-layouts.app title="Choose Pages" width="narrow">
    <x-ui.page-header title="Choose the Pages to connect"
        :description="count($pages).' '.\Illuminate\Support\Str::plural('Page', count($pages)).' found'.(($user['name'] ?? null) ? ' for '.$user['name'] : '').'. Nothing is selected: tick only the ones the bot should answer.'"
        :back="route('admin.meta-accounts.connect')" back-label="Back" />

    @include('admin.meta-accounts._stepper', ['step' => 2])

    <form method="POST" action="{{ route('admin.meta-accounts.connect.store') }}"
        x-data="{
            q: '',
            selected: [],
            ids: @js(array_column($pages, 'id')),
            names: @js(collect($pages)->mapWithKeys(fn ($p) => [$p['id'] => mb_strtolower($p['name'].' '.($p['instagram_business_account']['username'] ?? ''))])),
            match(id) { return this.q === '' || this.names[id].includes(this.q.toLowerCase()) },
            visible() { return this.ids.filter((id) => this.match(id)) },
            selectVisible() { this.selected = [...new Set([...this.selected, ...this.visible()])] },
        }">
        @csrf

        <div class="ui-card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-line bg-slate-50/60 px-4 py-3">
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" x-model.debounce.100ms="q" placeholder="Search Pages" aria-label="Search Pages" class="ui-input h-9 pl-9 text-[13px]">
                </div>
                <div class="ml-auto flex items-center gap-1">
                    <x-ui.button size="xs" variant="ghost" x-on:click="selectVisible()">Select all</x-ui.button>
                    <x-ui.button size="xs" variant="ghost" x-on:click="selected = []" x-bind:disabled="selected.length === 0">Clear</x-ui.button>
                </div>
            </div>

            <ul class="ui-scroll max-h-[60vh] divide-y divide-line overflow-y-auto" role="list">
                @foreach ($pages as $page)
                    @php($ig = $page['instagram_business_account'] ?? null)
                    <li x-show="match(@js($page['id']))">
                        <label for="page_{{ $page['id'] }}"
                            class="flex cursor-pointer items-center gap-4 px-4 py-3.5 transition-colors hover:bg-slate-50 sm:px-5"
                            x-bind:class="{ 'bg-brand-50/60 hover:bg-brand-50': selected.includes(@js($page['id'])) }">
                            <input type="checkbox" name="page_ids[]" value="{{ $page['id'] }}" id="page_{{ $page['id'] }}" x-model="selected"
                                class="size-4 shrink-0 rounded border-line-strong text-brand-600 accent-brand-600">
                            <span class="relative inline-flex shrink-0" x-data="{ broken: {{ empty($page['picture_url']) ? 'true' : 'false' }} }">
                                @if (! empty($page['picture_url']))
                                    <img src="{{ $page['picture_url'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-show="! broken" x-on:error="broken = true" class="size-10 rounded-xl bg-slate-100 object-cover ring-1 ring-black/5">
                                @endif
                                <span x-show="broken" @if (! empty($page['picture_url'])) x-cloak @endif class="inline-flex size-10 items-center justify-center rounded-xl bg-gradient-to-br from-sky-400 to-blue-600 text-xs font-semibold text-white">
                                    {{ mb_strtoupper(mb_substr($page['name'], 0, 2)) }}
                                </span>
                                <x-ui.channel-badge platform="facebook" variant="icon" size="2xs" class="absolute -right-1 -bottom-1" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-ink">{{ $page['name'] }}</span>
                                    @if ($page['connected'])
                                        <x-ui.badge tone="success" size="sm" icon="check">Connected</x-ui.badge>
                                    @endif
                                </span>
                                <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
                                    <span class="font-mono text-[11px] text-slate-400">{{ $page['id'] }}</span>
                                    @if ($ig)
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-ui.channel-badge platform="instagram" variant="icon" size="2xs" class="!ring-0" />
                                            <span class="font-medium text-slate-700">{{ '@'.($ig['username'] ?? $ig['id']) }}</span>
                                            @if ($page['ig_connected'])<span class="text-success-700">· connected</span>@endif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-slate-400"><x-ui.icon name="instagram" class="size-3" /> No Instagram linked</span>
                                    @endif
                                </span>
                            </span>
                            @if ($page['connected'])
                                <span class="hidden text-2xs text-ink-muted sm:block">Refreshes token</span>
                            @endif
                        </label>
                    </li>
                @endforeach
                <li x-show="visible().length === 0" x-cloak>
                    <x-ui.empty-state compact icon="search" title="No Pages match" description="Try another name." />
                </li>
            </ul>

            <div class="border-t border-line px-4 py-4 sm:px-5">
                <x-ui.toggle name="subscribe" :checked="true" label="Subscribe to webhooks" description="Required for the bot to receive new messages (messages, postbacks, echoes)." />
            </div>
        </div>

        @error('page_ids')<p class="ui-error mt-3">{{ $message }}</p>@enderror

        <div class="sticky bottom-4 z-10 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white/90 p-3 pl-5 shadow-pop backdrop-blur">
            <p class="text-[13px] text-ink-muted">
                <span class="font-semibold text-ink tabular-nums" x-text="selected.length">0</span>
                <span x-text="selected.length === 1 ? 'Page selected' : 'Pages selected'">Pages selected</span>
                <span class="hidden sm:inline">· linked Instagram accounts are added too</span>
            </p>
            <div class="flex items-center gap-2">
                <x-ui.button :href="route('admin.meta-accounts.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="check" x-bind:disabled="selected.length === 0">
                    Connect <span x-show="selected.length" x-text="selected.length" class="tabular-nums"></span>
                </x-ui.button>
            </div>
        </div>
    </form>
</x-layouts.app>
