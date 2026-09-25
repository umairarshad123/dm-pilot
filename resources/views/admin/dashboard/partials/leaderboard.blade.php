{{-- Per-page leaderboard (all pages; the selected one is highlighted). $leaderboard = InsightsService::byPage(null, $days). --}}
@php
    $maxVolume = max(1, ...array_map(fn ($p) => $p['incoming'] + $p['bot'] + $p['human'], $leaderboard ?: [['incoming' => 0, 'bot' => 0, 'human' => 0]]));
@endphp

<x-ui.table hoverable>
    <x-slot:toolbar>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-semibold text-ink">Pages</h3>
                <p class="mt-0.5 text-[13px] text-ink-muted">Ranked by message volume · last {{ $days }} days</p>
            </div>
            <x-ui.button size="sm" variant="ghost" icon-right="arrow-right" :href="$links['pages']">Manage pages</x-ui.button>
        </div>
    </x-slot:toolbar>
    <x-slot:head>
        <tr>
            <th class="w-8">#</th>
            <th>Page</th>
            <th class="text-right">Conversations</th>
            <th class="text-right">Incoming</th>
            <th class="text-right">Bot</th>
            <th class="text-right">Human</th>
            <th class="text-right">Leads</th>
            <th class="min-w-40">Volume</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>
        </tr>
    </x-slot:head>
    @forelse ($leaderboard as $i => $p)
        @php($volume = $p['incoming'] + $p['bot'] + $p['human'])
        @php($selected = $selectedPageId === $p['meta_account_id'])
        <tr @class(['bg-brand-50/50' => $selected])>
            <td class="text-xs font-semibold text-slate-400 tabular-nums">{{ $i + 1 }}</td>
            <td>
                <div class="flex min-w-48 items-center gap-3">
                    <x-ui.avatar :name="$p['name']" :channel="$p['platform']" size="sm" square />
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink">{{ $p['name'] }}</p>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <x-ui.channel-badge :platform="$p['platform']" variant="plain" size="xs" />
                            @if ($p['active'])
                                <x-ui.badge tone="success" size="sm" dot>Active</x-ui.badge>
                            @else
                                <x-ui.badge tone="neutral" size="sm" dot>Paused</x-ui.badge>
                            @endif
                        </div>
                    </div>
                </div>
            </td>
            <td class="text-right tabular-nums">{{ number_format($p['active_conversations']) }}</td>
            <td class="text-right tabular-nums">{{ number_format($p['incoming']) }}</td>
            <td class="text-right tabular-nums">{{ number_format($p['bot']) }}</td>
            <td class="text-right tabular-nums">{{ number_format($p['human']) }}</td>
            <td class="text-right tabular-nums">{{ number_format($p['leads_captured']) }}</td>
            <td>
                <div class="flex items-center gap-2">
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round($volume / $maxVolume * 100, 1) }}%"></div>
                    </div>
                    <span class="w-10 text-right text-xs text-ink-muted tabular-nums">{{ \App\Http\Controllers\Admin\DashboardController::compact($volume) }}</span>
                </div>
            </td>
            <td class="text-right whitespace-nowrap">
                @if ($selected)
                    <x-ui.badge tone="brand" size="sm">Viewing</x-ui.badge>
                @else
                    <form method="POST" action="{{ $links['page_switch'] }}" class="inline" data-no-loading>
                        @csrf
                        <input type="hidden" name="meta_account_id" value="{{ $p['meta_account_id'] }}">
                        <x-ui.button type="submit" size="xs" variant="secondary" icon-right="arrow-right">View</x-ui.button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="9"><x-ui.empty-state compact icon="layers" title="No pages connected" description="Connect a Facebook Page or Instagram account to start." /></td></tr>
    @endforelse
</x-ui.table>
