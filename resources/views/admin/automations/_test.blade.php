{{-- "Test a message": which rule would answer on the selected page (AutomationMatcher::matchText). --}}
<section class="ui-card">
    <header class="flex items-start gap-3 border-b border-line px-5 py-4">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-ui.icon name="search" class="size-[18px]" /></span>
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-ink">Test a message</h2>
            <p class="mt-0.5 text-[13px] text-ink-muted">Type what a customer might send to {{ $isPage ? $pageName : 'any page (global rules only)' }}.</p>
        </div>
    </header>
    <form class="space-y-3 p-5" x-on:submit.prevent="runTest()" data-no-loading>
        <div class="flex gap-2">
            <input type="text" x-model="test.text" maxlength="2000" class="ui-input h-9 min-w-0 flex-1" placeholder="How much is delivery?" aria-label="Customer message">
            <x-ui.button type="submit" variant="primary" icon="play" x-bind:disabled="!test.text.trim() || test.loading" aria-label="Test">Test</x-ui.button>
        </div>
        <x-ui.toggle x-model="test.firstContact" size="sm" label="First message from this customer" description="Lets the welcome message fire when no keyword matches." />

        <div x-show="test.result" x-cloak x-transition class="rounded-xl p-3.5 ring-1 ring-inset"
            x-bind:class="{ 'bg-violet-50/70 ring-violet-100': test.result?.matched, 'bg-brand-50/60 ring-brand-100': test.result && !test.result.matched }">
            <p class="flex items-center gap-1.5 text-[13px] font-semibold" x-bind:class="{ 'text-violet-800': test.result?.matched, 'text-brand-800': !test.result?.matched }">
                <span x-show="test.result?.matched"><x-ui.icon name="zap" class="size-4" /></span>
                <span x-show="!test.result?.matched"><x-ui.icon name="sparkles" class="size-4" /></span>
                <span x-text="test.result?.matched ? test.result.rule.name : 'AI would answer'"></span>
                <template x-if="test.result?.matched">
                    <span class="rounded-full bg-white/80 px-1.5 py-px text-2xs font-semibold text-violet-700 ring-1 ring-violet-100" x-text="test.result.rule.scope_label"></span>
                </template>
            </p>
            <p class="mt-1 text-xs text-slate-600" x-text="test.result?.message"></p>
            <template x-if="test.result?.matched">
                <div class="mt-3">
                    <p class="inline-block max-w-full rounded-2xl rounded-tl-md bg-white px-3.5 py-2 text-[13.5px] break-words whitespace-pre-line text-ink shadow-xs ring-1 ring-line" x-text="test.result.reply"></p>
                    <div class="mt-2">
                        <button type="button" class="text-xs font-medium text-violet-700 hover:underline"
                            x-on:click="openEdit(rules.find(r => r.id === test.result.rule.id) || test.result.rule)" x-show="test.result.rule.trigger === 'keyword'">Edit this rule</button>
                    </div>
                </div>
            </template>
            <template x-if="test.result && !test.result.matched && test.result.reason === 'ai'">
                <a href="{{ route('admin.bot-settings.edit') }}#tab-knowledge" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline">Improve the AI's knowledge in Bot Studio <x-ui.icon name="arrow-right" class="size-3" /></a>
            </template>
        </div>
    </form>
</section>
