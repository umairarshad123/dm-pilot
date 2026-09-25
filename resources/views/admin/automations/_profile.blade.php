{{-- Welcome screen (greeting + Get Started, Messenger only) and ice breakers, synced with Meta. Page scope only. --}}
<section class="ui-card">
    <header class="flex items-start gap-3 border-b border-line px-5 py-4">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-600"><x-ui.icon name="smile" class="size-[18px]" /></span>
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-ink">Welcome screen & ice breakers</h2>
            <p class="mt-0.5 text-[13px] text-ink-muted">What people see before they send their first message. Stored by Meta, per page.</p>
        </div>
    </header>

    @if (! $isPage)
        <div class="p-5">
            <x-ui.empty-state compact icon="layers" title="Pick a page first" description="Greeting text, the Get Started button and ice breakers are set on each Facebook Page or Instagram account separately.">
                <x-ui.button size="sm" icon="chevrons-up-down" x-on:click="$dispatch('open-page-switcher')">Choose a page</x-ui.button>
            </x-ui.empty-state>
        </div>
    @else
        @php
            $isInstagram = $account->platform->value === 'instagram';
            $profileConfig = [
                'routes' => [
                    'show' => route('admin.automations.profile.show', $account),
                    'update' => route('admin.automations.profile.update', $account),
                ],
                'instagram' => $isInstagram,
                'limits' => $limits,
                'profile' => $profile,
            ];
        @endphp
        <div class="space-y-5 p-5" x-data="messengerProfile(@js($profileConfig))">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-ink-muted">
                <span class="inline-flex items-center gap-1.5">
                    <x-ui.icon name="refresh" class="size-3.5" />
                    <span x-show="updatedAt" x-text="'Last saved to Meta ' + timeAgo(updatedAt)" x-bind:title="updatedAt"></span>
                    <span x-show="!updatedAt">Never saved from here</span>
                </span>
                <button type="button" class="inline-flex items-center gap-1 font-medium text-brand-700 hover:underline disabled:opacity-50" x-on:click="load()" x-bind:disabled="loading">
                    <x-ui.icon name="download" class="size-3.5" /><span x-text="loading ? 'Loading…' : 'Load current from Meta'"></span>
                </button>
            </div>

            @if ($isInstagram)
                <x-ui.alert tone="info" :icon="false">Instagram supports ice breakers only. Greeting text and the Get Started button are Messenger features.</x-ui.alert>
            @else
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="profile-greeting" class="ui-label">Greeting text</label>
                        <span class="text-2xs tabular-nums" x-bind:class="{ 'text-danger-600': greeting.length > limits.greeting, 'text-slate-400': greeting.length <= limits.greeting }"
                            x-text="greeting.length + ' / ' + limits.greeting"></span>
                    </div>
                    <textarea id="profile-greeting" x-model="greeting" rows="2" x-bind:maxlength="limits.greeting" class="ui-input min-h-16 resize-y"
                        placeholder="Hi @{{user_first_name}}! Ask us anything about our products."></textarea>
                    <p class="ui-hint">Shown on the Messenger welcome screen. Meta replaces <span class="ui-code">@{{user_first_name}}</span> with the person's name.</p>
                </div>

                <x-ui.toggle x-model="getStarted" label="Get Started button" description="Tapping it sends GET_STARTED, which triggers your welcome message." />
            @endif

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <p class="ui-label">Ice breakers</p>
                    <span class="text-2xs text-slate-400 tabular-nums" x-text="iceBreakers.length + ' / ' + limits.ice_breakers"></span>
                </div>
                <p class="ui-hint -mt-1">Up to {{ $limits['ice_breakers'] }} tappable questions. Each sends a payload your keyword rules can answer.</p>
                <ol class="space-y-2">
                    <template x-for="(item, i) in iceBreakers" :key="i">
                        <li class="rounded-xl border border-line bg-white p-2.5 shadow-xs">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-2xs font-semibold text-slate-600" x-text="i + 1"></span>
                                <input type="text" x-model="item.question" x-bind:maxlength="limits.question" data-ice-question
                                    class="ui-input h-8 min-w-0 flex-1 text-[13px]" placeholder="What are your opening hours?" x-bind:aria-label="'Ice breaker ' + (i + 1)">
                                <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-25" x-on:click="moveIceBreaker(i, -1)" x-bind:disabled="i === 0" aria-label="Move up"><x-ui.icon name="chevron-up" class="size-3.5" /></button>
                                <button type="button" class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-25" x-on:click="moveIceBreaker(i, 1)" x-bind:disabled="i === iceBreakers.length - 1" aria-label="Move down"><x-ui.icon name="chevron-down" class="size-3.5" /></button>
                                <button type="button" class="rounded p-1 text-slate-400 hover:bg-danger-50 hover:text-danger-600" x-on:click="removeIceBreaker(i)" aria-label="Remove ice breaker"><x-ui.icon name="x" class="size-3.5" /></button>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 pl-6.5 text-2xs text-ink-muted">
                                <span>Payload</span>
                                <input type="text" x-model="item.payload" maxlength="100" class="h-6 w-36 rounded-md border border-line bg-slate-50 px-1.5 font-mono text-2xs text-slate-700 focus:border-brand-300 focus:bg-white focus:outline-none"
                                    x-bind:placeholder="'ICE_BREAKER_' + (i + 1)" x-bind:aria-label="'Payload for ice breaker ' + (i + 1)">
                                <button type="button" class="font-medium text-violet-700 hover:underline" x-show="item.question.trim()"
                                    x-on:click="$dispatch('prefill-rule', { name: 'Ice breaker: ' + item.question.slice(0, 80), match_type: 'exact', keywords: [payloadFor(item, i)], reply_text: '' })">+ Answer with a rule</button>
                                <span class="tabular-nums" x-show="item.question.length > limits.question - 15" x-text="item.question.length + '/' + limits.question"></span>
                            </div>
                        </li>
                    </template>
                </ol>
                <x-ui.button size="sm" variant="ghost" icon="plus" x-show="iceBreakers.length < limits.ice_breakers" x-on:click="addIceBreaker()">Add ice breaker</x-ui.button>
            </div>

            <div x-show="conflict" x-cloak class="flex items-start gap-2 rounded-lg bg-warning-50 p-3 text-xs text-warning-700 ring-1 ring-warning-100 ring-inset">
                <x-ui.icon name="alert-triangle" class="mt-px size-3.5 shrink-0" />
                <span>Meta shows ice breakers <strong>instead of</strong> the Get Started button when both are set.</span>
            </div>
            <template x-for="w in warnings" :key="w">
                <p class="flex items-start gap-2 rounded-lg bg-warning-50 p-3 text-xs text-warning-700"><x-ui.icon name="alert-triangle" class="mt-px size-3.5 shrink-0" /><span x-text="w"></span></p>
            </template>
            <p class="ui-error" x-show="error" x-text="error"></p>

            <div class="flex justify-end border-t border-line pt-4">
                <x-ui.button variant="primary" icon="upload" x-on:click="save()" x-bind:disabled="saving">
                    <span x-text="saving ? 'Saving to Meta…' : 'Save to Meta'">Save to Meta</span>
                </x-ui.button>
            </div>
        </div>
    @endif
</section>
