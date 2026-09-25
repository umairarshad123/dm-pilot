{{--
    KPI tile.
    <x-ui.stat label="Messages today" :value="number_format($n)" delta="+12%" icon="message" hint="vs yesterday" />
    Props:
      label   string        value  string|number
      delta   string|null   e.g. "+12%", "-3" (trend auto-detected from the sign)
      trend   up | down | flat | null (overrides auto)
      invert  bool: a rising number is bad (e.g. failures) so "up" is red
      icon    icon name     tone  brand (default) | success | warning | danger | neutral | purple (icon tile colour)
      hint    small text after the delta            href  make the whole tile a link
--}}
@props([
    'label', 'value', 'delta' => null, 'trend' => null, 'invert' => false,
    'icon' => null, 'tone' => 'brand', 'hint' => null, 'href' => null,
])

@php
    $trend ??= $delta === null ? null : (str_starts_with(trim((string) $delta), '-') ? 'down' : (preg_match('/^\+?0(\.0+)?%?$/', trim((string) $delta)) ? 'flat' : 'up'));
    $good = $trend === 'flat' ? null : (($trend === 'up') xor $invert);
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600', 'success' => 'bg-success-50 text-success-600',
        'warning' => 'bg-warning-50 text-warning-600', 'danger' => 'bg-danger-50 text-danger-600',
        'neutral' => 'bg-slate-100 text-slate-600', 'purple' => 'bg-violet-50 text-violet-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'ui-card block p-5 min-w-0',
    'transition-shadow duration-200 hover:shadow-card-hover' => $href,
]) }}>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] font-medium text-ink-muted">{{ $label }}</p>
        @if ($icon)
            <span class="{{ $tones[$tone] ?? $tones['brand'] }} inline-flex size-8 shrink-0 items-center justify-center rounded-lg">
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>
    <p class="mt-1 text-[28px] leading-9 font-semibold tracking-tight text-ink tabular-nums">{{ $value }}</p>
    @if ($delta !== null || $hint)
        <p class="mt-1.5 flex items-center gap-1.5 text-xs">
            @if ($delta !== null)
                <span @class([
                    'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold',
                    'bg-success-50 text-success-700' => $good === true,
                    'bg-danger-50 text-danger-700' => $good === false,
                    'bg-slate-100 text-slate-600' => $good === null,
                ])>
                    @if ($trend === 'up')<x-ui.icon name="trending-up" class="size-3" :stroke="2.5" />@elseif ($trend === 'down')<x-ui.icon name="trending-down" class="size-3" :stroke="2.5" />@endif
                    {{ $delta }}
                </span>
            @endif
            @if ($hint)<span class="text-ink-muted">{{ $hint }}</span>@endif
        </p>
    @endif
    {{ $slot }}
</{{ $tag }}>
