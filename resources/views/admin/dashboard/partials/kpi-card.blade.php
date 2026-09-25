{{--
    KPI tile with delta vs the previous period + sparkline. $kpi = one row of DashboardController::kpis().
    (Candidate for a shared x-ui.stat "spark" prop.)
--}}
@php
    $tones = [
        'brand' => ['bg-brand-50 text-brand-600', 'text-brand-500'],
        'purple' => ['bg-violet-50 text-violet-600', 'text-violet-500'],
        'success' => ['bg-success-50 text-success-600', 'text-success-500'],
        'warning' => ['bg-warning-50 text-warning-600', 'text-warning-500'],
        'danger' => ['bg-danger-50 text-danger-600', 'text-danger-500'],
        'neutral' => ['bg-slate-100 text-slate-600', 'text-slate-400'],
    ];
    [$tileClass, $sparkClass] = $tones[$kpi['tone']] ?? $tones['brand'];
    $good = match ($kpi['trend']) { 'up' => ! $kpi['invert'], 'down' => $kpi['invert'], default => null };
    $isAlert = $kpi['key'] === 'ai_failures' && (int) $kpi['value'] > 0;
    $tag = $kpi['href'] ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($kpi['href']) href="{{ $kpi['href'] }}" @endif
    @class([
        'ui-card group relative flex min-w-0 flex-col overflow-hidden p-4 sm:p-5',
        'transition-shadow duration-200 hover:shadow-card-hover' => $kpi['href'],
        'border-danger-100 bg-gradient-to-b from-danger-50/70 to-white' => $isAlert,
    ])
    data-kpi="{{ $kpi['key'] }}">
    <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] font-medium text-ink-muted">{{ $kpi['label'] }}</p>
        <span class="{{ $tileClass }} hidden size-8 sm:inline-flex shrink-0 items-center justify-center rounded-lg">
            <x-ui.icon :name="$kpi['icon']" class="size-4" />
        </span>
    </div>

    <div class="mt-1 flex items-end justify-between gap-3">
        <p @class(['text-2xl sm:text-[28px] sm:leading-9 font-semibold tracking-tight', 'text-danger-700' => $isAlert, 'text-ink' => ! $isAlert])>{{ $kpi['display'] }}</p>
        @if (count($kpi['spark']) > 1 && max($kpi['spark']) > 0)
            @include('admin.dashboard.partials.sparkline', ['points' => $kpi['spark'], 'class' => $sparkClass.' hidden min-[420px]:block'])
        @elseif ($kpi['key'] === 'resolution' && $kpi['value'] !== null)
            @php($pct = max(0, min(100, (float) $kpi['value'])))
            <svg viewBox="0 0 36 36" class="mb-1 size-8 shrink-0 -rotate-90 sm:size-10" aria-hidden="true">
                <circle cx="18" cy="18" r="15" fill="none" stroke-width="4" class="stroke-success-100" />
                <circle cx="18" cy="18" r="15" fill="none" stroke-width="4" stroke-linecap="round" class="stroke-success-500"
                    pathLength="100" stroke-dasharray="{{ $pct }} 100" />
            </svg>
        @endif
    </div>

    <div class="mt-1.5 flex min-h-5 flex-wrap items-center gap-1.5 text-xs">
        @if ($kpi['delta'] !== null)
            <span @class([
                'inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 font-semibold',
                'bg-success-50 text-success-700' => $good === true,
                'bg-danger-50 text-danger-700' => $good === false,
                'bg-slate-100 text-slate-600' => $good === null,
            ]) title="Compared with the previous {{ $days }} days">
                @if ($kpi['trend'] === 'up')<x-ui.icon name="trending-up" class="size-3" :stroke="2.5" />@elseif ($kpi['trend'] === 'down')<x-ui.icon name="trending-down" class="size-3" :stroke="2.5" />@endif
                {{ $kpi['delta'] }}
            </span>
            <span class="hidden text-slate-400 sm:inline">vs prev.</span>
        @endif
        @if ($kpi['hint'])
            <span @class(['text-ink-muted', 'font-medium text-danger-700 group-hover:underline' => $isAlert])>{{ $kpi['delta'] !== null ? '· ' : '' }}{{ $kpi['hint'] }}</span>
        @endif
    </div>

    @if ($kpi['sub'])
        @php($subTotal = max(1, array_sum(array_column($kpi['sub'], 'value'))))
        <div class="mt-3 border-t border-line pt-3">
            <div class="flex h-1.5 gap-0.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                @foreach ($kpi['sub'] as $i => $part)
                    @if ($part['value'] > 0)
                        <span @class(['h-full', 'bg-violet-500' => $i === 0, 'bg-sky-400' => $i === 1, 'bg-amber-400' => $i === 2]) style="width: {{ round($part['value'] / $subTotal * 100, 2) }}%"></span>
                    @endif
                @endforeach
            </div>
            <dl class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-2xs text-ink-muted">
                @foreach ($kpi['sub'] as $i => $part)
                    @continue($i === 2 && $part['value'] === 0)
                    <div class="flex items-center gap-1">
                        <span @class(['size-1.5 rounded-full', 'bg-violet-500' => $i === 0, 'bg-sky-400' => $i === 1, 'bg-amber-400' => $i === 2]) aria-hidden="true"></span>
                        <dt>{{ $part['label'] }}</dt>
                        <dd class="font-semibold text-slate-700 tabular-nums">{{ number_format($part['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</{{ $tag }}>
