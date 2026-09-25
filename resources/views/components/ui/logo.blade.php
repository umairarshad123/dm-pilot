{{--
    Brand mark + wordmark. The product name lives in ONE place: App\Providers\ViewServiceProvider::BRAND (shared to views as $brand).
    <x-ui.logo />  <x-ui.logo :wordmark="false" size="lg" />  <x-ui.logo inverted />
    Props: name (default "DM Pilot"), wordmark (bool), size (sm|md|lg), inverted (white text for dark backgrounds)
--}}
@props(['name' => \App\Providers\ViewServiceProvider::BRAND, 'wordmark' => true, 'size' => 'md', 'inverted' => false])

@php
    $mark = ['sm' => 'size-7 rounded-lg', 'md' => 'size-8 rounded-[10px]', 'lg' => 'size-11 rounded-xl'][$size] ?? 'size-8';
    $glyph = ['sm' => 'size-3.5', 'md' => 'size-4', 'lg' => 'size-5'][$size] ?? 'size-4';
    $text = ['sm' => 'text-sm', 'md' => 'text-[15px]', 'lg' => 'text-xl'][$size] ?? 'text-[15px]';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    <span class="{{ $mark }} relative inline-flex shrink-0 items-center justify-center bg-gradient-to-br from-brand-500 via-brand-600 to-indigo-600 text-white shadow-[0_4px_12px_-2px_rgb(27_86_245/0.45)] ring-1 ring-white/20 ring-inset">
        <x-ui.icon name="send" :class="$glyph.' -translate-x-px translate-y-px'" :stroke="2.25" />
    </span>
    @if ($wordmark)
        <span class="{{ $text }} font-semibold tracking-tight {{ $inverted ? 'text-white' : 'text-ink' }}">{{ $name }}</span>
    @endif
</span>
