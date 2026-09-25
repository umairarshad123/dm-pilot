{{--
    Item for <x-ui.dropdown>. Renders <a> with href, otherwise <button> (type="button" unless you pass type="submit").
    <x-ui.dropdown-item :href="$url" icon="external-link">Open</x-ui.dropdown-item>
    <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.dropdown-item type="submit" icon="logout">Log out</x-ui.dropdown-item></form>
    Props: href, icon, danger (bool), active (bool, shows a check), type (button|submit), description
--}}
@props(['href' => null, 'icon' => null, 'danger' => false, 'active' => false, 'type' => 'button', 'description' => null])

@php
    $classes = implode(' ', [
        'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium transition-colors focus:outline-none',
        $danger ? 'text-danger-600 hover:bg-danger-50 focus:bg-danger-50' : 'text-slate-700 hover:bg-slate-100 hover:text-ink focus:bg-slate-100',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>
@else
    <button type="{{ $type }}" role="menuitem" tabindex="-1" {{ $attributes->class($classes) }}>
@endif
    @if ($icon)<x-ui.icon :name="$icon" @class(['size-4', 'text-slate-400' => ! $danger]) />@endif
    <span class="min-w-0 flex-1">
        <span class="block truncate">{{ $slot }}</span>
        @if ($description)<span class="block truncate text-xs font-normal text-ink-muted">{{ $description }}</span>@endif
    </span>
    @if ($active)<x-ui.icon name="check" class="size-4 text-brand-600" />@endif
@if ($href)
    </a>
@else
    </button>
@endif
