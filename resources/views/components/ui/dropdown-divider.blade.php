{{-- Separator line / optional group label inside <x-ui.dropdown>. <x-ui.dropdown-divider label="Pages" /> --}}
@props(['label' => null])

@if ($label)
    <p {{ $attributes->class(['px-2.5 pt-2 pb-1 text-2xs font-semibold tracking-wider text-slate-400 uppercase']) }}>{{ $label }}</p>
@else
    <div {{ $attributes->class(['-mx-1.5 my-1.5 h-px bg-line']) }} role="separator"></div>
@endif
