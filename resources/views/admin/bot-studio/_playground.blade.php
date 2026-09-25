{{-- "Test your bot": phone-style chat against the SAVED settings (BotStudioPlaygroundController). --}}
@php
    $playgroundConfig = [
        'url' => route('admin.bot-settings.playground'),
        'accountId' => $account?->id,
        'platform' => $account?->platform?->value ?? 'facebook',
        'pageName' => $pageName ?? 'Your page',
        'botEnabled' => $isPage ? ($setting->bot_enabled ?? true) && $global->bot_enabled : $global->bot_enabled,
    ];
@endphp
<div class="ui-card overflow-hidden" x-data="bsPlayground(@js($playgroundConfig))">
    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-[15px] font-semibold text-ink"><x-ui.icon name="message" class="size-4 text-brand-600" />Test your bot</h2>
            <p class="truncate text-xs text-ink-muted">{{ $isPage ? 'Private test as '.$pageName : 'Private test · global defaults' }}</p>
        </div>
        <div class="inline-flex items-center gap-0.5 rounded-lg bg-slate-100 p-0.5" role="radiogroup" aria-label="Channel preview">
            <button type="button" role="radio" x-on:click="platform = 'facebook'" x-bind:aria-checked="platform === 'facebook'"
                x-bind:class="{ 'bg-white text-messenger shadow-xs': platform === 'facebook', 'text-slate-500 hover:text-ink': platform !== 'facebook' }"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition" title="Messenger">
                <x-ui.icon name="messenger" class="size-3.5" /><span class="hidden sm:inline">Messenger</span>
            </button>
            <button type="button" role="radio" x-on:click="platform = 'instagram'" x-bind:aria-checked="platform === 'instagram'"
                x-bind:class="{ 'bg-white text-instagram shadow-xs': platform === 'instagram', 'text-slate-500 hover:text-ink': platform !== 'instagram' }"
                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition" title="Instagram">
                <x-ui.icon name="instagram" class="size-3.5" /><span class="hidden sm:inline">Instagram</span>
            </button>
        </div>
    </div>

    <div x-show="unsaved" x-cloak x-transition class="flex items-center gap-2 border-b border-warning-100 bg-warning-50 px-4 py-2 text-xs font-medium text-warning-700">
        <x-ui.icon name="alert-triangle" class="size-3.5 shrink-0" /> The playground uses saved settings. Save to test your changes.
    </div>
    <div x-show="!botEnabled" @if ($playgroundConfig['botEnabled']) x-cloak @endif class="flex items-center gap-2 border-b border-danger-100 bg-danger-50 px-4 py-2 text-xs font-medium text-danger-700">
        <x-ui.icon name="pause" class="size-3.5 shrink-0" /> The bot is OFF for real customers. You can still test here.
    </div>

    {{-- Phone --}}
    <div class="bg-slate-100/70 p-3 sm:p-4">
        <div class="mx-auto flex h-[560px] max-h-[70vh] w-full max-w-[380px] flex-col overflow-hidden rounded-[28px] border-[6px] border-slate-900 bg-white shadow-pop">
            {{-- App header --}}
            <div class="flex items-center gap-2.5 border-b px-3 py-2.5"
                x-bind:class="{ 'border-slate-100': platform === 'facebook', 'border-slate-200': platform === 'instagram' }">
                <x-ui.icon name="chevron-left" class="size-5" x-bind:class="{ 'text-messenger': platform === 'facebook', 'text-ink': platform === 'instagram' }" />
                <span class="relative">
                    <x-ui.avatar :name="$pageName ?? 'Your page'" size="sm" />
                    <span class="absolute right-0 bottom-0 size-2.5 rounded-full bg-success-500 ring-2 ring-white"></span>
                </span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-[13px] font-semibold text-ink" x-text="pageName"></p>
                    <p class="text-2xs text-ink-muted" x-text="platform === 'instagram' ? 'Active now' : 'Typically replies instantly'"></p>
                </div>
                <button type="button" x-on:click="reset()" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink" aria-label="Reset chat" title="Reset chat">
                    <x-ui.icon name="refresh" class="size-4" />
                </button>
            </div>

            {{-- Thread --}}
            <div x-ref="thread" class="ui-scroll flex-1 space-y-1.5 overflow-y-auto px-3 py-4" aria-live="polite">
                <template x-if="messages.length === 0">
                    <div class="flex h-full flex-col items-center justify-center px-4 text-center">
                        <x-ui.avatar :name="$pageName ?? 'Your page'" size="lg" />
                        <p class="mt-3 text-sm font-semibold text-ink" x-text="pageName"></p>
                        <p class="mt-1 text-xs text-ink-muted">Send a message the way a customer would, or tap Get Started to test your welcome message.</p>
                        <button type="button" x-on:click="getStarted()"
                            x-bind:class="{ 'bg-messenger text-white hover:bg-messenger/90': platform === 'facebook', 'bg-instagram-gradient text-white': platform === 'instagram' }"
                            class="mt-4 rounded-full px-4 py-1.5 text-[13px] font-semibold shadow-xs transition">Get Started</button>
                        <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                            @foreach (['Hi! What do you sell?', 'How much does it cost?', 'Are you open today?'] as $sample)
                                <button type="button" x-on:click="send(@js($sample))" class="rounded-full border border-line bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">{{ $sample }}</button>
                            @endforeach
                        </div>
                    </div>
                </template>

                <template x-for="m in messages" :key="m.id">
                    <div class="flex flex-col" x-bind:class="{ 'items-end': m.role === 'user', 'items-start': m.role !== 'user' }">
                        {{-- typing --}}
                        <template x-if="m.pending">
                            <div class="flex items-center gap-1 rounded-2xl bg-slate-100 px-3.5 py-3" aria-label="Bot is typing">
                                <span class="size-1.5 animate-bounce rounded-full bg-slate-400"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:120ms]"></span>
                                <span class="size-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:240ms]"></span>
                            </div>
                        </template>
                        <template x-if="!m.pending">
                            <div class="max-w-[82%]">
                                <div class="rounded-[20px] px-3.5 py-2 text-[13.5px] leading-snug break-words whitespace-pre-line"
                                    x-bind:class="{
                                        'bg-messenger text-white': m.role === 'user' && platform === 'facebook',
                                        'bg-instagram-gradient text-white': m.role === 'user' && platform === 'instagram',
                                        'bg-slate-100 text-ink': m.role !== 'user' && !m.error,
                                        'bg-danger-50 text-danger-700 ring-1 ring-danger-100': m.error,
                                    }"
                                    x-text="m.content"></div>
                                <template x-if="m.meta">
                                    <div class="mt-1 flex flex-wrap items-center gap-1 px-1 text-2xs text-ink-muted">
                                        <span class="inline-flex items-center gap-1 rounded-full px-1.5 py-px font-semibold"
                                            x-bind:class="{ 'bg-violet-50 text-violet-700': m.meta.source === 'automation', 'bg-brand-50 text-brand-700': m.meta.source === 'ai' }"
                                            x-text="m.meta.source === 'automation' ? 'Automation' : 'AI'"></span>
                                        <span x-show="m.meta.source === 'automation'" class="max-w-40 truncate" x-text="m.meta.rule_name || ('Rule #' + m.meta.rule_id)"></span>
                                        <span x-show="m.meta.source === 'ai'" class="font-mono" x-text="m.meta.provider + ' · ' + m.meta.model"></span>
                                        <span class="tabular-nums" x-text="'· ' + (m.meta.latency_ms >= 1000 ? (m.meta.latency_ms / 1000).toFixed(1) + 's' : m.meta.latency_ms + 'ms')"></span>
                                    </div>
                                </template>
                                <template x-if="m.error">
                                    <button type="button" x-on:click="retry()" class="mt-1 px-1 text-2xs font-semibold text-danger-600 hover:underline">Retry</button>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Composer --}}
            <form class="flex items-center gap-2 border-t border-slate-100 px-2.5 py-2" x-on:submit.prevent="send()" data-no-loading>
                <input x-ref="input" type="text" x-model="text" maxlength="4000" placeholder="Message…" aria-label="Test message"
                    class="h-9 min-w-0 flex-1 rounded-full border-0 bg-slate-100 px-4 text-[13.5px] text-ink placeholder:text-slate-400 focus:ring-2 focus:ring-brand-200 focus:outline-none">
                <button type="submit" x-bind:disabled="!text.trim() || sending" aria-label="Send"
                    x-bind:class="{ 'text-messenger': platform === 'facebook', 'text-instagram': platform === 'instagram' }"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-full transition hover:bg-slate-100 disabled:opacity-40">
                    <x-ui.icon name="send" class="size-5" />
                </button>
            </form>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3">
        <label class="flex min-w-0 items-center gap-2 text-xs text-ink-muted">
            <span class="shrink-0">Test as</span>
            <input type="text" x-model="name" maxlength="120" class="ui-input h-7 w-36 text-xs" aria-label="Customer name for variables">
        </label>
        <div class="flex items-center gap-1">
            <x-ui.button size="xs" variant="ghost" icon="play" x-on:click="getStarted()">Simulate Get Started</x-ui.button>
            <x-ui.button size="xs" variant="ghost" icon="refresh" x-on:click="reset()">Reset</x-ui.button>
        </div>
    </div>
</div>
