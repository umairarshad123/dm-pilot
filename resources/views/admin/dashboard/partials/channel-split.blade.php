{{-- Messenger vs Instagram donut. $channels = InsightsService::byChannel(). --}}
@php
    $rows = [];
    foreach (['facebook' => ['Messenger', 'stroke-messenger', 'bg-messenger'], 'instagram' => ['Instagram', 'stroke-instagram', 'bg-instagram']] as $key => [$label, $stroke, $bg]) {
        $c = $channels[$key] ?? ['incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0, 'active_conversations' => 0, 'conversations_started' => 0];
        $rows[$key] = [
            'label' => $label, 'stroke' => $stroke, 'bg' => $bg,
            'messages' => $c['incoming'] + $c['bot'] + $c['human'],
            'incoming' => $c['incoming'], 'replies' => $c['bot'] + $c['human'],
            'conversations' => $c['active_conversations'],
        ];
    }
    $total = array_sum(array_column($rows, 'messages'));
    $offset = 0;
@endphp

<x-ui.card title="Channels" description="Messages by channel" class="min-w-0">
    <div class="flex flex-col items-center gap-6 sm:flex-row xl:flex-col 2xl:flex-row" x-data="{ hover: null }">
        <div class="relative size-40 shrink-0">
            <svg viewBox="0 0 42 42" class="size-full -rotate-90" role="img" aria-label="Messenger {{ $rows['facebook']['messages'] }} messages, Instagram {{ $rows['instagram']['messages'] }} messages">
                <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="5" class="stroke-slate-100" />
                @if ($total > 0)
                    @foreach ($rows as $key => $r)
                        @continue($r['messages'] === 0)
                        @php($pct = $r['messages'] / $total * 100)
                        @php($gapPct = $pct < 100 ? 1.2 : 0)
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="5" class="{{ $r['stroke'] }} transition-[stroke-width] duration-150"
                            x-bind:stroke-width="hover === '{{ $key }}' ? 6.5 : 5"
                            x-on:mouseenter="hover = '{{ $key }}'" x-on:mouseleave="hover = null"
                            stroke-dasharray="{{ round(max(0.1, $pct - $gapPct), 3) }} {{ round(100 - max(0.1, $pct - $gapPct), 3) }}"
                            stroke-dashoffset="{{ round(-$offset, 3) }}" pathLength="100">
                            <title>{{ $r['label'] }}: {{ number_format($r['messages']) }} messages ({{ round($pct) }}%)</title>
                        </circle>
                        @php($offset += $pct)
                    @endforeach
                @endif
            </svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                <template x-if="hover === null"><div>
                    <p class="text-2xl font-semibold tracking-tight text-ink">{{ \App\Http\Controllers\Admin\DashboardController::compact($total) }}</p>
                    <p class="text-2xs font-medium tracking-wider text-slate-400 uppercase">messages</p>
                </div></template>
                @foreach ($rows as $key => $r)
                    <template x-if="hover === '{{ $key }}'"><div>
                        <p class="text-2xl font-semibold tracking-tight text-ink">{{ $total > 0 ? round($r['messages'] / $total * 100) : 0 }}%</p>
                        <p class="text-2xs font-medium tracking-wider text-slate-400 uppercase">{{ $r['label'] }}</p>
                    </div></template>
                @endforeach
            </div>
        </div>

        <ul class="w-full min-w-0 flex-1 space-y-3">
            @foreach ($rows as $key => $r)
                <li class="rounded-xl p-3 ring-1 ring-line transition-colors" x-bind:class="{ 'bg-slate-50': hover === '{{ $key }}' }"
                    x-on:mouseenter="hover = '{{ $key }}'" x-on:mouseleave="hover = null">
                    <div class="flex items-center gap-2.5">
                        <x-ui.channel-badge :platform="$key" variant="icon" size="sm" />
                        <span class="flex-1 text-[13px] font-semibold text-ink">{{ $r['label'] }}</span>
                        <span class="text-[13px] font-semibold text-ink tabular-nums">{{ number_format($r['messages']) }}</span>
                        <span class="w-10 text-right text-xs text-ink-muted tabular-nums">{{ $total > 0 ? round($r['messages'] / $total * 100) : 0 }}%</span>
                    </div>
                    <p class="mt-1.5 pl-[30px] text-xs text-ink-muted">
                        {{ number_format($r['conversations']) }} {{ \Illuminate\Support\Str::plural('conversation', $r['conversations']) }} · {{ number_format($r['incoming']) }} in · {{ number_format($r['replies']) }} replies
                    </p>
                </li>
            @endforeach
        </ul>
    </div>
</x-ui.card>
