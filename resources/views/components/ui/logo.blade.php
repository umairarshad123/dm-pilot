{{--
    Brand mark + wordmark. The product name lives in ONE place: App\Providers\ViewServiceProvider::BRAND (shared to views as $brand).
    <x-ui.logo />  <x-ui.logo :wordmark="false" size="lg" />  <x-ui.logo inverted />
    Props: name (default "Apex Chat Bot"), wordmark (bool), size (sm|md|lg), inverted (white text for dark backgrounds)
--}}
@props(['name' => \App\Providers\ViewServiceProvider::BRAND, 'wordmark' => true, 'size' => 'md', 'inverted' => false])

@php
    $mark = ['sm' => 'size-7 rounded-lg', 'md' => 'size-8 rounded-[10px]', 'lg' => 'size-11 rounded-xl'][$size] ?? 'size-8';
    $glyph = ['sm' => 'size-3.5', 'md' => 'size-4', 'lg' => 'size-5'][$size] ?? 'size-4';
    $text = ['sm' => 'text-sm', 'md' => 'text-[15px]', 'lg' => 'text-xl'][$size] ?? 'text-[15px]';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2.5']) }}>
    <img src="{{ asset('images/logo.png') }}" alt="{{ $wordmark ? '' : $name }}" width="44" height="44"
         class="{{ $mark }} shrink-0 bg-white object-contain shadow-[0_4px_12px_-4px_rgb(15_23_42/0.25)] ring-1 ring-black/5">

    @if ($wordmark)
        <span class="{{ $text }} font-semibold tracking-tight {{ $inverted ? 'text-white' : 'text-ink' }}">{{ $name }}</span>
    @endif
</span>
