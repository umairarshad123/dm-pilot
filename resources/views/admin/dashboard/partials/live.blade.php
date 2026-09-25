{{--
    Dashboard analytics region. Rendered by DashboardController@index and @data (auto-refresh swaps it in place).
    Needs: $kpis, $insights, $leaderboard, $days, $hasPages, $hasActivity, $totalMessages, $selectedPageId, $links.
--}}
<div class="space-y-6">
    {{-- KPI grid --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        @foreach ($kpis as $kpi)
            @include('admin.dashboard.partials.kpi-card', ['kpi' => $kpi])
        @endforeach
    </div>

    {{-- Main chart + channel split --}}
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="min-w-0 xl:col-span-2">
            @include('admin.dashboard.partials.daily-chart', ['daily' => $insights['daily']])
        </div>
        @include('admin.dashboard.partials.channel-split', ['channels' => $insights['by_channel']])
    </div>

    {{-- Heatmap + activity --}}
    <div class="grid gap-6 xl:grid-cols-5">
        <div class="min-w-0 xl:col-span-3">
            @include('admin.dashboard.partials.heatmap', ['heatmap' => $insights['heatmap']])
        </div>
        <div class="min-w-0 xl:col-span-2">
            @include('admin.dashboard.partials.activity', ['recent' => $insights['recent']])
        </div>
    </div>

    @if ($hasPages)
        @include('admin.dashboard.partials.leaderboard')
    @endif
</div>
