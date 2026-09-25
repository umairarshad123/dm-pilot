{{--
    Spinner. <x-ui.spinner size="xs|sm|md|lg" class="text-brand-600" />  (inherits currentColor)
--}}
@props(['size' => 'md'])

@php
    $sizes = ['xs' => 'size-3', 'sm' => 'size-4', 'md' => 'size-5', 'lg' => 'size-8'];
@endphp

<svg {{ $attributes->class([$sizes[$size] ?? $sizes['md'], 'animate-spin shrink-0']) }} viewBox="0 0 24 24" fill="none" role="status" aria-label="Loading">
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-20" />
    <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
</svg>
