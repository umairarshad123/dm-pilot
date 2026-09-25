{{--
    Daily messages: stacked columns (incoming, bot, human, failed) with hover tooltip, legend with totals and a table view.
    $daily = InsightsService::dailySeries(). Palette validated for CVD (dataviz validator): teal / brand / amber / red.
--}}
@php
    $series = [
        'incoming' => ['label' => 'Incoming', 'swatch' => 'bg-teal-600'],
        'bot' => ['label' => 'Bot replies', 'swatch' => 'bg-brand-500'],
        'human' => ['label' => 'Human replies', 'swatch' => 'bg-[#eda100]'],
        'failed' => ['label' => 'Failed', 'swatch' => 'bg-[#e34948]'],
    ];
    $totals = [];
    foreach ($series as $key => $_) {
        $totals[$key] = array_sum(array_column($daily, $key));
    }
    $dayTotals = array_map(fn ($d) => $d['incoming'] + $d['bot'] + $d['human'] + $d['failed'], $daily);
    $peak = max([0, ...$dayTotals]);

    // Nice axis ceiling: 1, 2, 2.5, 5 × 10^n
    $ceil = 4;
    if ($peak > 0) {
        $mag = 10 ** floor(log10($peak));
        foreach ([1, 2, 2.5, 5, 10] as $step) {
            if ($step * $mag >= $peak) { $ceil = $step * $mag; break; }
        }
        $ceil = max(4, $ceil);
    }
    $ticks = [$ceil, $ceil * 0.75, $ceil * 0.5, $ceil * 0.25, 0];

    $n = count($daily);
    $labelEvery = max(1, (int) ceil($n / 7));
    $today = now()->toDateString();
    $gap = $n > 45 ? 'gap-px' : ($n > 20 ? 'gap-[3px]' : 'gap-1.5 sm:gap-2');

    $tooltipDays = array_map(fn ($d, $t) => [
        ...$d,
        'total' => $t,
        'label' => \Illuminate\Support\Carbon::parse($d['date'])->format('D, M j'),
    ], $daily, $dayTotals);
@endphp

