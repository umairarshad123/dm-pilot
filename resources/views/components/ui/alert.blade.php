{{--
    Inline alert / callout.
    <x-ui.alert tone="warning" title="Outside the 24h window">Meta may reject standard messages.</x-ui.alert>
    Props:
      tone         info (default) | success | warning | danger | neutral | brand
      title        bold first line          icon  override the default tone icon (false = none)
      dismissible  bool: close button (client-side only)
    Slot "actions" renders buttons under the text.
--}}
@props(['tone' => 'info', 'title' => null, 'icon' => null, 'dismissible' => false])

@php
    $tones = [
        'info' => ['bg-sky-50 ring-sky-200/70 text-sky-900', 'text-sky-500', 'info'],
        'brand' => ['bg-brand-50 ring-brand-100 text-brand-900', 'text-brand-600', 'sparkles'],
        'success' => ['bg-success-50 ring-success-100 text-emerald-900', 'text-success-600', 'check-circle'],
        'warning' => ['bg-warning-50 ring-amber-200/70 text-amber-900', 'text-warning-600', 'alert-triangle'],
        'danger' => ['bg-danger-50 ring-danger-100 text-rose-900', 'text-danger-600', 'alert-circle'],
        'neutral' => ['bg-slate-50 ring-slate-200 text-slate-800', 'text-slate-500', 'info'],
    ];
    [$box, $iconColor, $defaultIcon] = $tones[$tone] ?? $tones['info'];
    $icon = $icon === false ? null : ($icon ?? $defaultIcon);
@endphp

<div role="{{ in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="{ shown: true }" x-show="shown" x-transition.opacity @endif
    {{ $attributes->class(['flex gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-inset', $box]) }}>
    @if ($icon)<x-ui.icon :name="$icon" class="mt-0.5 size-4 {{ $iconColor }}" />@endif
    <div class="min-w-0 flex-1">
        @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
        @if ($slot->isNotEmpty())<div @class(['opacity-90', 'mt-0.5' => $title])>{{ $slot }}</div>@endif
        @isset($actions)<div class="mt-3 flex flex-wrap gap-2">{{ $actions }}</div>@endisset
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="shown = false" class="-m-1 h-fit rounded-md p-1 opacity-60 hover:opacity-100" aria-label="Dismiss">
            <x-ui.icon name="x" class="size-4" />
        </button>
    @endif
</div>
