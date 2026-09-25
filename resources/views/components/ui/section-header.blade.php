{{--
    Heading for a section inside a page (h2 level).
    <x-ui.section-header title="Connected pages" description="Pages the bot replies on.">
        <x-ui.button size="sm" icon="plus">Add</x-ui.button>
    </x-ui.section-header>
    Props: title, description, count (small pill after the title). Default slot = right-aligned actions.
--}}
@props(['title', 'description' => null, 'count' => null])

<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-x-4 gap-y-2']) }}>
    <div class="min-w-0">
        <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
            {{ $title }}
            @if ($count !== null)<span class="rounded-full bg-slate-100 px-2 py-px text-xs font-semibold text-slate-600 tabular-nums">{{ $count }}</span>@endif
        </h2>
        @if ($description)<p class="mt-0.5 text-[13px] text-ink-muted">{{ $description }}</p>@endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
