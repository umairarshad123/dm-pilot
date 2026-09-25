{{--
    Channel marker for Messenger (platform "facebook") and Instagram.
    <x-ui.channel-badge platform="facebook" />                                  pill: icon + "Messenger"
    <x-ui.channel-badge :platform="$account->platform" variant="icon" size="sm" />  round brand icon only
    Props:
      platform  'facebook' | 'instagram' | App\Enums\Platform
      variant   pill (default) | icon | plain (coloured glyph + label, no background)
      size      xs | sm | md (default)   (icon variant: 2xs=14px, xs=16px, sm=20px, md=24px)
      label     override the text (default "Messenger" / "Instagram")
--}}
@props(['platform', 'variant' => 'pill', 'size' => 'md', 'label' => null])

@php
    $value = $platform instanceof \BackedEnum ? $platform->value : (string) $platform;
    $isIg = $value === 'instagram';
    $text = $label ?? ($isIg ? 'Instagram' : 'Messenger');
    $iconSizes = ['2xs' => 'size-3.5', 'xs' => 'size-4', 'sm' => 'size-5', 'md' => 'size-6'];
    $glyphSizes = ['2xs' => 'size-2', 'xs' => 'size-2.5', 'sm' => 'size-3', 'md' => 'size-3.5'];
@endphp

@if ($variant === 'icon')
    <span {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full text-white ring-2 ring-white',
        $iconSizes[$size] ?? $iconSizes['md'],
        $isIg ? 'bg-instagram-gradient' : 'bg-messenger',
    ]) }} title="{{ $text }}">
        <x-ui.icon :name="$isIg ? 'instagram' : 'messenger'" :class="$glyphSizes[$size] ?? 'size-3.5'" :stroke="2.5" />
        <span class="sr-only">{{ $text }}</span>
    </span>
@elseif ($variant === 'plain')
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 text-xs font-medium text-slate-600']) }}>
        <x-ui.icon :name="$isIg ? 'instagram' : 'messenger'" :class="$isIg ? 'size-3.5 text-instagram' : 'size-3.5 text-messenger'" />
        {{ $text }}
    </span>
@else
    <span {{ $attributes->class([
        'inline-flex items-center gap-1.5 rounded-full py-0.5 pr-2 pl-0.5 font-medium whitespace-nowrap',
        in_array($size, ['xs', 'sm'], true) ? 'text-2xs' : 'text-xs',
        $isIg ? 'bg-instagram-soft text-[#b3245a]' : 'bg-messenger-soft text-[#0060c0]',
    ]) }}>
        <span class="{{ $isIg ? 'bg-instagram-gradient' : 'bg-messenger' }} inline-flex size-4 items-center justify-center rounded-full text-white">
            <x-ui.icon :name="$isIg ? 'instagram' : 'messenger'" class="size-2.5" :stroke="2.75" />
        </span>
        {{ $text }}
    </span>
@endif
