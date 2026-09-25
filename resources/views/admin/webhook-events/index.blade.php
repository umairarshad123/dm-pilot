@php
    $tones = ['pending' => 'warning', 'processed' => 'success', 'ignored' => 'neutral', 'failed' => 'danger'];
    $current = $filters['status'] ?? null;
    $base = array_filter(['object' => $filters['object'] ?? null]);
    $total = $counts->sum();
@endphp
<x-layouts.app title="Settings">
    <x-slot:subnav>
        <x-ui.tabs :tabs="\App\Http\Controllers\Admin\SettingsController::tabs('webhooks')" class="-mb-px" />
    </x-slot:subnav>

    <div class="space-y-5">
        <x-ui.section-header title="Webhook events" :description="'Every payload Meta delivered, newest first. '.number_format($last24h).' in the last 24 hours. Kept '.config('legal.retention.webhook_events_days', 30).' days.'" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav class="inline-flex flex-wrap items-center gap-1 rounded-xl bg-slate-100 p-1" aria-label="Filter by status">
                <a href="{{ route('admin.webhook-events.index', $base) }}" @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition', 'bg-white text-ink shadow-xs' => ! $current, 'text-slate-600 hover:text-ink' => $current]) @if (! $current) aria-current="page" @endif>
                    All <span class="text-2xs text-slate-400 tabular-nums">{{ number_format($total) }}</span>
                </a>
                @foreach (\App\Http\Controllers\Admin\WebhookEventController::STATUSES as $status)
                    <a href="{{ route('admin.webhook-events.index', $base + ['status' => $status]) }}"
                        @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium capitalize transition', 'bg-white text-ink shadow-xs' => $current === $status, 'text-slate-600 hover:text-ink' => $current !== $status])
                        @if ($current === $status) aria-current="page" @endif>
                        <span @class(['size-1.5 rounded-full', 'bg-warning-500' => $status === 'pending', 'bg-success-500' => $status === 'processed', 'bg-slate-400' => $status === 'ignored', 'bg-danger-500' => $status === 'failed'])></span>
                        {{ $status }} <span class="text-2xs text-slate-400 tabular-nums">{{ number_format($counts[$status] ?? 0) }}</span>
                    </a>
                @endforeach
            </nav>

            <x-ui.filter-bar :action="route('admin.webhook-events.index')" :search="null">
                @if ($current)<input type="hidden" name="status" value="{{ $current }}">@endif
                <x-ui.select name="object" size="sm" class="w-auto" placeholder="All objects"
                    :options="$objects->mapWithKeys(fn ($o) => [$o => $o === 'page' ? 'page (Messenger)' : $o])->all()" :value="$filters['object'] ?? null" />
            </x-ui.filter-bar>
        </div>

        <x-ui.table hoverable>
            <x-slot:head>
                <tr><th class="w-20">#</th><th>Object</th><th>Status</th><th class="text-right">Messages</th><th>Error</th><th class="text-right">Received</th></tr>
            </x-slot:head>
            @forelse ($events as $event)
                <tr class="cursor-pointer" onclick="location.href='{{ route('admin.webhook-events.show', $event) }}'">
                    <td><a href="{{ route('admin.webhook-events.show', $event) }}" class="font-mono text-xs text-ink-muted hover:text-brand-700">{{ $event->id }}</a></td>
                    <td>
                        <span class="inline-flex items-center gap-2">
                            @if ($event->object === 'instagram')
                                <x-ui.channel-badge platform="instagram" variant="icon" size="xs" class="!ring-0" />
                            @elseif ($event->object === 'page')
                                <x-ui.channel-badge platform="facebook" variant="icon" size="xs" class="!ring-0" />
                            @endif
                            <span class="font-medium text-ink">{{ $event->object ?: 'unknown' }}</span>
                        </span>
                    </td>
                    <td><x-ui.badge size="sm" dot :tone="$tones[$event->status] ?? 'neutral'" class="capitalize">{{ $event->status }}</x-ui.badge></td>
                    <td class="text-right tabular-nums">{{ $event->messages_count }}</td>
                    <td class="max-w-xs"><span class="block truncate text-[13px] text-danger-700" title="{{ $event->error }}">{{ $event->error }}</span></td>
                    <td class="text-right whitespace-nowrap text-ink-muted" title="{{ $event->created_at?->toDayDateTimeString() }}">{{ $event->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6">
                    <x-ui.empty-state compact icon="webhook" title="No webhook events"
                        :description="$current || ($filters['object'] ?? null) ? 'Nothing matches these filters.' : 'Events appear as soon as Meta delivers a message to /webhooks/meta.'" />
                </td></tr>
            @endforelse
            <x-slot:footer>{{ $events->links('components.ui.pagination') }}</x-slot:footer>
        </x-ui.table>
    </div>
</x-layouts.app>
