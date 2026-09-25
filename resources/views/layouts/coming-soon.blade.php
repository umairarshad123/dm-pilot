{{--
    Placeholder page for areas that are not built yet (used by Route::view in routes/admin/*.php).
    Data: title, icon, headline, description, links (optional list of [label, description, icon, route]).
--}}
<x-layouts.app :title="$title ?? 'Coming soon'">
    <div class="ui-card overflow-hidden">
        <div class="relative">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,var(--color-brand-50),transparent_60%)]" aria-hidden="true"></div>
            <x-ui.empty-state class="relative" :icon="$icon ?? 'sparkles'" :title="$headline ?? ($title ?? 'Coming soon')" :description="$description ?? null">
                <x-ui.badge tone="brand" dot>Coming soon</x-ui.badge>
            </x-ui.empty-state>
        </div>

        @if (! empty($links))
            <div @class(['grid gap-px border-t border-line bg-line', 'sm:grid-cols-2' => count($links) === 2, 'sm:grid-cols-3' => count($links) >= 3])>
                @foreach ($links as $link)
                    @continue(! \Illuminate\Support\Facades\Route::has($link['route']))
                    <a href="{{ route($link['route']) }}" class="group flex items-start gap-3 bg-white p-5 transition-colors hover:bg-slate-50">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition-colors group-hover:bg-brand-50 group-hover:text-brand-600">
                            <x-ui.icon :name="$link['icon'] ?? 'arrow-right'" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1 text-sm font-semibold text-ink">
                                {{ $link['label'] }}
                                <x-ui.icon name="arrow-right" class="size-3.5 text-slate-400 transition-transform group-hover:translate-x-0.5" />
                            </span>
                            <span class="mt-0.5 block text-[13px] text-ink-muted">{{ $link['description'] ?? '' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
