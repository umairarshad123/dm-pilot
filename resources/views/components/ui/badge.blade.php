{{--
    Status pill. <x-ui.badge tone="success" dot>Active</x-ui.badge>
    Props:
      tone  neutral (default) | brand | info | success | warning | danger | purple | dark
      size  sm | md (default)
      dot   bool: leading status dot       icon  leading icon name
--}}
@props(['tone' => 'neutral', 'size' => 'md', 'dot' => false, 'icon' => null])

@php
    $tones = [
        'neutral' => ['bg-slate-100 text-slate-700 ring-slate-200/80', 'bg-slate-400'],
        'brand' => ['bg-brand-50 text-brand-700 ring-brand-100', 'bg-brand-500'],
        'info' => ['bg-sky-50 text-sky-700 ring-sky-100', 'bg-sky-500'],
        'success' => ['bg-success-50 text-success-700 ring-success-100', 'bg-success-500'],
        'warning' => ['bg-warning-50 text-warning-700 ring-warning-100', 'bg-warning-500'],
        'danger' => ['bg-danger-50 text-danger-700 ring-danger-100', 'bg-danger-500'],
        'purple' => ['bg-violet-50 text-violet-700 ring-violet-100', 'bg-violet-500'],
        'dark' => ['bg-slate-800 text-white ring-slate-800', 'bg-white'],
    ];
    [$toneClass, $dotClass] = $tones[$tone] ?? $tones['neutral'];
    $sizeClass = $size === 'sm' ? 'px-1.5 py-px text-2xs gap-1' : 'px-2 py-0.5 text-xs gap-1.5';
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full font-medium whitespace-nowrap ring-1 ring-inset', $toneClass, $sizeClass]) }}>
    @if ($dot)<span class="size-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>@endif
    @if ($icon)<x-ui.icon :name="$icon" class="size-3" />@endif
    {{ $slot }}
</span>
