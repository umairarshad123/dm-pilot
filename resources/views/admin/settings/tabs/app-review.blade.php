{{-- Settings → App Review helper (source: docs/APP_REVIEW.md) --}}
@php($done = collect($checklist)->where('done', true)->count())
<div class="space-y-6">
    <x-ui.section-header title="Meta App Review" description="Everything Meta asks for, with the exact URLs of this installation. Full paste-ready texts: docs/APP_REVIEW.md.">
        <x-ui.button size="sm" variant="ghost" icon="external-link" href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Open App Dashboard</x-ui.button>
    </x-ui.section-header>

    <x-ui.card title="URLs to paste in the dashboard" icon="link" :padded="false">
        <ul class="divide-y divide-line">
            @foreach ($urls as $url)
                <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:gap-4">
                    <div class="min-w-0 sm:w-64 sm:shrink-0">
                        <p class="text-sm font-medium text-ink">{{ $url['label'] }}</p>
                        <p class="text-xs text-ink-muted">{{ $url['where'] }}</p>
                    </div>
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <code class="ui-code min-w-0 flex-1 truncate py-1.5" title="{{ $url['value'] }}">{{ $url['value'] }}</code>
                        <x-ui.copy-button :value="$url['value']" size="xs" :label="'Copy'" />
                    </div>
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    <div class="grid gap-5 lg:grid-cols-5">
        <x-ui.card title="Checklist" icon="check-circle" class="lg:col-span-3" :padded="false">
            <x-slot:actions>
                <span class="text-[13px] text-ink-muted tabular-nums">{{ $done }} / {{ count($checklist) }} verified</span>
            </x-slot:actions>
            <ol class="divide-y divide-line">
                @foreach ($checklist as $i => $item)
                    <li class="flex items-start gap-3.5 px-5 py-3.5">
                        <span @class([
                            'mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-success-500 text-white' => $item['done'] === true,
                            'bg-danger-50 text-danger-600 ring-1 ring-danger-100' => $item['done'] === false,
                            'bg-slate-100 text-slate-500' => $item['done'] === null,
                        ])>
                            @if ($item['done'] === true)<x-ui.icon name="check" class="size-3.5" :stroke="2.75" />@else{{ $i + 1 }}@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                                {{ $item['title'] }}
                                @if ($item['done'] === null)<x-ui.badge size="sm">Manual</x-ui.badge>@endif
                                @if ($item['done'] === false)<x-ui.badge size="sm" tone="danger">To do</x-ui.badge>@endif
                            </p>
                            <p class="mt-0.5 text-[13px] text-ink-muted">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <x-ui.card title="Permissions to request" icon="key" class="lg:col-span-2" :padded="false" description="Advanced Access, when you serve client Pages.">
            <ul class="divide-y divide-line">
                @foreach ($permissions as $perm)
                    <li class="px-5 py-3">
                        <code class="ui-code">{{ $perm['name'] }}</code>
                        <p class="mt-1 text-xs text-ink-muted">{{ $perm['why'] }}</p>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</div>
