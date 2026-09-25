{{--
    Button / link button.
    <x-ui.button>Cancel</x-ui.button>                                  (type="button")
    <x-ui.button type="submit" variant="primary" icon="check">Save</x-ui.button>  (spinner while the form submits)
    <x-ui.button :href="route('admin.meta-accounts.connect')" icon="plus">Connect</x-ui.button>
    <x-ui.button variant="ghost" size="sm" icon="trash" aria-label="Delete" />      (icon-only when the slot is empty)
    Props:
      variant   primary | secondary (default) | soft | ghost | danger | danger-soft | link
      size      xs | sm | md (default) | lg
      href      render an <a> instead of a <button>
      type      button (default) | submit | reset
      icon      leading icon name (see ui.icon)      iconRight  trailing icon name
      loading   bool: force the loading state (spinner + aria-busy)
      disabled  bool
      block     bool: full width
--}}
@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconRight' => null,
    'loading' => false,
    'disabled' => false,
    'block' => false,
])

@php
    $iconOnly = $slot->isEmpty();

    $variants = [
        'primary' => 'bg-brand-600 text-white shadow-xs ring-1 ring-inset ring-brand-700/20 hover:bg-brand-700 active:bg-brand-800',
        'secondary' => 'bg-white text-slate-700 shadow-xs ring-1 ring-inset ring-line-strong hover:bg-slate-50 hover:text-ink active:bg-slate-100',
        'soft' => 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-ink active:bg-slate-200/70',
        'danger' => 'bg-danger-600 text-white shadow-xs hover:bg-danger-700 active:bg-danger-700 focus-visible:outline-danger-500',
        'danger-soft' => 'bg-white text-danger-600 shadow-xs ring-1 ring-inset ring-danger-100 hover:bg-danger-50 active:bg-danger-100',
        'link' => 'text-brand-600 hover:text-brand-700 hover:underline underline-offset-2',
    ];

    $sizes = $iconOnly
        ? ['xs' => 'size-7 rounded-md', 'sm' => 'size-8 rounded-lg', 'md' => 'size-9 rounded-lg', 'lg' => 'size-11 rounded-xl']
        : ['xs' => 'h-7 px-2.5 text-xs gap-1 rounded-md', 'sm' => 'h-8 px-3 text-[13px] gap-1.5 rounded-lg', 'md' => 'h-9 px-3.5 text-sm gap-2 rounded-lg', 'lg' => 'h-11 px-5 text-[15px] gap-2 rounded-xl'];

    if ($variant === 'link') {
        $sizes = array_map(fn () => 'gap-1.5 text-sm', $sizes);
    }

    $iconSizes = ['xs' => 'size-3.5', 'sm' => 'size-4', 'md' => 'size-4', 'lg' => 'size-5'];

    $classes = implode(' ', [
        'ui-btn relative inline-flex shrink-0 items-center justify-center font-medium whitespace-nowrap select-none',
        'transition-[background-color,color,box-shadow,opacity] duration-150',
        'disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50',
        $variants[$variant] ?? $variants['secondary'],
        $sizes[$size] ?? $sizes['md'],
        $block ? 'w-full' : '',
    ]);

    $iconClass = $iconSizes[$size] ?? 'size-4';
@endphp

@if ($href)
    <a href="{{ $disabled ? '#' : $href }}" {{ $attributes->class($classes) }} @if ($disabled) aria-disabled="true" tabindex="-1" @endif>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        @unless ($iconOnly)<span>{{ $slot }}</span>@endunless
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconClass" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @disabled($disabled) @if ($disabled) data-disabled @endif @if ($loading) aria-busy="true" @endif>
        @if ($icon)<x-ui.icon :name="$icon" :class="$iconClass" />@endif
        @unless ($iconOnly)<span>{{ $slot }}</span>@endunless
        @if ($iconRight)<x-ui.icon :name="$iconRight" :class="$iconClass" />@endif
        <span class="ui-btn-spinner absolute inset-0 items-center justify-center"><x-ui.spinner size="sm" /></span>
    </button>
@endif
