{{-- Create / edit a keyword rule (modal). State lives in the parent automations() component. --}}
<x-ui.modal name="rule-editor" size="lg" icon="zap" title="Keyword rule" description="When a customer's message matches, this reply is sent instead of the AI.">
    <form id="rule-editor-form" class="space-y-5" x-on:submit.prevent="saveRule()" data-no-loading novalidate>
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,0.7fr)]">
            <div class="grid gap-1.5">
                <label for="rule-name" class="ui-label">Rule name <span class="text-danger-500">*</span></label>
                <input id="rule-name" type="text" x-model="form.name" maxlength="120" class="ui-input h-9" placeholder="Pricing question"
                    x-bind:aria-invalid="err('name') ? 'true' : null">
                <p class="ui-error" x-show="err('name')" x-text="err('name')"></p>
            </div>
            <div class="grid gap-1.5">
                <label for="rule-scope" class="ui-label">Applies to</label>
                <select id="rule-scope" x-model.number="form.meta_account_id" class="ui-input h-9"
                    x-init="$nextTick(() => $el.value = form.meta_account_id ?? '')" x-effect="$el.value = form.meta_account_id ?? ''">
                    <option value="">All pages</option>
                    @foreach ($pages as $p)
                        <option value="{{ $p->id }}">{{ $p->page_name ?: 'Page #'.$p->id }} ({{ $p->platform->value === 'instagram' ? 'Instagram' : 'Messenger' }})</option>
                    @endforeach
                </select>
                <p class="ui-error" x-show="err('meta_account_id')" x-text="err('meta_account_id')"></p>
            </div>
        </div>

        {{-- Match type --}}
        <fieldset class="grid gap-2">
            <legend class="ui-label mb-1.5">Match when the message…</legend>
            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 sm:grid-cols-4">
                @foreach ($matchTypes as $type => $meta)
                    <label class="cursor-pointer rounded-lg px-2 py-1.5 text-center text-[13px] font-medium transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-500"
                        x-bind:class="{ 'bg-white text-ink shadow-xs': form.match_type === @js($type), 'text-slate-600 hover:text-ink': form.match_type !== @js($type) }">
                        <input type="radio" name="match_type" value="{{ $type }}" x-model="form.match_type" class="sr-only">{{ $meta['label'] }}
                    </label>
                @endforeach
            </div>
            <p class="ui-hint" x-text="(matchTypes[form.match_type] || {}).help"></p>
        </fieldset>

        {{-- Keywords --}}
        <div class="grid gap-1.5">
            <label for="rule-keyword" class="ui-label" x-text="form.match_type === 'regex' ? 'Patterns' : 'Keywords'">Keywords</label>
            <div class="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border bg-white px-2 py-1.5 shadow-xs focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/15"
                x-bind:class="{ 'border-danger-300': err('keywords') || hasRegexErrors, 'border-line-strong': !(err('keywords') || hasRegexErrors) }"
                x-on:click="$refs.keywordInput.focus()">
                <template x-for="(k, i) in form.keywords" :key="k + i">
                    <span class="inline-flex max-w-full items-center gap-1 rounded-md py-0.5 pr-1 pl-2 text-[13px] font-medium ring-1 ring-inset"
                        x-bind:class="{ 'bg-danger-50 text-danger-700 ring-danger-200': regexErrors[i], 'bg-violet-50 text-violet-700 ring-violet-100': !regexErrors[i], 'font-mono': form.match_type === 'regex' }"
                        x-bind:title="regexErrors[i] || ''">
                        <span class="truncate" x-text="k"></span>
                        <button type="button" class="rounded p-0.5 hover:bg-black/5" x-on:click.stop="removeKeyword(i)" x-bind:aria-label="'Remove ' + k"><x-ui.icon name="x" class="size-3" /></button>
                    </span>
                </template>
                <input id="rule-keyword" x-ref="keywordInput" type="text" x-model="keywordDraft" maxlength="200"
                    x-on:keydown="onKeywordKey($event)" x-on:paste="onKeywordPaste($event)" x-on:blur="keywordDraft.trim() && addKeywords()"
                    class="h-7 min-w-32 flex-1 border-0 bg-transparent px-1 text-sm text-ink placeholder:text-slate-400 focus:ring-0 focus:outline-none"
                    x-bind:class="{ 'font-mono': form.match_type === 'regex' }"
                    x-bind:placeholder="form.keywords.length ? 'Add more…' : (form.match_type === 'regex' ? '^(hi|hello)\\b — Enter to add' : 'price, cost, how much — Enter or comma to add')">
            </div>
            <template x-for="(e, i) in regexErrors" :key="i">
                <p class="ui-error" x-show="e" x-text="form.keywords[i] + ': ' + e"></p>
            </template>
            <p class="ui-error" x-show="err('keywords')" x-text="err('keywords')"></p>
            <p class="ui-hint" x-show="!err('keywords')">Case-insensitive. The rule fires if <em>any</em> keyword matches. Ice breaker and button payloads are matched too.</p>
        </div>

        {{-- Reply --}}
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
            <div class="grid content-start gap-1.5">
                <div class="flex items-center justify-between">
                    <label for="rule-reply" class="ui-label">Reply <span class="text-danger-500">*</span></label>
                    <span class="text-2xs text-slate-400 tabular-nums" x-text="(form.reply_text || '').length + ' / 2,000'"></span>
                </div>
                <textarea id="rule-reply" x-ref="ruleReply" x-model="form.reply_text" rows="5" maxlength="2000" class="ui-input min-h-28 resize-y leading-relaxed"
                    x-bind:aria-invalid="err('reply_text') ? 'true' : null" placeholder="Hi {first_name}! Our prices start at $20."></textarea>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-ink-muted">Insert:</span>
                    @foreach ($variables as $var => $label)
                        <button type="button" x-on:click="insertVar('rule-reply', @js($var))" title="{{ $label }}"
                            class="rounded-md border border-brand-100 bg-brand-50 px-1.5 py-0.5 font-mono text-2xs font-medium text-brand-700 transition hover:bg-brand-100">{{ $var }}</button>
                    @endforeach
                </div>
                <p class="ui-error" x-show="err('reply_text')" x-text="err('reply_text')"></p>
            </div>
            <div class="flex flex-col">
                <p class="ui-label mb-1.5">Preview <span class="font-normal text-ink-muted">(as Sara Khan)</span></p>
                <div class="flex flex-1 flex-col justify-end gap-2 rounded-xl bg-slate-50 p-3 ring-1 ring-line ring-inset">
                    <p class="ml-auto max-w-[85%] rounded-2xl rounded-br-md bg-messenger px-3 py-1.5 text-[13px] text-white"
                        x-text="form.keywords[0] && form.match_type !== 'regex' ? form.keywords[0] + '?' : 'Customer message'"></p>
                    <p class="max-w-[90%] rounded-2xl rounded-bl-md bg-white px-3 py-1.5 text-[13px] break-words whitespace-pre-line text-ink shadow-xs ring-1 ring-line"
                        x-bind:class="{ 'opacity-60': previewLoading }" x-show="preview" x-text="preview"></p>
                    <p class="text-xs text-slate-400 italic" x-show="!preview">Your reply appears here…</p>
                </div>
            </div>
        </div>

        {{-- Options --}}
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3">
            <x-ui.toggle x-model="form.active" size="sm" label="Active" />
            <button type="button" class="text-xs font-medium text-slate-500 hover:text-ink" x-on:click="showAdvanced = !showAdvanced" x-bind:aria-expanded="showAdvanced">
                <span x-text="showAdvanced ? 'Hide advanced' : 'Advanced'"></span>
            </button>
            <div x-show="showAdvanced" x-collapse class="w-full">
                <div class="grid gap-1.5 pt-2 sm:max-w-56">
                    <label for="rule-priority" class="ui-label">Priority</label>
                    <input id="rule-priority" type="number" min="-1000" max="1000" step="1" x-model.number="form.priority" class="ui-input h-9">
                    <p class="ui-hint">Higher runs first. Reordering the list sets this for you.</p>
                    <p class="ui-error" x-show="err('priority')" x-text="err('priority')"></p>
                </div>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <x-ui.button type="submit" form="rule-editor-form" variant="primary" icon="check" x-bind:disabled="saving">
            <span x-text="saving ? 'Saving…' : (form.id ? 'Save rule' : 'Create rule')">Save rule</span>
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>
