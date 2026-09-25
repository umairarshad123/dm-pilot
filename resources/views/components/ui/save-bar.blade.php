{{--
    Sticky save bar for settings forms. Place as the LAST child inside the <form>.
    Shows "Unsaved changes" once any field changes; Discard resets the form.
    <form method="POST" action="..." data-warn-unsaved>   (data-warn-unsaved = browser prompt when leaving with changes)
        @csrf @method('PUT')
        ...fields...
        <x-ui.save-bar />
    </form>
    Props: label ("Save changes"), always (bool: keep visible even when clean; default true, buttons disable when clean)
    Default slot = extra buttons left of Save.
--}}
@props(['label' => 'Save changes', 'always' => true])

<div x-data="dirtyForm" @unless ($always) x-show="dirty" x-transition x-cloak @endunless
    {{ $attributes->class(['sticky bottom-4 z-20 mt-6 rounded-2xl border border-line bg-white/90 py-2.5 pr-2.5 pl-4 shadow-pop backdrop-blur']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="flex items-center gap-2 text-[13px] text-ink-muted">
            <span class="size-2 rounded-full transition-colors" x-bind:class="dirty ? 'bg-warning-500' : 'bg-success-500'"></span>
            <span x-text="dirty ? 'Unsaved changes' : 'All changes saved'">All changes saved</span>
        </p>
        <div class="flex items-center gap-2">
            {{ $slot }}
            <x-ui.button variant="ghost" x-show="dirty" x-cloak x-on:click="reset()">Discard</x-ui.button>
            <x-ui.button type="submit" variant="primary" icon="check">{{ $label }}</x-ui.button>
        </div>
    </div>
</div>
