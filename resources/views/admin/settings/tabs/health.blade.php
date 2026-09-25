{{-- Settings → System health --}}
@php
    $allGood = $summary['errors'] === 0 && $summary['warnings'] === 0;
    $groups = collect($checks)->groupBy('group');
@endphp
<div class="space-y-6">
    <div @class([
        'relative overflow-hidden rounded-2xl p-6 ring-1 ring-inset',
        'bg-gradient-to-br from-success-50 to-white ring-success-100' => $allGood,
        'bg-gradient-to-br from-warning-50 to-white ring-amber-200/70' => ! $allGood && $summary['errors'] === 0,
        'bg-gradient-to-br from-danger-50 to-white ring-danger-100' => $summary['errors'] > 0,
    ])>
        <div class="flex flex-wrap items-center gap-4">
            <span @class([
                'inline-flex size-12 items-center justify-center rounded-2xl text-white shadow-sm',
                'bg-success-500' => $allGood,
                'bg-warning-500' => ! $allGood && $summary['errors'] === 0,
                'bg-danger-500' => $summary['errors'] > 0,
            ])>
                <x-ui.icon :name="$allGood ? 'check' : ($summary['errors'] > 0 ? 'alert-circle' : 'alert-triangle')" class="size-6" :stroke="2.25" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold tracking-tight text-ink">
                    {{ $allGood ? 'All systems go' : ($summary['errors'] > 0 ? $summary['errors'].' '.\Illuminate\Support\Str::plural('problem', $summary['errors']).' to fix' : $summary['warnings'].' '.\Illuminate\Support\Str::plural('thing', $summary['warnings']).' to review') }}
                </h2>
                <p class="text-[13px] text-ink-muted">{{ $summary['ok'] }} of {{ $summary['total'] }} checks pass · {{ $env }} · PHP {{ $versions['php'] }} · Laravel {{ $versions['laravel'] }}</p>
            </div>
            <x-ui.button size="sm" icon="refresh" :href="route('admin.settings.index', ['tab' => 'health'])">Re-run</x-ui.button>
        </div>
    </div>

    @if ($debug && $env === 'production')
        <x-ui.alert tone="danger" title="APP_DEBUG is on in production">Set APP_DEBUG=false: error pages can leak configuration.</x-ui.alert>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-2">
        @foreach ($groups as $group => $items)
            <x-ui.card :title="$group" :padded="false">
                <ul class="divide-y divide-line">
                    @foreach ($items as $check)
                        @php($state = $check['ok'] ? 'ok' : (empty($check['warn']) ? 'error' : 'warn'))
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <span @class([
                                'mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full',
                                'bg-success-50 text-success-600' => $state === 'ok',
                                'bg-warning-50 text-warning-600' => $state === 'warn',
                                'bg-danger-50 text-danger-600' => $state === 'error',
                            ])>
                                <x-ui.icon :name="$state === 'ok' ? 'check' : ($state === 'warn' ? 'alert-triangle' : 'x')" class="size-3" :stroke="2.75" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-ink">{{ $check['label'] }}</p>
                                @unless ($check['ok'])<p class="mt-0.5 text-xs text-ink-muted">{{ $check['hint'] }}</p>@endunless
                            </div>
                            <span @class(['shrink-0 text-[13px] tabular-nums', 'text-ink-muted' => $state === 'ok', 'font-medium text-warning-700' => $state === 'warn', 'font-medium text-danger-700' => $state === 'error'])>{{ $check['value'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($group === 'Webhooks')
                    <x-slot:footer>
                        <a href="{{ route('admin.webhook-events.index') }}" class="ui-link text-[13px]">Open the webhook event log →</a>
                    </x-slot:footer>
                @endif
            </x-ui.card>
        @endforeach

        <x-ui.card title="Background workers" icon="clock">
            <div class="space-y-4 text-[13px] text-ink-muted">
                <p>
                    Queue <code class="ui-code">{{ $queue }}</code>
                    @if ($queuedJobs !== null) · <span class="font-medium text-ink tabular-nums">{{ $queuedJobs }}</span> jobs waiting @endif
                </p>
                <div>
                    <p class="font-medium text-slate-700">Queue worker (keep running, e.g. Supervisor)</p>
                    @php($cmd = 'php artisan queue:work --tries=3')
                    <div class="group relative mt-1.5"><pre class="ui-pre ui-scroll pr-12">{{ $cmd }}</pre><x-ui.copy-button :value="$cmd" variant="ghost" size="xs" icon-only label="Copy command" class="!absolute top-2 right-2 !text-slate-400 hover:!bg-white/10 hover:!text-white" /></div>
                </div>
                <div>
                    <p class="font-medium text-slate-700">Scheduler (cron, every minute): prunes webhook events</p>
                    @php($cron = '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1')
                    <div class="group relative mt-1.5"><pre class="ui-pre ui-scroll pr-12">{{ $cron }}</pre><x-ui.copy-button :value="$cron" variant="ghost" size="xs" icon-only label="Copy cron line" class="!absolute top-2 right-2 !text-slate-400 hover:!bg-white/10 hover:!text-white" /></div>
                </div>
            </div>
        </x-ui.card>
    </div>
</div>
