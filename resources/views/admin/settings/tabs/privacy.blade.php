{{-- Settings → Data & privacy --}}
@php
    $statusTones = ['completed' => 'success', 'no_data' => 'neutral', 'pending' => 'warning', 'failed' => 'danger'];
    $statusLabels = ['completed' => 'Deleted', 'no_data' => 'No data found', 'pending' => 'Pending', 'failed' => 'Failed'];
@endphp
<div class="space-y-6">
    <x-ui.section-header title="Data & privacy" description="What is kept, for how long, and the public pages Meta reviewers check." />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Conversations kept" :value="($retention['conversations_months'] ?? '?').' months'" icon="messages" tone="brand" hint="DATA_RETENTION_MONTHS" />
        <x-ui.stat label="Webhook payloads kept" :value="($retention['webhook_events_days'] ?? '?').' days'" icon="webhook" tone="purple" hint="WEBHOOK_EVENT_RETENTION_DAYS" />
        <x-ui.stat label="Logs kept" :value="($retention['logs_days'] ?? '?').' days'" icon="book" tone="neutral" hint="LOG_RETENTION_DAYS" />
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        <x-ui.card title="Public pages" icon="globe" class="lg:col-span-2" :padded="false">
            <ul class="divide-y divide-line">
                @foreach ($links as $link)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><x-ui.icon :name="$link['icon']" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-ink">{{ $link['label'] }}</p>
                            <p class="truncate text-xs text-ink-muted">{{ $link['url'] }}</p>
                        </div>
                        <x-ui.copy-button :value="$link['url']" size="xs" variant="ghost" icon-only :label="'Copy '.$link['label'].' URL'" />
                        <x-ui.button :href="$link['url']" size="xs" variant="ghost" icon="external-link" target="_blank" rel="noopener" :aria-label="'Open '.$link['label']" />
                    </li>
                @endforeach
            </ul>
            <x-slot:footer>
                <p class="mr-auto text-xs text-ink-muted">Operator <span class="font-medium text-slate-700">{{ $operator }}</span> · {{ $contactEmail }}</p>
            </x-slot:footer>
        </x-ui.card>

        <x-ui.card title="Deletion & deauthorize requests" icon="shield" class="lg:col-span-3" :padded="false"
            description="Received from Meta on /meta/data-deletion and /meta/deauthorize.">
            @if ($deletionRequests->isEmpty())
                <x-ui.empty-state compact icon="shield" tone="neutral" title="No requests yet" description="When someone removes the app or asks Meta to delete their data, it shows up here." />
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead><tr><th>Code</th><th>Type</th><th>Status</th><th class="text-right">Received</th></tr></thead>
                        <tbody>
                            @foreach ($deletionRequests as $req)
                                <tr>
                                    <td class="font-mono text-xs">{{ $req->confirmation_code }}</td>
                                    <td>
                                        <x-ui.badge size="sm" :tone="$req->type === 'deauthorize' ? 'purple' : 'info'">{{ $req->type === 'deauthorize' ? 'Deauthorize' : 'Data deletion' }}</x-ui.badge>
                                    </td>
                                    <td>
                                        <x-ui.badge size="sm" dot :tone="$statusTones[$req->status] ?? 'neutral'">{{ $statusLabels[$req->status] ?? $req->status }}</x-ui.badge>
                                        @if ($req->type === 'deauthorize' && $req->accounts_affected)
                                            <span class="ml-1 text-xs text-ink-muted">{{ $req->accounts_affected }} paused</span>
                                        @elseif ($req->conversations_deleted || $req->messages_deleted)
                                            <span class="ml-1 text-xs text-ink-muted">{{ $req->conversations_deleted }} conv. · {{ $req->messages_deleted }} msg.</span>
                                        @endif
                                    </td>
                                    <td class="text-right whitespace-nowrap text-ink-muted" title="{{ $req->created_at?->toDayDateTimeString() }}">{{ $req->created_at?->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="AI sub-processors" icon="sparkles" description="Listed in the Privacy Policy: they may receive message text to write replies.">
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach ($subprocessors as $sp)
                <li class="rounded-xl px-4 py-3 ring-1 ring-line ring-inset">
                    <p class="text-sm font-medium text-ink">{{ $sp['name'] }}</p>
                    <p class="text-xs text-ink-muted">{{ $sp['purpose'] }}</p>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
