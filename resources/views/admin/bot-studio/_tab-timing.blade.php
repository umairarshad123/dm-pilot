{{-- Timing & handoff: reply delay (burst batching), human takeover, history, fallback message. --}}
<div class="space-y-6">
    <x-ui.card title="Reply timing" icon="clock" description="How long the bot waits before answering.">
        <div x-data="bsInherit(@js($inherited['reply_delay_seconds']))" class="space-y-3">
            @include('admin.bot-studio._inherit-head', ['label' => 'Reply delay', 'for' => 'reply_delay_seconds'])
            <div class="flex items-center gap-4">
                <input type="range" min="0" max="60" step="1" class="h-2 flex-1 cursor-pointer accent-brand-600" aria-label="Reply delay slider"
                    x-bind:value="custom ? Math.min(60, Number(value)) : Math.min(60, Number(inherited ?? 0))"
                    x-bind:class="{ 'opacity-50': !custom }"
                    x-on:input="set($event.target.value)">
                <div class="w-32 shrink-0">
                    <x-ui.input type="number" name="reply_delay_seconds" min="0" max="3600" step="1" :value="$setting->reply_delay_seconds" data-inherit-input
                        :placeholder="(string) $inherited['reply_delay_seconds']">
                        <x-slot:trailing>sec</x-slot:trailing>
                    </x-ui.input>
                </div>
            </div>
            <div class="flex gap-3 rounded-xl bg-brand-50/60 p-3.5 text-[13px] text-slate-700 ring-1 ring-brand-100 ring-inset">
                <x-ui.icon name="messages" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                <p>People often send a burst: <em>"hi"</em>, <em>"do you deliver?"</em>, <em>"to DHA?"</em>. With a delay, the bot waits until they stop typing for this long and answers everything in <strong>one</strong> reply. 0 = answer each message immediately. 5–15 seconds feels natural.</p>
            </div>
            @include('admin.bot-studio._inherit-foot', ['name' => 'reply_delay_seconds', 'unit' => 'seconds'])
        </div>
    </x-ui.card>

    <x-ui.card title="Human handoff" icon="hand" description="When your team replies from Live Chat or the Meta inbox, the bot steps back.">
        <div class="grid gap-6 sm:grid-cols-2">
            <div x-data="bsInherit(@js($inherited['human_takeover_minutes']))" class="space-y-2">
                @include('admin.bot-studio._inherit-head', ['label' => 'Pause after a human reply', 'for' => 'human_takeover_minutes'])
                <x-ui.input type="number" name="human_takeover_minutes" min="0" max="43200" step="1" :value="$setting->human_takeover_minutes" data-inherit-input
                    :placeholder="(string) $inherited['human_takeover_minutes']">
                    <x-slot:trailing>min</x-slot:trailing>
                </x-ui.input>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([15 => '15 min', 60 => '1 hour', 240 => '4 hours', 1440 => '1 day'] as $minutes => $label)
                        <button type="button" x-on:click="set(@js((string) $minutes))"
                            x-bind:class="{ 'border-brand-300 bg-brand-50 text-brand-700': String(value) === @js((string) $minutes), 'border-line bg-white text-slate-600 hover:bg-slate-50': String(value) !== @js((string) $minutes) }"
                            class="rounded-full border px-2.5 py-1 text-xs font-medium shadow-xs transition">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="ui-hint">The bot stays quiet in that conversation for this long after a person replies. 0 = never pause.</p>
                @include('admin.bot-studio._inherit-foot', ['name' => 'human_takeover_minutes', 'unit' => 'minutes'])
            </div>

            <div x-data="bsInherit(@js($inherited['history_limit']))" class="space-y-2">
                @include('admin.bot-studio._inherit-head', ['label' => 'Conversation memory', 'for' => 'history_limit'])
                <x-ui.input type="number" name="history_limit" min="0" max="200" step="1" :value="$setting->history_limit" data-inherit-input
                    :placeholder="(string) $inherited['history_limit']">
                    <x-slot:trailing>msgs</x-slot:trailing>
                </x-ui.input>
                <p class="ui-hint">How many recent messages the AI reads before replying. More context = better answers, slightly higher cost.</p>
                @include('admin.bot-studio._inherit-foot', ['name' => 'history_limit', 'unit' => 'messages'])
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="When the AI fails" icon="alert-triangle" description="If the AI provider is down or times out, what should the customer see?">
        <div x-data="bsInherit(@js($inherited['fallback_message']))" class="space-y-4">
            <x-ui.toggle x-model="fallbackOn" :label="$isPage ? 'Use a custom fallback message on this page' : 'Send a fallback message'"
                x-on:change="if (!fallbackOn) clear(); else $nextTick(() => document.getElementById('fallback_message')?.focus())"
                :description="$isPage ? 'Off = inherit the global behaviour.' : 'Off = stay silent; your team sees the failed reply in Live Chat.'" />

            <div x-show="fallbackOn" x-collapse @unless (filled($storedFallback)) x-cloak @endunless>
                <div class="space-y-2">
                    @include('admin.bot-studio._inherit-head', ['label' => 'Fallback message', 'for' => 'fallback_message'])
                    <x-ui.textarea name="fallback_message" rows="2" autosize :counter="2000" maxlength="2000" :value="$setting->fallback_message" data-inherit-input
                        placeholder="Thanks for your message! A team member will reply shortly." />
                    @include('admin.bot-studio._inherit-foot', ['name' => 'fallback_message', 'preview' => false])
                </div>
            </div>

            <div x-show="!fallbackOn" @if (filled($storedFallback)) x-cloak @endif class="flex items-start gap-2.5 rounded-xl bg-slate-50 p-3.5 text-[13px] text-slate-700">
                <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-slate-400" />
                @if (filled($inherited['fallback_message']))
                    <p>{{ $isPage ? 'Uses the global fallback:' : 'Uses the server default (BOT_FALLBACK_MESSAGE):' }} <span class="font-medium">“{{ $inherited['fallback_message'] }}”</span>
                        @if ($isPage)<span class="block pt-1 text-xs text-ink-muted">To stay silent on this page, clear the fallback message in the global defaults.</span>@endif
                    </p>
                @else
                    <p><strong>Stays silent</strong> when the AI fails. The customer gets no automatic reply; the failure shows in Live Chat so a person can answer.</p>
                @endif
            </div>
        </div>
    </x-ui.card>
</div>
