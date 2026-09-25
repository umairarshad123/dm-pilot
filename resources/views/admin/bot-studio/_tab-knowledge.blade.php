{{-- Knowledge: business info, FAQ list, offers & pricing. --}}
<div class="space-y-6">
    <x-ui.card title="Business info" description="Hours, location, services, delivery, policies. The AI only answers from what you write here." icon="book">
        <div x-data="bsInherit(@js($inherited['business_info']))" class="space-y-2">
            @include('admin.bot-studio._inherit-head', ['label' => 'About the business', 'for' => 'business_info'])
            <x-ui.textarea name="business_info" rows="8" autosize :counter="50000" maxlength="50000" :value="$setting->business_info" data-inherit-input
                :placeholder="$ph($inherited['business_info']) ?: 'We are a family bakery in Lahore. Open Mon–Sat 9am–8pm. Free delivery over Rs 3,000 within 5 km. Custom cakes need 48 hours notice…'" />
            @include('admin.bot-studio._inherit-foot', ['name' => 'business_info'])
        </div>
    </x-ui.card>

    {{-- FAQs --}}
    <div class="ui-card" x-data="{ importing: false }">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
            <div class="flex min-w-0 items-start gap-3">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-ui.icon name="message" class="size-[18px]" /></span>
                <div class="min-w-0">
                    <h3 class="flex items-center gap-2 text-[15px] font-semibold text-ink">
                        FAQs
                        <span class="rounded-full bg-slate-100 px-1.5 text-2xs font-semibold text-slate-600 tabular-nums" x-text="faqs.length">{{ count($faqValues) }}</span>
                    </h3>
                    <p class="mt-0.5 text-[13px] text-ink-muted">Common questions with the exact answer you want. {{ $isPage ? 'A page list replaces the global list.' : '' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button size="sm" variant="ghost" icon="upload" x-on:click="importing = !importing" x-bind:aria-expanded="importing">Paste Q:/A: text</x-ui.button>
                <x-ui.button size="sm" variant="ghost" x-show="faqs.length > 1" x-cloak x-on:click="toggleAllFaqs(!faqs.every(f => f.open))">
                    <span x-text="faqs.every(f => f.open) ? 'Collapse all' : 'Expand all'">Expand all</span>
                </x-ui.button>
                <x-ui.button size="sm" variant="soft" icon="plus" x-on:click="addFaq()">Add FAQ</x-ui.button>
            </div>
        </div>

        <div class="space-y-3 p-5">
            {{-- Import --}}
            <div x-show="importing" x-collapse x-cloak>
                <div class="space-y-2 rounded-xl border border-dashed border-brand-200 bg-brand-50/40 p-4">
                    <label for="faq-import" class="ui-label">Paste FAQs</label>
                    <p class="ui-hint">A line starting with <span class="ui-code">Q:</span>, then a line starting with <span class="ui-code">A:</span>. Leave a blank line between pairs. They are added to the end of the list.</p>
                    <textarea id="faq-import" x-model="importText" rows="6" class="ui-input font-mono text-[13px]"
                        placeholder="Q: Do you deliver?&#10;A: Yes, free within the city.&#10;&#10;Q: Are you open on Sunday?&#10;A: No, Monday to Saturday only."></textarea>
                    <div class="flex justify-end gap-2">
                        <x-ui.button size="sm" variant="ghost" x-on:click="importing = false; importText = ''">Cancel</x-ui.button>
                        <x-ui.button size="sm" variant="primary" icon="upload" x-on:click="importFaqs() && (importing = false)">Import</x-ui.button>
                    </div>
                </div>
            </div>

            {{-- Inherited list (page with no own FAQs) --}}
            @if ($isPage)
                <div x-show="faqs.length === 0" @if (count($faqValues)) x-cloak @endif class="rounded-xl border border-line bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="flex items-center gap-2 text-[13px] text-slate-700">
                            <x-ui.icon name="link" class="size-4 text-slate-400" />
                            @if (count($inheritedFaqs))
                                Inheriting {{ count($inheritedFaqs) }} {{ \Illuminate\Support\Str::plural('FAQ', count($inheritedFaqs)) }} from the global defaults.
                            @else
                                No FAQs here or in the global defaults yet.
                            @endif
                        </p>
                        @if (count($inheritedFaqs))
                            <x-ui.button size="xs" icon="copy" x-on:click="copyInheritedFaqs()">Copy to this page and edit</x-ui.button>
                        @endif
                    </div>
                    @if (count($inheritedFaqs))
                        <ul class="mt-3 space-y-1.5 border-t border-line pt-3">
                            @foreach (array_slice($inheritedFaqs, 0, 5) as $faq)
                                <li class="truncate text-[13px] text-ink-muted"><span class="font-medium text-slate-700">Q:</span> {{ $faq['question'] }}</li>
                            @endforeach
                            @if (count($inheritedFaqs) > 5)
                                <li class="text-xs text-slate-400">+ {{ count($inheritedFaqs) - 5 }} more</li>
                            @endif
                        </ul>
                    @endif
                </div>
            @else
                <div x-show="faqs.length === 0" @if (count($faqValues)) x-cloak @endif>
                    <x-ui.empty-state compact icon="message" title="No FAQs yet" description="Add the questions customers ask most. Exact answers beat guesses.">
                        <x-ui.button size="sm" variant="primary" icon="plus" x-on:click="addFaq()">Add your first FAQ</x-ui.button>
                    </x-ui.empty-state>
                </div>
            @endif

            {{-- The list --}}
            <ol class="space-y-2.5" aria-label="FAQ list">
                <template x-for="(faq, i) in faqs" :key="faq.uid">
                    <li class="rounded-xl border bg-white shadow-xs transition"
                        x-bind:class="{ 'border-danger-300 ring-2 ring-danger-100': faqError(i, 'question') || faqError(i, 'answer'), 'border-line': !(faqError(i, 'question') || faqError(i, 'answer')), 'opacity-50': dragIndex === i }"
                        x-on:dragover.prevent x-on:drop.prevent="dropFaq(i)">
                        <div class="flex items-center gap-2 px-2 py-2 sm:px-3">
                            <span class="hidden cursor-grab items-center rounded-md p-1 text-slate-300 hover:text-slate-500 sm:inline-flex" draggable="true"
                                x-on:dragstart="dragIndex = i; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="dragIndex = null" title="Drag to reorder" aria-hidden="true">
                                <x-ui.icon name="dots-vertical" class="size-4" />
                            </span>
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg px-1 py-1 text-left" x-on:click="faq.open = !faq.open" x-bind:aria-expanded="faq.open">
                                <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-slate-100 text-2xs font-semibold text-slate-600 tabular-nums" x-text="i + 1"></span>
                                <span class="min-w-0 flex-1 truncate text-[13.5px] font-medium" x-bind:class="{ 'text-ink': faq.question, 'text-slate-400 italic': !faq.question }" x-text="faq.question || 'New question'"></span>
                                <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="{ 'rotate-180': faq.open }" />
                            </button>
                            <div class="flex shrink-0 items-center">
                                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-30" x-on:click="moveFaq(i, -1)" x-bind:disabled="i === 0" aria-label="Move up"><x-ui.icon name="chevron-up" class="size-4" /></button>
                                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-ink disabled:opacity-30" x-on:click="moveFaq(i, 1)" x-bind:disabled="i === faqs.length - 1" aria-label="Move down"><x-ui.icon name="chevron-down" class="size-4" /></button>
                                <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-danger-50 hover:text-danger-600" x-on:click="removeFaq(i)" aria-label="Remove FAQ"><x-ui.icon name="trash" class="size-4" /></button>
                            </div>
                        </div>
                        <div x-show="faq.open" x-collapse>
                            <div class="grid gap-3 border-t border-line px-3 py-3 sm:px-4">
                                <div class="grid gap-1.5">
                                    <label class="ui-label" x-bind:for="'faq-q-' + faq.uid">Question</label>
                                    <input type="text" class="ui-input h-9" maxlength="1000" data-faq-question
                                        x-bind:id="'faq-q-' + faq.uid" x-bind:name="'faqs[' + i + '][question]'" x-model="faq.question"
                                        x-bind:aria-invalid="faqError(i, 'question') ? 'true' : null" placeholder="Do you deliver?">
                                    <p class="ui-error" x-show="faqError(i, 'question')" x-text="faqError(i, 'question')"></p>
                                </div>
                                <div class="grid gap-1.5">
                                    <label class="ui-label" x-bind:for="'faq-a-' + faq.uid">Answer</label>
                                    <textarea class="ui-input min-h-20 resize-y leading-relaxed" rows="3" maxlength="5000"
                                        x-bind:id="'faq-a-' + faq.uid" x-bind:name="'faqs[' + i + '][answer]'" x-model="faq.answer"
                                        x-bind:aria-invalid="faqError(i, 'answer') ? 'true' : null" placeholder="Yes! Free delivery within the city on orders over Rs 3,000."></textarea>
                                    <p class="ui-error" x-show="faqError(i, 'answer')" x-text="faqError(i, 'answer')"></p>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ol>

            @error('faqs')
                <p class="ui-error flex items-center gap-1"><x-ui.icon name="alert-circle" class="size-3.5" />{{ $message }}</p>
            @enderror

            <div x-show="faqs.length > 0" x-cloak class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <x-ui.button size="sm" variant="ghost" icon="plus" x-on:click="addFaq()">Add another</x-ui.button>
                @if ($isPage)
                    <button type="button" class="text-xs font-medium text-slate-500 hover:text-ink" x-on:click="faqs = []; dirty()">Clear list to inherit global FAQs</button>
                @endif
            </div>
        </div>
    </div>

    <x-ui.card title="Offers & pricing" description="Current prices, packages, promotions and discount codes. Keep it up to date: the AI quotes it word for word." icon="tag">
        <div x-data="bsInherit(@js($inherited['offers']))" class="space-y-2">
            @include('admin.bot-studio._inherit-head', ['label' => 'Offers', 'for' => 'offers'])
            <x-ui.textarea name="offers" rows="6" autosize :counter="20000" maxlength="20000" :value="$setting->offers" data-inherit-input
                :placeholder="$ph($inherited['offers']) ?: 'Classic cake 1 kg: Rs 2,500. Custom cakes from Rs 4,000. 10% off first order with code WELCOME10.'" />
            @include('admin.bot-studio._inherit-foot', ['name' => 'offers'])
        </div>
    </x-ui.card>
</div>
