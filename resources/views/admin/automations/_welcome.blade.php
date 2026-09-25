{{-- Welcome message (trigger = welcome) for the current scope. --}}
@php
    $welcomeConfig = [
        'routes' => $routes,
        'accountId' => $account?->id,
        'rule' => $welcome,
        'inherited' => $inheritedWelcome,
    ];
@endphp
<section class="ui-card" x-data="automationWelcome(@js($welcomeConfig))">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
        <div class="flex min-w-0 items-start gap-3">
            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-600"><x-ui.icon name="hand" class="size-[18px]" /></span>
            <div class="min-w-0">
                <h2 class="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-ink">
                    Welcome message
                    <template x-if="editing && rule">
                        <span class="rounded-full px-2 py-0.5 text-2xs font-semibold" x-bind:class="{ 'bg-success-50 text-success-700': rule.active, 'bg-slate-100 text-slate-600': !rule.active }" x-text="rule.active ? 'Live' : 'Off'"></span>
                    </template>
                    <template x-if="!editing && inherited">
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-2xs font-semibold text-slate-600">From All pages</span>
                    </template>
                </h2>
                <p class="mt-0.5 text-[13px] text-ink-muted">Sent the first time someone messages {{ $isPage ? 'this page' : 'you' }} or taps Get Started, unless a keyword rule matches first.</p>
            </div>
        </div>
        <div x-show="editing" x-cloak>
            <x-ui.toggle x-model="active" size="sm" label="Enabled" />
        </div>
    </header>

    {{-- Page without its own welcome: show the inherited one --}}
    <div x-show="!editing" x-cloak class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
        <div class="min-w-0 flex-1">
            <template x-if="inherited">
                <div>
                    <p class="text-xs font-medium text-ink-muted">This page uses the All pages welcome<span x-show="!inherited.active"> (currently off)</span>:</p>
                    <p class="mt-2 inline-block max-w-full rounded-2xl rounded-tl-md bg-slate-100 px-3.5 py-2 text-[13.5px] break-words whitespace-pre-line text-ink" x-text="inherited.reply_text"></p>
                </div>
            </template>
            <template x-if="!inherited">
                <p class="text-[13px] text-ink-muted">No welcome message yet. New customers get an AI reply to their first message.</p>
            </template>
        </div>
        <x-ui.button size="sm" variant="soft" icon="edit" x-on:click="startOverride()">
            <span x-text="inherited ? 'Customize for this page' : 'Create welcome message'"></span>
        </x-ui.button>
    </div>

    <div x-show="editing" @if (! $welcome && $isPage) x-cloak @endif class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)]">
        <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
                <label for="welcome-text" class="ui-label">Message</label>
                <span class="text-2xs text-slate-400 tabular-nums" x-text="text.length + ' / 2,000'"></span>
            </div>
            <textarea id="welcome-text" x-ref="welcomeText" x-model="text" rows="4" maxlength="2000" class="ui-input min-h-24 resize-y leading-relaxed"
                x-bind:aria-invalid="error ? 'true' : null" placeholder="Hi {first_name}! Thanks for reaching out to {page_name}. How can we help today?"></textarea>
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-ink-muted">Insert:</span>
                @foreach ($variables as $var => $label)
                    <button type="button" x-on:click="insertVar(@js($var))" title="{{ $label }}"
                        class="rounded-md border border-brand-100 bg-brand-50 px-1.5 py-0.5 font-mono text-2xs font-medium text-brand-700 transition hover:bg-brand-100">{{ $var }}</button>
                @endforeach
            </div>
            <p class="ui-error" x-show="error" x-text="error"></p>
        </div>
        <div class="flex flex-col">
            <p class="ui-label mb-2">Preview <span class="font-normal text-ink-muted">(as Sara Khan)</span></p>
            <div class="flex flex-1 flex-col justify-end rounded-xl bg-slate-50 p-3 ring-1 ring-line ring-inset">
                <div class="flex items-end gap-2">
                    <x-ui.avatar :name="$pageName ?? 'Your Page'" size="xs" />
                    <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2 text-[13.5px] break-words whitespace-pre-line text-ink shadow-xs ring-1 ring-line"
                        x-show="preview" x-text="preview"></p>
                    <p class="text-xs text-slate-400 italic" x-show="!preview">Start typing to preview…</p>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 lg:col-span-2">
            @if ($isPage)
                <x-ui.button size="sm" variant="ghost" x-show="rule && inherited" x-cloak x-on:click="removeOverride()">Use All pages welcome</x-ui.button>
                <x-ui.button size="sm" variant="ghost" x-show="!rule" x-cloak x-on:click="editing = false">Cancel</x-ui.button>
            @endif
            <span class="text-xs text-warning-600" x-show="dirty" x-cloak>Unsaved changes</span>
            <x-ui.button size="sm" variant="primary" icon="check" x-on:click="save()" x-bind:disabled="saving || !text.trim()">
                <span x-text="saving ? 'Saving…' : 'Save welcome message'">Save welcome message</span>
            </x-ui.button>
        </div>
    </div>
</section>
