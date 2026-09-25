{{-- Setup checklist with auto-detected progress. $checklist from DashboardController::checklist(). --}}
@php($next = collect($checklist['steps'])->firstWhere('done', false))

<section class="ui-card overflow-hidden" data-checklist>
    <div class="flex flex-col gap-4 border-b border-line bg-gradient-to-r from-brand-50/80 via-white to-white px-5 py-4 sm:flex-row sm:items-center">
        <div class="relative size-14 shrink-0">
            <svg viewBox="0 0 36 36" class="size-full -rotate-90" aria-hidden="true">
                <circle cx="18" cy="18" r="15" fill="none" stroke-width="3.5" class="stroke-brand-100" />
                <circle cx="18" cy="18" r="15" fill="none" stroke-width="3.5" stroke-linecap="round" pathLength="100"
                    stroke-dasharray="{{ max(0.1, $checklist['percent']) }} 100" @class(['stroke-brand-600' => ! $checklist['complete'], 'stroke-success-500' => $checklist['complete']]) />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-[13px] font-semibold text-ink tabular-nums">{{ $checklist['percent'] }}%</span>
        </div>
        <div class="min-w-0 flex-1">
            <h3 class="text-[15px] font-semibold text-ink">{{ $checklist['complete'] ? 'Setup complete' : 'Finish setting up your bot' }}</h3>
            <p class="mt-0.5 text-[13px] text-ink-muted">{{ $checklist['done'] }} of {{ $checklist['total'] }} steps done{{ $next ? ' · next: '.$next['title'] : '' }}</p>
        </div>
        @if ($next && $next['href'])
            <x-ui.button variant="primary" size="sm" icon-right="arrow-right" :href="$next['href']">{{ $next['cta'] }}</x-ui.button>
        @endif
    </div>
    <ol class="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($checklist['steps'] as $i => $step)
            <li @class(['flex gap-3 bg-white p-4', 'bg-brand-50/40' => $next && $next['key'] === $step['key']]) data-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? '1' : '0' }}">
                @if ($step['done'])
                    <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-success-500 text-white"><x-ui.icon name="check" class="size-3.5" :stroke="3" /><span class="sr-only">Done:</span></span>
                @else
                    <span @class(['inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ring-1 ring-inset',
                        'bg-brand-600 text-white ring-brand-600' => $next && $next['key'] === $step['key'],
                        'bg-white text-slate-500 ring-line-strong' => ! ($next && $next['key'] === $step['key'])])>{{ $i + 1 }}</span>
                @endif
                <div class="min-w-0">
                    <p @class(['text-[13px] font-semibold', 'text-slate-400 line-through decoration-slate-300' => $step['done'], 'text-ink' => ! $step['done']])>{{ $step['title'] }}</p>
                    @unless ($step['done'])
                        <p class="mt-0.5 text-xs text-ink-muted">{{ $step['description'] }}</p>
                        @if ($step['href'])
                            <a href="{{ $step['href'] }}" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline">{{ $step['cta'] }} <x-ui.icon name="arrow-right" class="size-3" /></a>
                        @endif
                    @endunless
                </div>
            </li>
        @endforeach
    </ol>
</section>
