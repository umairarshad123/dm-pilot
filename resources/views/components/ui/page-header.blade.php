{{--
    In-content page heading (h1-sized) with description, optional back link, eyebrow and actions.
    Use when a page needs more than the top-bar title (a description, a back link, an avatar...).
    <x-ui.page-header title="Alice Smith" description="Customer since May" :back="route('admin.conversations.index')" back-label="Live Chat">
        <x-slot:leading><x-ui.avatar name="Alice Smith" size="lg" channel="instagram" /></x-slot:leading>
        <x-ui.button icon="pause">Pause bot</x-ui.button>
    </x-ui.page-header>
    Props: title, description, eyebrow (small caps above), back (url), backLabel ("Back")
    Slots: leading (left of title), meta (row of badges below description), default = actions.
--}}
@props(['title', 'description' => null, 'eyebrow' => null, 'back' => null, 'backLabel' => 'Back'])

<div {{ $attributes->class(['mb-6 min-w-0 sm:mb-8']) }}>
    @if ($back)
        <a href="{{ $back }}" class="group mb-3 inline-flex items-center gap-1 text-[13px] font-medium text-ink-muted hover:text-ink">
            <x-ui.icon name="arrow-left" class="size-3.5 transition-transform group-hover:-translate-x-0.5" />{{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="flex min-w-0 items-center gap-4">
            @isset($leading){{ $leading }}@endisset
            <div class="min-w-0">
                @if ($eyebrow)<p class="mb-1 text-2xs font-semibold tracking-wider text-brand-600 uppercase">{{ $eyebrow }}</p>@endif
                <h1 class="truncate text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
                @if ($description)<p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>@endif
                @isset($meta)<div class="mt-2 flex flex-wrap items-center gap-2">{{ $meta }}</div>@endisset
            </div>
        </div>
        @if ($slot->isNotEmpty())
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>
