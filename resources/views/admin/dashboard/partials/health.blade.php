{{-- System health: compact status pill that expands into the checks. $health, $aiProviders from the controller. --}}
@php
    $failing = collect($health)->reject(fn ($c) => $c['ok']);
    $ok = $failing->isEmpty();
@endphp

<section id="system-health" class="ui-card min-w-0 self-start" x-data="{ open: @js(! $ok) }" x-on:toggle-health.window="open = ! open; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))">
    <button type="button" class="flex w-full items-center gap-3 rounded-(--radius-card) px-5 py-4 text-left" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="health-checks">
        <span @class([
            'inline-flex size-9 shrink-0 items-center justify-center rounded-xl',
            'bg-success-50 text-success-600' => $ok,
            'bg-warning-50 text-warning-600' => ! $ok,
        ])>
            <x-ui.icon :name="$ok ? 'shield' : 'alert-triangle'" class="size-[18px]" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[15px] font-semibold text-ink">System health</span>
            <span class="mt-0.5 block text-[13px] text-ink-muted">
                @if ($ok)
                    All {{ count($health) }} checks passing
                @else
                    {{ $failing->count() }} of {{ count($health) }} checks need attention
                @endif
            </span>
        </span>
        <span class="hidden sm:inline-flex">
            @if ($ok)
                <x-ui.badge tone="success" dot>All systems operational</x-ui.badge>
            @else
                <x-ui.badge tone="warning" dot>{{ $failing->count() }} {{ \Illuminate\Support\Str::plural('issue', $failing->count()) }}</x-ui.badge>
            @endif
        </span>
        <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="{ 'rotate-180': open }" />
    </button>

    <div id="health-checks" x-show="open" x-collapse @if ($ok) x-cloak @endif>
        <ul class="divide-y divide-line border-t border-line">
            @foreach (collect($health)->sortBy(fn ($c) => $c['ok'] ? 1 : 0, SORT_REGULAR)->values() as $check)
                <li class="flex items-start gap-3 px-5 py-3" data-check="{{ $check['key'] }}">
                    @if ($check['ok'] && ! ($check['warn'] ?? false))
                        <x-ui.icon name="check-circle" class="mt-0.5 size-[18px] shrink-0 text-success-500" />
                    @elseif ($check['ok'])
                        <x-ui.icon name="alert-circle" class="mt-0.5 size-[18px] shrink-0 text-warning-500" />
                    @else
                        <x-ui.icon name="x-circle" class="mt-0.5 size-[18px] shrink-0 text-danger-500" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium text-ink {{ str_starts_with($check['label'], 'META_') ? 'font-mono text-[12.5px]' : '' }}">{{ $check['label'] }}</p>
                        <p class="mt-0.5 text-xs text-ink-muted">{{ $check['hint'] }}</p>
                        @if ($check['key'] === 'ai' && count($aiProviders) > 1)
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach ($aiProviders as $p)
                                    <x-ui.badge size="sm" :tone="$p['configured'] ? 'success' : 'neutral'" :icon="$p['configured'] ? 'check' : null">
                                        {{ $p['label'] }}@if ($p['default']) · default @endif
                                    </x-ui.badge>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <x-ui.badge size="sm" :tone="$check['ok'] ? (($check['warn'] ?? false) ? 'warning' : 'success') : 'danger'">{{ $check['ok'] ? $check['detail'] : ($check['detail'] === 'Missing' ? 'Missing' : $check['detail']) }}</x-ui.badge>
                        @if (! empty($check['href']) && (! $check['ok'] || ($check['warn'] ?? false)))
                            <a href="{{ $check['href'] }}" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-ink" aria-label="Open {{ $check['label'] }}"><x-ui.icon name="arrow-right" class="size-3.5" /></a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
