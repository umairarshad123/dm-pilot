{{--
    CSS-only tooltip on hover / keyboard focus.
    <x-ui.tooltip text="Human agents see this"> <x-ui.icon name="info" /> </x-ui.tooltip>
    Props: text, position top (default) | bottom | left | right
--}}
@props(['text', 'position' => 'top'])

@php
    $pos = [
        'top' => 'bottom-full left-1/2 mb-2 -translate-x-1/2',
        'bottom' => 'top-full left-1/2 mt-2 -translate-x-1/2',
        'left' => 'right-full top-1/2 mr-2 -translate-y-1/2',
        'right' => 'left-full top-1/2 ml-2 -translate-y-1/2',
    ][$position] ?? 'bottom-full left-1/2 mb-2 -translate-x-1/2';
@endphp

<span {{ $attributes->class(['group/tip relative inline-flex']) }}>
    {{ $slot }}
    <span role="tooltip" class="{{ $pos }} pointer-events-none absolute z-50 w-max max-w-64 rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity delay-75 duration-150 group-hover/tip:opacity-100 group-focus-within/tip:opacity-100">{{ $text }}</span>
</span>