<x-ui.card :padded="false" class="h-full">
    <x-slot:header>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-semibold text-ink">Messages</h3>
                <p class="mt-0.5 text-[13px] text-ink-muted"><span class="font-semibold text-ink tabular-nums">{{ number_format($totalMessages) }}</span> messages in the last {{ $days }} days</p>
            </div>
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12.5px]" aria-label="Legend">
                @foreach ($series as $key => $s)
                    <li class="flex items-center gap-1.5 text-ink-muted">
                        <span class="{{ $s['swatch'] }} size-2.5 rounded-[3px]" aria-hidden="true"></span>
                        {{ $s['label'] }} <span class="font-semibold text-ink tabular-nums">{{ number_format($totals[$key]) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </x-slot:header>

    <div class="p-5 pt-4" x-data="{ i: null, table: false, days: @js($tooltipDays) }">
        @if (! $hasActivity)
            <div class="relative">
                <div class="flex h-56 items-end gap-2 opacity-60" aria-hidden="true">
                    @foreach ([28, 42, 35, 55, 48, 66, 52, 74, 61, 80, 70, 88] as $h)
                        <div class="flex-1 rounded-t-[4px] bg-gradient-to-t from-slate-100 to-slate-50" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="max-w-sm rounded-xl bg-white/90 px-5 py-4 text-center shadow-card ring-1 ring-line backdrop-blur">
                        <p class="text-sm font-semibold text-ink">No messages in this period</p>
                        <p class="mt-1 text-[13px] text-ink-muted">When customers message your {{ $hasPages ? 'page' : 'connected pages' }}, daily volume shows up here.</p>
                        <div class="mt-3 flex justify-center gap-2">
                            @if ($hasPages)
                                <x-ui.button size="sm" variant="soft" icon="bot" :href="$links['bot']">Test your bot</x-ui.button>
                            @else
                                <x-ui.button size="sm" variant="primary" icon="plus" :href="$links['connect']">Connect a page</x-ui.button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div x-show="! table">
                <div class="flex gap-3">
                    {{-- Y axis --}}
                    <div class="flex h-56 w-8 shrink-0 flex-col justify-between text-right text-2xs text-slate-400 tabular-nums" aria-hidden="true">
                        @foreach ($ticks as $t)
                            <span class="-translate-y-1/2 leading-none first:translate-y-0 last:translate-y-0">{{ \App\Http\Controllers\Admin\DashboardController::compact($t) }}</span>
                        @endforeach
                    </div>

                    <div class="relative min-w-0 flex-1">
                        {{-- Gridlines --}}
                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                            @foreach ($ticks as $t)
                                <div @class(['h-px', 'bg-line' => $t == 0, 'bg-slate-100' => $t != 0])></div>
                            @endforeach
                        </div>

                        {{-- Columns --}}
                        <div class="relative flex h-56 items-end {{ $gap }}" role="img"
                            aria-label="Daily messages over the last {{ $days }} days: {{ number_format($totalMessages) }} total" x-on:mouseleave="i = null">
                            @foreach ($daily as $idx => $d)
                                @php($t = $dayTotals[$idx])
                                <div class="group relative flex h-full min-w-0 flex-1 cursor-default items-end justify-center"
                                    x-on:mouseenter="i = {{ $idx }}" x-on:click="i = {{ $idx }}">
                                    <div class="pointer-events-none absolute inset-x-0 inset-y-0 rounded-md bg-slate-900/[0.04] opacity-0 transition-opacity group-hover:opacity-100"></div>
                                    @if ($t > 0)
                                        <div class="relative flex w-full max-w-6 flex-col-reverse gap-[2px] overflow-hidden rounded-t-[4px]" style="height: {{ round($t / $ceil * 100, 2) }}%">
                                            @foreach (['incoming', 'bot', 'human', 'failed'] as $key)
                                                @if ($d[$key] > 0)
                                                    <div class="{{ $series[$key]['swatch'] }} w-full shrink-0" style="flex: {{ $d[$key] }} 1 0%; min-height: 2px"></div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Tooltip --}}
                        <template x-if="i !== null && days[i]">
                            <div class="pointer-events-none absolute top-2 z-10 w-48 rounded-xl bg-slate-900/95 p-3 text-xs text-white shadow-pop"
                                x-bind:style="(i / days.length) > 0.6 ? `right: ${100 - (i / days.length) * 100}%; margin-right: 8px` : `left: ${((i + 1) / days.length) * 100}%; margin-left: 8px`">
                                <p class="font-semibold" x-text="days[i].label"></p>
                                <dl class="mt-2 space-y-1">
                                    <div class="flex items-center gap-2"><span class="size-2 rounded-[2px] bg-teal-500"></span><dt class="flex-1 text-slate-300">Incoming</dt><dd class="font-semibold tabular-nums" x-text="days[i].incoming.toLocaleString()"></dd></div>
                                    <div class="flex items-center gap-2"><span class="size-2 rounded-[2px] bg-brand-400"></span><dt class="flex-1 text-slate-300">Bot replies</dt><dd class="font-semibold tabular-nums" x-text="days[i].bot.toLocaleString()"></dd></div>
                                    <div class="flex items-center gap-2"><span class="size-2 rounded-[2px] bg-[#eda100]"></span><dt class="flex-1 text-slate-300">Human replies</dt><dd class="font-semibold tabular-nums" x-text="days[i].human.toLocaleString()"></dd></div>
                                    <div class="flex items-center gap-2"><span class="size-2 rounded-[2px] bg-[#e34948]"></span><dt class="flex-1 text-slate-300">Failed</dt><dd class="font-semibold tabular-nums" x-text="days[i].failed.toLocaleString()"></dd></div>
                                </dl>
                                <p class="mt-2 flex justify-between border-t border-white/10 pt-2 text-slate-300">Total <span class="font-semibold text-white tabular-nums" x-text="days[i].total.toLocaleString()"></span></p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- X axis --}}
                <div class="mt-2 ml-11 flex {{ $gap }} text-2xs text-slate-400" aria-hidden="true">
                    @foreach ($daily as $idx => $d)
                        <div class="relative min-w-0 flex-1">
                            @if (($n - 1 - $idx) % $labelEvery === 0)
                                <span @class(['absolute left-1/2 -translate-x-1/2 whitespace-nowrap', 'hidden sm:inline' => intdiv($n - 1 - $idx, $labelEvery) % 2 === 1, 'font-semibold text-slate-600' => $d['date'] === $today])>
                                    {{ $d['date'] === $today ? 'Today' : \Illuminate\Support\Carbon::parse($d['date'])->format('M j') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="h-4"></div>
            </div>

            <div x-show="table" x-cloak class="ui-scroll max-h-72 overflow-auto rounded-lg ring-1 ring-line">
                <table class="ui-table w-full text-[13px]">
                    <thead class="sticky top-0 bg-slate-50"><tr><th class="px-3 py-2 text-left">Date</th>@foreach ($series as $s)<th class="px-3 py-2 text-right">{{ $s['label'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (array_reverse($daily) as $d)
                            <tr class="border-t border-line"><td class="px-3 py-1.5 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($d['date'])->format('D, M j') }}</td>@foreach ($series as $key => $s)<td class="px-3 py-1.5 text-right tabular-nums">{{ number_format($d[$key]) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-1 flex justify-end">
                <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-ink-muted hover:text-ink" x-on:click="table = ! table">
                    <x-ui.icon name="chart" class="size-3.5" /><span x-text="table ? 'Show chart' : 'View as table'">View as table</span>
                </button>
            </div>
        @endif
    </div>
</x-ui.card>
