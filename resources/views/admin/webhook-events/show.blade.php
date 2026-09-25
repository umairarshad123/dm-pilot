{{-- JSON colours come from WebhookEventController::highlight(): text-sky-300 text-emerald-300 text-violet-300 text-amber-300 --}}
@php($tones = ['pending' => 'warning', 'processed' => 'success', 'ignored' => 'neutral', 'failed' => 'danger'])
<x-layouts.app title="Settings">
    <x-slot:subnav>
        <x-ui.tabs :tabs="\App\Http\Controllers\Admin\SettingsController::tabs('webhooks')" class="-mb-px" />
    </x-slot:subnav>

    <x-ui.page-header :title="'Event #'.$event->id" :back="route('admin.webhook-events.index')" back-label="Webhook events"
        :description="'Received '.$event->created_at?->toDayDateTimeString().' ('.$event->created_at?->diffForHumans().')'">
        <x-slot:meta>
            <x-ui.badge dot :tone="$tones[$event->status] ?? 'neutral'" class="capitalize">{{ $event->status }}</x-ui.badge>
            <x-ui.badge>{{ $event->object ?: 'unknown' }}</x-ui.badge>
        </x-slot:meta>
        <x-ui.button size="sm" variant="ghost" icon="chevron-left" :href="$previous ? route('admin.webhook-events.show', $previous) : null" :disabled="! $previous" aria-label="Older event" />
        <x-ui.button size="sm" variant="ghost" icon="chevron-right" :href="$next ? route('admin.webhook-events.show', $next) : null" :disabled="! $next" aria-label="Newer event" />
    </x-ui.page-header>

    <div class="grid items-start gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:order-2">
            @if ($event->error)
                <x-ui.alert tone="danger" title="Processing error">{{ $event->error }}</x-ui.alert>
            @endif

            <x-ui.card title="Details" :padded="false">
                <dl class="divide-y divide-line text-[13px]">
                    @foreach ([
                        'Status' => ucfirst($event->status),
                        'Object' => $event->object ?: 'unknown',
                        'Messages stored' => $event->messages_count,
                        'Messaging items' => $messagingCount,
                        'Received' => $event->created_at?->toDateTimeString(),
                        'Processed' => $event->processed_at?->toDateTimeString() ?? 'Not yet',
                    ] as $label => $value)
                        <div class="flex items-center justify-between gap-4 px-5 py-2.5">
                            <dt class="text-ink-muted">{{ $label }}</dt>
                            <dd class="font-medium text-ink tabular-nums">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="px-5 py-2.5">
                        <dt class="text-ink-muted">Payload hash</dt>
                        <dd class="mt-1 truncate font-mono text-xs text-slate-500" title="{{ $event->payload_hash }}">{{ $event->payload_hash }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Channels in this event" :padded="false">
                @if ($entryIds->isEmpty())
                    <p class="px-5 py-4 text-[13px] text-ink-muted">No entry ids in the payload.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($entryIds as $entryId)
                            @php($account = $accounts->first(fn ($a) => $a->ownExternalId() === $entryId) ?? $accounts->first(fn ($a) => $a->page_id === $entryId))
                            <li class="flex items-center gap-3 px-5 py-3">
                                @if ($account)
                                    <x-ui.channel-badge :platform="$account->platform" variant="icon" size="sm" class="!ring-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $account->page_name }}</p>
                                        <p class="font-mono text-2xs text-slate-400">{{ $entryId }}</p>
                                    </div>
                                    <x-ui.button size="xs" variant="ghost" :href="route('admin.meta-accounts.edit', $account)" icon="arrow-up-right" aria-label="Open channel" />
                                @else
                                    <span class="inline-flex size-5 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-ui.icon name="alert-circle" class="size-3" /></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm text-ink-muted">Not a connected channel</p>
                                        <p class="font-mono text-2xs text-slate-400">{{ $entryId }}</p>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="min-w-0 lg:order-1 lg:col-span-2" x-data="{ wrap: false }">
            <div class="overflow-hidden rounded-2xl bg-slate-900 shadow-card ring-1 ring-slate-800">
                <div class="flex items-center gap-2 border-b border-white/10 px-4 py-2.5">
                    <span class="flex gap-1.5" aria-hidden="true"><span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span></span>
                    <span class="ml-2 flex-1 font-mono text-xs text-slate-400">payload.json · {{ number_format(strlen($json)) }} bytes</span>
                    <button type="button" x-on:click="wrap = ! wrap" class="rounded-md px-2 py-1 text-xs font-medium text-slate-400 hover:bg-white/10 hover:text-white" x-text="wrap ? 'No wrap' : 'Wrap'">Wrap</button>
                    <x-ui.copy-button target="#payload-json" variant="ghost" size="xs" label="Copy JSON" class="!text-slate-300 hover:!bg-white/10 hover:!text-white" />
                </div>
                <pre id="payload-json" class="ui-scroll max-h-[70vh] overflow-auto p-4 font-mono text-[12.5px] leading-relaxed text-slate-300" x-bind:class="{ 'whitespace-pre-wrap break-all': wrap }">{!! $highlighted !!}</pre>
            </div>
        </div>
    </div>
</x-layouts.app>
