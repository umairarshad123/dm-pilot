{{--
    Card surface.
    <x-ui.card title="Webhook" description="Where Meta sends events">
        <x-slot:actions><x-ui.button size="sm">Edit</x-ui.button></x-slot:actions>
        ...body...
        <x-slot:footer>...</x-slot:footer>
    </x-ui.card>
    Props:
      title, description  header text (header renders only when title/description/header/actions given)
      icon                icon name shown in a soft tile left of the title
      padded              bool (default true); false = flush body (tables, lists)
      hover               bool: lift on hover (for clickable cards)
    Slots: header (replaces the whole header), actions, footer, default body.
--}}
@props(['title' => null, 'description' => null, 'icon' => null, 'padded' => true, 'hover' => false])

<section {{ $attributes->class([
    'ui-card flex flex-col min-w-0',
    'transition-shadow duration-200 hover:shadow-card-hover' => $hover,
]) }}>
    @if (isset($header))
        <div class="border-b border-line px-5 py-4">{{ $header }}</div>
    @elseif ($title || $description || isset($actions))
        <header class="flex items-start gap-3 border-b border-line px-5 py-4">
            @if ($icon)
                <span class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <x-ui.icon :name="$icon" class="size-4" />
                </span>
            @endif
            <div class="min-w-0 flex-1">
                @if ($title)<h2 class="text-[15px] leading-6 font-semibold text-ink">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-[13px] text-ink-muted">{{ $description }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['flex-1 min-w-0', 'p-5' => $padded])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex flex-wrap items-center justify-end gap-2 rounded-b-(--radius-card) border-t border-line bg-slate-50/60 px-5 py-3">
            {{ $footer }}
        </footer>
    @endisset
</section>
