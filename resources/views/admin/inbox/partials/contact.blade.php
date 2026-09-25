{{-- Right pane: contact details (inline column on wide screens, slide-over sheet below 1380px). Alpine: liveChat. --}}
@php
    // Full class strings per LeadStage::color() (never built dynamically).
    $stageStyles = [
        'gray' => ['dot' => 'bg-slate-400', 'chip' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        'blue' => ['dot' => 'bg-sky-500', 'chip' => 'bg-sky-50 text-sky-700 ring-sky-200'],
        'amber' => ['dot' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-800 ring-amber-200'],
        'green' => ['dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'red' => ['dot' => 'bg-rose-500', 'chip' => 'bg-rose-50 text-rose-700 ring-rose-200'],
    ];
    $stageHints = [
        'new' => 'Just started talking',
        'contacted' => 'You reached out',
        'qualified' => 'Shared contact details or showed intent',
        'customer' => 'Bought from you',
        'lost' => 'Not interested',
    ];
@endphp

{{-- Backdrop for the sheet --}}
<div x-show="! wide && sheetOpen && conv" x-cloak x-on:click="sheetOpen = false" x-transition.opacity
    class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-[1px]" aria-hidden="true"></div>

<aside x-show="panelVisible" x-cloak aria-label="Contact details"
    x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-6 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-x-6 opacity-0"
    class="flex min-h-0 flex-col bg-surface"
    x-bind:class="{
        'fixed inset-y-0 right-0 z-50 w-full max-w-sm shadow-pop': ! wide,
        'relative w-[320px] shrink-0 border-l border-line': wide,
    }">
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-line px-4">
        <p class="text-[13px] font-semibold text-ink">Contact</p>
        <button type="button" x-on:click="toggleContact()" class="-mr-1.5 inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close contact details">
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>

    <div class="ui-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain">
        {{-- Identity --}}
        <div class="flex flex-col items-center px-5 pt-6 pb-5 text-center">
            @include('admin.inbox.partials.avatar', ['obj' => 'conv', 'size' => 'size-20 text-xl', 'badge' => 'sm'])

            <div class="mt-3 w-full">
                <template x-if="! editingName">
                    <button type="button" x-on:click="startEditName()" class="group mx-auto inline-flex max-w-full items-center gap-1.5 rounded-lg px-2 py-0.5 hover:bg-slate-100" title="Edit name">
                        <span class="truncate text-base font-semibold text-ink" x-text="conv?.name"></span>
                        <x-ui.icon name="edit" class="size-3.5 shrink-0 text-slate-300 group-hover:text-slate-500" />
                    </button>
                </template>
                <template x-if="editingName">
                    <input type="text" x-ref="nameInput" x-model="form.customer_name" maxlength="255" placeholder="Customer name"
                        x-on:keydown.enter.prevent="saveName()" x-on:keydown.escape.prevent.stop="editingName = false" x-on:blur="saveName()"
                        x-init="$nextTick(() => $el.select())"
                        class="ui-input h-9 text-center font-semibold">
                </template>
                <p x-show="errors.customer_name" x-text="errors.customer_name" class="ui-error mt-1"></p>
            </div>
            <p x-show="conv?.username" class="mt-0.5 text-[13px] text-ink-muted" x-text="'@' + (conv?.username ?? '')"></p>
            <div class="mt-2.5 flex flex-wrap items-center justify-center gap-1.5">
                <span x-show="conv?.platform === 'facebook'"><x-ui.channel-badge platform="facebook" size="sm" /></span>
                <span x-show="conv?.platform === 'instagram'"><x-ui.channel-badge platform="instagram" size="sm" /></span>
                <span x-show="conv?.page_name" class="inline-flex max-w-44 items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-2xs font-medium text-slate-600">
                    <x-ui.icon name="layers" class="size-3 text-slate-400" /><span class="truncate" x-text="conv?.page_name"></span>
                </span>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <x-ui.button size="xs" variant="secondary" icon="refresh" x-on:click="refreshProfile()" title="Fetch the latest name and picture from Meta">Refresh</x-ui.button>
                <x-ui.button size="xs" variant="secondary" icon="copy" x-on:click="copyId()" title="Copy the PSID / IGSID">Copy ID</x-ui.button>
                <x-ui.button size="xs" variant="secondary" icon="users" :href="route('admin.contacts.index')" x-bind:href="contactsUrl()">Contacts</x-ui.button>
            </div>
        </div>

        <div class="space-y-6 border-t border-line px-5 py-5">
            {{-- Lead stage --}}
            <div>
                <p class="mb-2 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Lead stage</p>
                <x-ui.dropdown align="left" width="w-full" class="w-full">
                    <x-slot:trigger>
                        <button type="button" class="flex h-9 w-full items-center gap-2 rounded-lg border border-line-strong bg-white px-3 text-left text-[13px] font-medium text-ink shadow-xs transition hover:border-slate-300">
                            @foreach ($stages as $stage)
                                <span x-show="(conv?.lead_stage ?? 'new') === '{{ $stage->value }}'" class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="{{ $stageStyles[$stage->color()]['dot'] ?? 'bg-slate-400' }} size-2.5 shrink-0 rounded-full"></span>
                                    <span class="truncate">{{ $stage->label() }}</span>
                                </span>
                            @endforeach
                            <x-ui.spinner size="xs" class="text-slate-400" x-show="saving.lead_stage" />
                            <x-ui.icon name="chevrons-up-down" class="size-4 text-slate-400" />
                        </button>
                    </x-slot:trigger>
                    @foreach ($stages as $stage)
                        <button type="button" role="menuitem" tabindex="-1" x-on:click="setStage('{{ $stage->value }}'); close(true)"
                            class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left hover:bg-slate-100 focus:bg-slate-100 focus:outline-none">
                            <span class="{{ $stageStyles[$stage->color()]['dot'] ?? 'bg-slate-400' }} size-2.5 shrink-0 rounded-full"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-medium text-ink">{{ $stage->label() }}</span>
                                <span class="block truncate text-xs text-ink-muted">{{ $stageHints[$stage->value] ?? '' }}</span>
                            </span>
                            <x-ui.icon name="check" class="size-4 text-brand-600" x-show="(conv?.lead_stage ?? 'new') === '{{ $stage->value }}'" />
                        </button>
                    @endforeach
                </x-ui.dropdown>
            </div>

            {{-- Tags --}}
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Tags</p>
                    <x-ui.spinner size="xs" class="text-slate-400" x-show="saving.tags" />
                </div>
                <div class="relative" x-on:click.outside="tagOpen = false">
                    <div class="flex min-h-9 flex-wrap items-center gap-1.5 rounded-lg border border-line-strong bg-white px-2 py-1.5 shadow-xs transition focus-within:border-brand-500 focus-within:shadow-focus"
                        x-on:click="$refs.tagInput.focus()">
                        <template x-for="tag in (conv?.tags ?? [])" x-bind:key="tag">
                            <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-brand-50 py-0.5 pr-1 pl-2 text-xs font-medium text-brand-700 ring-1 ring-brand-100 ring-inset">
                                <span class="truncate" x-text="tag"></span>
                                <button type="button" x-on:click.stop="removeTag(tag)" class="rounded p-0.5 text-brand-400 hover:bg-brand-100 hover:text-brand-700" x-bind:aria-label="'Remove tag ' + tag">
                                    <x-ui.icon name="x" class="size-3" :stroke="2.5" />
                                </button>
                            </span>
                        </template>
                        <input type="text" x-ref="tagInput" x-model="tagInput" maxlength="50" autocomplete="off"
                            x-on:focus="tagOpen = true" x-on:input="tagOpen = true; tagIndex = 0" x-on:keydown="onTagKey($event)"
                            x-bind:placeholder="(conv?.tags ?? []).length ? 'Add…' : 'Add a tag…'"
                            class="min-w-16 flex-1 border-0 bg-transparent p-0.5 text-[13px] text-ink placeholder:text-slate-400 focus:ring-0 focus:outline-none" aria-label="Add a tag">
                    </div>
                    <div x-show="tagOpen && (tagSuggestions.length || tagInput.trim())" x-cloak x-transition.opacity.duration.100ms
                        class="absolute inset-x-0 top-full z-30 mt-1.5 max-h-56 overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-pop" role="listbox">
                        <template x-for="(t, ti) in tagSuggestions" x-bind:key="t">
                            <button type="button" role="option" x-on:mousedown.prevent="addTag(t)" x-on:mouseenter="tagIndex = ti"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[13px] text-slate-700"
                                x-bind:class="{ 'bg-slate-100 text-ink': ti === tagIndex }">
                                <x-ui.icon name="tag" class="size-3.5 text-slate-400" /><span x-text="t"></span>
                            </button>
                        </template>
                        <template x-if="tagInput.trim() && ! tagSuggestions.includes(tagInput.trim().toLowerCase()) && ! (conv?.tags ?? []).includes(tagInput.trim().toLowerCase())">
                            <button type="button" x-on:mousedown.prevent="addTag(tagInput)"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-[13px] text-brand-700 hover:bg-brand-50">
                                <x-ui.icon name="plus" class="size-3.5" /> Create “<span class="font-medium" x-text="tagInput.trim().toLowerCase()"></span>”
                            </button>
                        </template>
                    </div>
                </div>
                <p x-show="errors.tags" x-text="errors.tags" class="ui-error mt-1"></p>
            </div>

            {{-- Contact info --}}
            <div class="space-y-3">
                <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Contact info</p>
                @foreach ([['email', 'mail', 'email', 'Add email', 'email'], ['phone', 'phone', 'tel', 'Add phone', 'tel']] as [$field, $icon, $type, $placeholder, $autocomplete])
                    <div>
                        <label class="relative block">
                            <span class="sr-only">{{ ucfirst($field) }}</span>
                            <x-ui.icon :name="$icon" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input type="{{ $type }}" x-model="form.{{ $field }}" placeholder="{{ $placeholder }}" autocomplete="off" maxlength="255"
                                x-on:blur="saveIfChanged('{{ $field }}')" x-on:keydown.enter.prevent="$el.blur()"
                                x-on:keydown.escape.prevent="form.{{ $field }} = conv?.{{ $field }} ?? ''; errors.{{ $field }} = null; $el.blur()"
                                x-bind:aria-invalid="errors.{{ $field }} ? 'true' : 'false'"
                                class="ui-input h-9 pr-8 pl-9 text-[13px]">
                            <span class="absolute top-1/2 right-2.5 -translate-y-1/2">
                                <x-ui.spinner size="xs" class="text-slate-400" x-show="saving.{{ $field }}" />
                                <x-ui.icon name="check" class="size-4 text-success-600" x-show="saved.{{ $field }} && ! saving.{{ $field }}" x-transition.opacity />
                            </span>
                        </label>
                        <p x-show="errors.{{ $field }}" x-cloak x-text="errors.{{ $field }}" class="ui-error mt-1"></p>
                    </div>
                @endforeach
            </div>

            {{-- Notes --}}
            <div>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Notes</p>
                    <span class="text-2xs font-medium">
                        <span x-show="saving.notes" class="text-slate-400">Saving…</span>
                        <span x-show="saved.notes && ! saving.notes" x-transition.opacity class="inline-flex items-center gap-1 text-success-600"><x-ui.icon name="check" class="size-3" :stroke="2.5" /> Saved</span>
                    </span>
                </div>
                <textarea x-model="form.notes" rows="3" maxlength="10000" placeholder="Private notes about this contact (only your team sees them)"
                    x-on:input="queueNotes(); $el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 320) + 'px'" x-on:blur="flushNotes()"
                    class="ui-input resize-none bg-warning-50/40 text-[13px] leading-relaxed"></textarea>
                <p x-show="errors.notes" x-cloak x-text="errors.notes" class="ui-error mt-1"></p>
            </div>

            {{-- Captured leads --}}
            <div x-show="conv?.captured?.length" x-cloak>
                <p class="mb-2 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Captured from chat</p>
                <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line">
                    <template x-for="(e, ei) in (conv?.captured ?? [])" x-bind:key="ei">
                        <li class="flex items-center gap-2.5 bg-white px-3 py-2">
                            <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
                                x-bind:class="{ 'bg-sky-50 text-sky-600': e.type === 'email', 'bg-emerald-50 text-emerald-600': e.type !== 'email' }">
                                <x-ui.icon name="mail" class="size-3.5" x-show="e.type === 'email'" />
                                <x-ui.icon name="phone" class="size-3.5" x-show="e.type !== 'email'" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium text-ink" x-text="e.value"></span>
                                <span class="block text-2xs text-slate-400" x-text="e.at ? longAgo(e.at) : ''"></span>
                            </span>
                            <button type="button" x-on:click="copyText(e.value).then(ok => toast(ok ? 'Copied' : 'Could not copy', ok ? 'success' : 'error'))"
                                class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Copy">
                                <x-ui.icon name="copy" class="size-3.5" />
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Stats --}}
            <div>
                <p class="mb-2 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Activity</p>
                <dl class="grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset">
                        <dt class="text-2xs text-ink-muted">Messages</dt>
                        <dd class="mt-0.5 text-[15px] font-semibold text-ink tabular-nums" x-text="conv?.messages_count !== undefined ? number(conv.messages_count) : '—'"></dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset">
                        <dt class="text-2xs text-ink-muted">First seen</dt>
                        <dd class="mt-0.5 truncate text-[13px] font-semibold text-ink" x-text="conv?.first_seen_at ? longAgo(conv.first_seen_at) : '—'" x-bind:title="fullDate(conv?.first_seen_at)"></dd>
                    </div>
                    <div class="col-span-2 rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset">
                        <dt class="text-2xs text-ink-muted">Last customer message</dt>
                        <dd class="mt-0.5 flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <span class="truncate" x-text="conv?.last_customer_message_at ? longAgo(conv.last_customer_message_at) : 'Never'" x-bind:title="fullDate(conv?.last_customer_message_at)"></span>
                            <span x-show="windowOpen" class="rounded-full bg-success-50 px-1.5 py-px text-2xs font-semibold text-success-700">Can reply</span>
                            <span x-show="! windowOpen" class="rounded-full bg-warning-50 px-1.5 py-px text-2xs font-semibold text-warning-700">24h window closed</span>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 truncate font-mono text-2xs text-slate-400" x-bind:title="'Customer ID: ' + (conv?.external_user_id ?? '')" x-text="'ID ' + (conv?.external_user_id ?? '')"></p>
            </div>
        </div>
    </div>
</aside>
