{{--
    Contact profile slide-over. Data: contactsPage.contact (ContactController::present()), edits in `form`.
    Opened by open(id) from list rows / board cards; ?contact=ID in the URL.
--}}
@php
    $field = 'ui-input h-9';
    $sectionTitle = 'text-2xs font-semibold tracking-wider text-slate-400 uppercase';
@endphp

<div x-show="drawer" x-cloak class="fixed inset-0 z-[60] overflow-hidden" role="dialog" aria-modal="true" data-drawer aria-labelledby="contact-drawer-title">
    <div x-show="drawer" x-transition.opacity.duration.200ms class="absolute inset-0 bg-slate-900/30 backdrop-blur-[2px]" x-on:click="close()" aria-hidden="true"></div>

    <div x-show="drawer" x-trap.noscroll="drawer"
        x-transition:enter="transform transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition duration-200 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        class="absolute inset-y-0 right-0 flex w-full max-w-[36rem] flex-col bg-white shadow-pop sm:rounded-l-2xl">

        {{-- Loading --}}
        <template x-if="! contact">
            <div class="flex-1 p-6">
                <div class="flex justify-end"><button type="button" x-on:click="close()" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close"><x-ui.icon name="x" class="size-5" /></button></div>
                <div class="mt-2 flex items-center gap-4">
                    <div class="ui-skeleton size-16 rounded-full"></div>
                    <div class="flex-1 space-y-2"><div class="ui-skeleton h-5 w-2/3"></div><div class="ui-skeleton h-3.5 w-1/3"></div></div>
                </div>
                <div class="mt-8 grid grid-cols-4 gap-3">
                    @for ($i = 0; $i < 4; $i++)<div class="ui-skeleton h-16 rounded-xl"></div>@endfor
                </div>
                <div class="mt-8 space-y-3">
                    @for ($i = 0; $i < 5; $i++)<div class="ui-skeleton h-9 rounded-lg"></div>@endfor
                </div>
            </div>
        </template>

        <template x-if="contact">
            <div class="flex min-h-0 flex-1 flex-col">
                {{-- Header --}}
                <div class="relative shrink-0 overflow-hidden border-b border-line sm:rounded-tl-2xl">
                    <div class="absolute inset-0 bg-gradient-to-br from-brand-50 via-white to-violet-50" aria-hidden="true"></div>
                    <div class="absolute -top-16 -right-10 size-48 rounded-full bg-brand-200/30 blur-3xl" aria-hidden="true"></div>
                    <div class="relative px-6 pt-4 pb-5">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="{{ $sectionTitle }}">Contact profile</p>
                            <div class="-mr-2 flex items-center gap-0.5">
                                <button type="button" x-on:click="copyText(location.href).then(() => toast('Link copied'))" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/80 hover:text-slate-700" aria-label="Copy link to this contact" title="Copy link">
                                    <x-ui.icon name="link" class="size-4" />
                                </button>
                                <button type="button" x-on:click="refreshProfile()" x-bind:disabled="refreshing" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/80 hover:text-slate-700 disabled:opacity-50" aria-label="Refresh profile from Meta" title="Refresh profile from Meta">
                                    <x-ui.icon name="refresh" class="size-4" x-bind:class="{ 'animate-spin': refreshing }" />
                                </button>
                                <button type="button" x-on:click="close()" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/80 hover:text-slate-700" aria-label="Close">
                                    <x-ui.icon name="x" class="size-5" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="relative inline-flex shrink-0">
                                <span class="inline-flex size-16 items-center justify-center rounded-full text-lg font-semibold tracking-tight ring-4 ring-white select-none" x-bind:class="contact.avatar_class" x-text="contact.initials" aria-hidden="true"></span>
                                <template x-if="contact.picture">
                                    <img x-bind:src="contact.picture" alt="" referrerpolicy="no-referrer" x-on:error="$el.remove()" class="absolute inset-0 size-16 rounded-full bg-white object-cover ring-4 ring-white">
                                </template>
                                <span class="absolute right-0 bottom-0" x-show="contact.platform === 'instagram'"><x-ui.channel-badge platform="instagram" variant="icon" size="sm" /></span>
                                <span class="absolute right-0 bottom-0" x-show="contact.platform !== 'instagram'"><x-ui.channel-badge platform="facebook" variant="icon" size="sm" /></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 id="contact-drawer-title" class="truncate text-xl font-semibold tracking-tight text-ink" x-text="contact.name"></h2>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[13px] text-ink-muted">
                                    <span x-show="contact.username" x-text="'@' + contact.username"></span>
                                    <span x-show="contact.username" class="text-slate-300">·</span>
                                    <span x-text="contact.channel"></span>
                                    <template x-if="contact.page">
                                        <span class="contents"><span class="text-slate-300">·</span><span class="truncate" x-text="contact.page"></span></span>
                                    </template>
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" x-bind:class="stage(contact.lead_stage).badge">
                                        <span class="size-1.5 rounded-full" x-bind:class="stage(contact.lead_stage).dot"></span><span x-text="stage(contact.lead_stage).label"></span>
                                    </span>
                                    @foreach ($botStyles as $key => $bot)
                                        <span x-show="contact.bot === @js($key)" class="{{ $bot['class'] }} inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">
                                            <span class="{{ $bot['dot'] }} size-1.5 rounded-full"></span>{{ $bot['label'] }}
                                        </span>
                                    @endforeach
                                    <span x-show="contact.unread > 0" class="rounded-full bg-brand-600 px-2 py-0.5 text-xs font-semibold text-white" x-text="contact.unread + ' unread'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button variant="primary" size="sm" icon="message" href="#" x-bind:href="contact.urls.live_chat">Open in Live Chat</x-ui.button>
                            <x-ui.button size="sm" icon="copy" x-on:click="copyText(contact.external_user_id).then(() => toast('Customer ID copied'))" x-bind:title="contact.external_user_id">Copy ID</x-ui.button>
                        </div>
                    </div>
                </div>

                {{-- Body --}}
                <div class="ui-scroll min-h-0 flex-1 overflow-y-auto">
                    <form id="contact-form" class="space-y-7 px-6 py-6" x-on:submit.prevent="save()" novalidate>
                        {{-- Stats --}}
                        <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset">
                                <p class="text-2xs font-medium text-ink-muted">Messages</p>
                                <p class="mt-0.5 text-lg font-semibold text-ink tabular-nums" x-text="contact.stats.messages.toLocaleString()"></p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset">
                                <p class="text-2xs font-medium text-ink-muted">In / out</p>
                                <p class="mt-0.5 text-lg font-semibold text-ink tabular-nums"><span x-text="contact.stats.incoming"></span><span class="text-slate-300"> / </span><span x-text="contact.stats.outgoing"></span></p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset" x-bind:title="contact.stats.first_seen_full">
                                <p class="text-2xs font-medium text-ink-muted">First seen</p>
                                <p class="mt-1 truncate text-[13px] font-semibold text-ink" x-text="contact.stats.first_seen ?? '—'"></p>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-line ring-inset" x-bind:title="contact.last_active_full">
                                <p class="text-2xs font-medium text-ink-muted">Last active</p>
                                <p class="mt-1 truncate text-[13px] font-semibold text-ink" x-text="contact.last_active ?? '—'"></p>
                            </div>
                        </div>

                        {{-- Lead stage --}}
                        <section>
                            <h3 class="{{ $sectionTitle }} mb-2.5">Lead stage</h3>
                            <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="Lead stage">
                                <template x-for="(s, key) in stages" x-bind:key="key">
                                    <button type="button" role="radio" x-bind:aria-checked="form.lead_stage === key" x-on:click="form.lead_stage = key"
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium ring-1 transition ring-inset"
                                        x-bind:class="form.lead_stage === key ? s.active : 'bg-white text-slate-600 ring-line-strong hover:bg-slate-50 hover:text-ink'">
                                        <span class="size-2 rounded-full" x-bind:class="s.dot"></span><span x-text="s.label"></span>
                                    </button>
                                </template>
                            </div>
                            <p class="ui-error mt-1.5" x-show="err('lead_stage')" x-text="err('lead_stage')"></p>
                        </section>

                        {{-- Profile --}}
                        <section>
                            <h3 class="{{ $sectionTitle }} mb-2.5">Profile</h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="cf-name" class="ui-label">Display name</label>
                                    <input id="cf-name" type="text" x-model="form.customer_name" maxlength="255" class="{{ $field }} mt-1.5" x-bind:placeholder="contact.name" x-bind:aria-invalid="err('customer_name') ? 'true' : 'false'">
                                    <p class="ui-error mt-1.5" x-show="err('customer_name')" x-text="err('customer_name')"></p>
                                </div>
                                <div>
                                    <label for="cf-first" class="ui-label">First name</label>
                                    <input id="cf-first" type="text" x-model="form.first_name" maxlength="255" class="{{ $field }} mt-1.5" x-bind:aria-invalid="err('first_name') ? 'true' : 'false'">
                                    <p class="ui-error mt-1.5" x-show="err('first_name')" x-text="err('first_name')"></p>
                                </div>
                                <div>
                                    <label for="cf-last" class="ui-label">Last name</label>
                                    <input id="cf-last" type="text" x-model="form.last_name" maxlength="255" class="{{ $field }} mt-1.5" x-bind:aria-invalid="err('last_name') ? 'true' : 'false'">
                                    <p class="ui-error mt-1.5" x-show="err('last_name')" x-text="err('last_name')"></p>
                                </div>
                                <div>
                                    <label for="cf-email" class="ui-label">Email</label>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><x-ui.icon name="mail" class="size-4" /></span>
                                        <input id="cf-email" type="email" x-model="form.email" maxlength="255" class="{{ $field }} pl-9" placeholder="name@example.com" x-bind:aria-invalid="err('email') ? 'true' : 'false'">
                                    </div>
                                    <p class="ui-error mt-1.5" x-show="err('email')" x-text="err('email')"></p>
                                </div>
                                <div>
                                    <label for="cf-phone" class="ui-label">Phone</label>
                                    <div class="relative mt-1.5">
                                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><x-ui.icon name="phone" class="size-4" /></span>
                                        <input id="cf-phone" type="tel" x-model="form.phone" maxlength="40" class="{{ $field }} pl-9" placeholder="+1 555 010 0000" x-bind:aria-invalid="err('phone') ? 'true' : 'false'">
                                    </div>
                                    <p class="ui-error mt-1.5" x-show="err('phone')" x-text="err('phone')"></p>
                                </div>
                            </div>
                        </section>

                        {{-- Tags --}}
                        <section>
                            <h3 class="{{ $sectionTitle }} mb-2.5">Tags</h3>
                            <div class="relative" x-on:click.outside="tagOpen = false">
                                <div class="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-line-strong bg-white px-2 py-1.5 shadow-xs transition focus-within:border-brand-500 focus-within:shadow-focus"
                                    x-on:click="$refs.tagInput.focus()">
                                    <template x-for="t in form.tags" x-bind:key="t">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-brand-50 py-0.5 pr-1 pl-2 text-xs font-medium text-brand-700 ring-1 ring-brand-100 ring-inset">
                                            <span x-text="t"></span>
                                            <button type="button" x-on:click.stop="removeTag(t)" class="rounded p-0.5 text-brand-400 hover:bg-brand-100 hover:text-brand-700" x-bind:aria-label="'Remove tag ' + t">
                                                <x-ui.icon name="x" class="size-3" />
                                            </button>
                                        </span>
                                    </template>
                                    <input x-ref="tagInput" type="text" x-model="tagDraft" maxlength="50" autocomplete="off" aria-label="Add a tag"
                                        x-on:focus="tagOpen = true" x-on:input="tagOpen = true" x-on:keydown="tagKeydown($event)" x-on:keydown.escape.stop="tagOpen = false"
                                        class="min-w-24 flex-1 border-0 bg-transparent px-1 py-0.5 text-sm text-ink placeholder:text-slate-400 focus:ring-0 focus:outline-none"
                                        x-bind:placeholder="form.tags.length ? 'Add tag…' : 'Add tags, e.g. vip, wholesale'">
                                </div>
                                <div x-show="tagOpen && (tagSuggestions.length || tagDraft.trim())" x-cloak x-transition.opacity.duration.100ms
                                    class="absolute inset-x-0 top-full z-10 mt-1.5 max-h-56 overflow-y-auto rounded-xl border border-line bg-white p-1.5 shadow-pop">
                                    <template x-if="tagDraft.trim() && ! form.tags.includes(tagDraft.trim().toLowerCase()) && ! allTags.includes(tagDraft.trim().toLowerCase())">
                                        <button type="button" x-on:click="addTag(); $refs.tagInput.focus()" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-[13px] text-slate-700 hover:bg-slate-100">
                                            <x-ui.icon name="plus" class="size-3.5 text-brand-600" /> Create <strong class="font-semibold text-ink" x-text="tagDraft.trim().toLowerCase()"></strong>
                                        </button>
                                    </template>
                                    <template x-for="t in tagSuggestions" x-bind:key="t">
                                        <button type="button" x-on:click="addTag(t); $refs.tagInput.focus()" class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-[13px] text-slate-700 hover:bg-slate-100">
                                            <x-ui.icon name="tag" class="size-3.5 text-slate-400" /><span x-text="t"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <p class="ui-hint mt-1.5">Press Enter or comma to add. Tags are lowercase.</p>
                            <p class="ui-error mt-1.5" x-show="err('tags')" x-text="err('tags')"></p>
                        </section>

                        {{-- Notes --}}
                        <section>
                            <h3 class="{{ $sectionTitle }} mb-2.5"><label for="cf-notes">Notes</label></h3>
                            <textarea id="cf-notes" x-model="form.notes" rows="4" maxlength="10000" class="ui-input resize-y" placeholder="Private notes for your team: preferences, order history, follow-ups…" x-bind:aria-invalid="err('notes') ? 'true' : 'false'"></textarea>
                            <p class="ui-error mt-1.5" x-show="err('notes')" x-text="err('notes')"></p>
                        </section>

                        {{-- Captured leads --}}
                        <section>
                            <div class="mb-2.5 flex items-center justify-between">
                                <h3 class="{{ $sectionTitle }}">Captured from chat</h3>
                                <span class="text-2xs text-ink-muted" x-show="contact.stats.lead_captured" x-text="'First lead ' + contact.stats.lead_captured"></span>
                            </div>
                            <template x-if="contact.captured.length">
                                <ol class="relative space-y-3 border-l border-line pl-5">
                                    <template x-for="(entry, i) in contact.captured" x-bind:key="i">
                                        <li class="relative">
                                            <span class="absolute top-0.5 -left-[1.95rem] inline-flex size-5 items-center justify-center rounded-full bg-success-50 text-success-600 ring-4 ring-white">
                                                <span x-show="entry.type === 'email'"><x-ui.icon name="mail" class="size-3" /></span>
                                                <span x-show="entry.type === 'phone'"><x-ui.icon name="phone" class="size-3" /></span>
                                            </span>
                                            <div class="flex items-center justify-between gap-3">
                                                <p class="min-w-0 truncate text-[13px] font-medium text-ink" x-text="entry.value"></p>
                                                <button type="button" x-on:click="copyText(entry.value).then(() => toast('Copied'))" class="shrink-0 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Copy"><x-ui.icon name="copy" class="size-3.5" /></button>
                                            </div>
                                            <p class="text-xs text-ink-muted" x-bind:title="entry.at_full"><span x-text="entry.type === 'email' ? 'Email' : 'Phone'"></span> shared in chat · <span x-text="entry.at ?? ''"></span></p>
                                        </li>
                                    </template>
                                </ol>
                            </template>
                            <template x-if="! contact.captured.length">
                                <p class="rounded-xl border border-dashed border-line-strong px-4 py-3 text-[13px] text-ink-muted">
                                    No details captured yet. Emails and phone numbers the customer types in chat are saved here automatically.
                                </p>
                            </template>
                        </section>

                        {{-- Recent messages --}}
                        <section>
                            <div class="mb-2.5 flex items-center justify-between">
                                <h3 class="{{ $sectionTitle }}">Recent messages</h3>
                                <a x-bind:href="contact.urls.live_chat" class="ui-link text-xs">Full conversation</a>
                            </div>
                            <template x-if="contact.messages.length">
                                <div class="space-y-2 rounded-xl bg-slate-50 p-3 ring-1 ring-line ring-inset">
                                    <template x-for="m in contact.messages" x-bind:key="m.id">
                                        <div class="flex flex-col" x-bind:class="m.mine ? 'items-end' : 'items-start'">
                                            <div class="max-w-[85%] rounded-2xl px-3 py-2 text-[13px] leading-5 break-words whitespace-pre-line"
                                                x-bind:class="m.mine ? (m.failed ? 'bg-danger-50 text-danger-700 ring-1 ring-danger-100 rounded-br-md' : 'bg-brand-600 text-white rounded-br-md') : 'bg-white text-ink ring-1 ring-line rounded-bl-md'">
                                                <span x-text="m.body || (m.attachment ? 'Attachment' : '(no text)')" x-bind:class="{ 'italic opacity-80': ! m.body }"></span>
                                            </div>
                                            <p class="mt-0.5 px-1 text-2xs text-ink-muted" x-bind:title="m.at_full"><span x-text="m.sender"></span> · <span x-text="m.at"></span><span x-show="m.failed" class="font-semibold text-danger-600"> · failed</span></p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="! contact.messages.length">
                                <p class="rounded-xl border border-dashed border-line-strong px-4 py-3 text-[13px] text-ink-muted">No messages stored for this contact.</p>
                            </template>
                        </section>

                        {{-- Danger zone --}}
                        <section class="rounded-xl border border-danger-100 bg-danger-50/40 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-[13px] font-semibold text-danger-700">Danger zone</h3>
                                    <p class="mt-0.5 text-xs text-slate-600">
                                        Permanently delete this contact, all <span x-text="contact.stats.messages"></span> messages and matching raw webhook data.
                                        Use this for customer data-deletion requests. It cannot be undone.
                                    </p>
                                </div>
                                <x-ui.button variant="danger-soft" size="sm" icon="trash" x-on:click="deleteConfirm = ''; $dispatch('open-modal', 'contact-delete')">Delete contact &amp; all messages</x-ui.button>
                            </div>
                        </section>
                    </form>
                </div>

                {{-- Save bar --}}
                <div class="shrink-0 border-t border-line bg-white/95 px-6 py-3 backdrop-blur" x-show="dirty || saving" x-transition.opacity.duration.150ms>
                    <div class="flex items-center justify-between gap-3">
                        <p class="flex items-center gap-2 text-[13px] font-medium text-slate-700"><span class="size-2 animate-pulse rounded-full bg-warning-500"></span>Unsaved changes</p>
                        <div class="flex items-center gap-2">
                            <x-ui.button variant="ghost" size="sm" x-on:click="discard()" x-bind:disabled="saving">Discard</x-ui.button>
                            <x-ui.button type="submit" form="contact-form" variant="primary" size="sm" icon="check" x-bind:disabled="saving" x-bind:aria-busy="saving ? 'true' : null">Save changes</x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<x-ui.modal name="contact-delete" title="Delete this contact?" icon="trash" tone="danger" size="sm">
    <div class="space-y-4">
        <p>
            <strong class="text-ink" x-text="contact?.name"></strong> and
            <strong class="text-ink" x-text="(contact?.stats.messages ?? 0) + ' messages'"></strong> will be permanently deleted.
            The customer can still message you again later and will appear as a new contact.
        </p>
        <div>
            <label for="contact-delete-confirm" class="ui-label">Type <span class="ui-code">DELETE</span> to confirm</label>
            <input id="contact-delete-confirm" type="text" x-model="deleteConfirm" autocomplete="off" class="ui-input mt-1.5" placeholder="DELETE"
                x-on:keydown.enter.prevent="destroyContact()">
        </div>
    </div>
    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <x-ui.button variant="danger" icon="trash" x-bind:disabled="deleteConfirm !== 'DELETE' || deleting" x-on:click="destroyContact()">Delete permanently</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
