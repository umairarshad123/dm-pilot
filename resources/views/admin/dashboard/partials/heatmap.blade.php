{{-- Busiest hours: incoming messages by weekday × hour (sequential brand ramp). $heatmap = InsightsService::hourlyHeatmap(). --}}
@php
    $scale = ['bg-slate-100', 'bg-brand-100', 'bg-brand-200', 'bg-brand-300', 'bg-brand-400', 'bg-brand-500', 'bg-brand-700'];
    $max = max(1, $heatmap['max']);
    $level = fn (int $v) => $v === 0 ? 0 : min(6, (int) ceil($v / $max * 6));
    $fullDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $hourLabel = fn (int $h) => $h === 0 ? '12am' : ($h < 12 ? $h.'am' : ($h === 12 ? '12pm' : ($h - 12).'pm'));

    // Peak slot for the headline
    $peak = null;
    foreach ($heatmap['matrix'] as $d => $row) {
        foreach ($row as $h => $v) {
            if ($v > 0 && ($peak === null || $v > $peak[2])) { $peak = [$d, $h, $v]; }
        }
    }
    $hourTotals = array_map(fn ($h) => array_sum(array_column($heatmap['matrix'], $h)), range(0, 23));
    $timezone = config('app.timezone');
@endphp

<x-ui.card :padded="false" class="h-full">
    <x-slot:header>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-[15px] font-semibold text-ink">Busiest hours</h3>
                <p class="mt-0.5 text-[13px] text-ink-muted">
                    @if ($peak)
                        Peak: <span class="font-semibold text-ink">{{ $fullDays[$peak[0]] }}s around {{ $hourLabel($peak[1]) }}</span> · incoming messages ({{ $timezone }})
                    @else
                        Incoming messages by day and hour ({{ $timezone }})
                    @endif
                </p>
            </div>
        </div>
    </x-slot:header>

    <div class="p-5 pt-4" x-data="{ tip: null }">
        @if ($heatmap['total'] === 0)
            <x-ui.empty-state compact icon="clock" tone="neutral" title="Not enough data yet"
                description="Once customers start messaging, you'll see when they're most active so you can staff live chat at the right times." />
        @else
            <div class="ui-scroll -mx-5 overflow-x-auto px-5 pb-1">
                <div class="min-w-[560px]" x-on:mouseleave="tip = null">
                    {{-- Hour totals (mini bars) --}}
                    @php($hourMax = max(1, max($hourTotals)))
                    <div class="mb-1.5 grid grid-cols-[2.5rem_repeat(24,minmax(0,1fr))] items-end gap-[3px]" aria-hidden="true">
                        <span></span>
                        @foreach ($hourTotals as $h => $v)
                            <div class="flex h-6 items-end"><div class="w-full rounded-t-[2px] bg-slate-200" style="height: {{ max($v > 0 ? 8 : 0, round($v / $hourMax * 100)) }}%"></div></div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-[2.5rem_repeat(24,minmax(0,1fr))] gap-[3px]" role="grid" aria-label="Incoming messages by weekday and hour">
                        @foreach ($heatmap['matrix'] as $d => $row)
                            <div class="flex items-center text-2xs font-medium text-slate-500" role="rowheader">{{ $heatmap['days'][$d] }}</div>
                            @foreach ($row as $h => $v)
                                <div class="{{ $scale[$level($v)] }} aspect-square rounded-[4px] transition hover:ring-2 hover:ring-ink/70 hover:ring-offset-1"
                                    role="gridcell" title="{{ $fullDays[$d] }} {{ $hourLabel($h) }}: {{ $v }} {{ \Illuminate\Support\Str::plural('message', $v) }}"
                                    x-on:mouseenter="tip = @js($fullDays[$d].' · '.$hourLabel($h).'–'.$hourLabel(($h + 1) % 24).' · '.number_format($v).' '.\Illuminate\Support\Str::plural('message', $v))"></div>
                            @endforeach
                        @endforeach
                    </div>
                    <div class="mt-1.5 grid grid-cols-[2.5rem_repeat(24,minmax(0,1fr))] gap-[3px] text-2xs text-slate-400" aria-hidden="true">
                        <span></span>
                        @foreach (range(0, 23) as $h)
                            <span class="text-center">{{ $h % 3 === 0 ? $hourLabel($h) : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-ink-muted">
                <p class="min-h-4 font-medium text-slate-700" x-text="tip ?? 'Hover a cell for details'">Hover a cell for details</p>
                <div class="flex items-center gap-1.5">
                    <span>Less</span>
                    @foreach ($scale as $cls)
                        <span class="{{ $cls }} size-3 rounded-[3px]" aria-hidden="true"></span>
                    @endforeach
                    <span>More</span>
                </div>
            </div>
        @endif
    </div>
</x-ui.card>
