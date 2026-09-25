{{-- Connect wizard progress. Vars: $step (1..3) --}}
@php($steps = [1 => 'Sign in', 2 => 'Choose Pages', 3 => 'Done'])
<nav aria-label="Progress" class="mb-8">
    <ol class="flex items-center">
        @foreach ($steps as $n => $label)
            <li @class(['flex items-center', 'flex-1' => ! $loop->last])>
                <span class="flex items-center gap-2.5" @if ($n === $step) aria-current="step" @endif>
                    <span @class([
                        'inline-flex size-8 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold transition',
                        'bg-brand-600 text-white shadow-xs' => $n < $step,
                        'bg-white text-brand-700 ring-2 ring-brand-600' => $n === $step,
                        'bg-white text-slate-400 ring-1 ring-line' => $n > $step,
                    ])>
                        @if ($n < $step)<x-ui.icon name="check" class="size-4" :stroke="2.5" />@else{{ $n }}@endif
                    </span>
                    <span @class(['hidden text-[13px] font-medium sm:inline', 'text-ink' => $n <= $step, 'text-slate-400' => $n > $step])>{{ $label }}</span>
                </span>
                @unless ($loop->last)
                    <span @class(['mx-3 h-px flex-1 sm:mx-4', 'bg-brand-600' => $n < $step, 'bg-line' => $n >= $step]) aria-hidden="true"></span>
                @endunless
            </li>
        @endforeach
    </ol>
</nav>
