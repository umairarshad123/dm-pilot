{{--
    Pipeline board: one column per LeadStage. Drag a card (native HTML5 drag and drop) or use its
    "Move to" menu (keyboard / touch fallback) to change the stage. Data: contactsPage.columns.
--}}
<div class="ui-scroll -mx-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
    <div class="flex snap-x gap-4">
        <template x-for="col in columns" x-bind:key="col.stage">
            <section class="flex max-h-[calc(100dvh-13rem)] min-h-[22rem] w-[18.5rem] shrink-0 snap-start flex-col rounded-2xl bg-slate-100/80 ring-1 ring-line transition-[box-shadow,background-color] duration-150 ring-inset xl:w-auto xl:min-w-0 xl:flex-1"
                x-bind:class="over === col.stage && drag && drag.from !== col.stage ? '!bg-brand-50/70 ring-2 !ring-brand-400' : ''"
                x-bind:aria-label="stage(col.stage).label + ' column'"
                x-on:dragover.prevent="over = col.stage; $event.dataTransfer.dropEffect = 'move'"
                x-on:dragleave="$el.contains($event.relatedTarget) || (over === col.stage && (over = null))"
                x-on:drop.prevent="drop(col.stage)">
                <header class="shrink-0 px-3 pt-3 pb-2">
                    <div class="mb-2.5 h-1 rounded-full bg-gradient-to-r" x-bind:class="stage(col.stage).bar"></div>
                    <div class="flex items-center justify-between gap-2 px-0.5">
                        <h3 class="flex items-center gap-2 text-[13px] font-semibold text-ink">
                            <span class="size-2 rounded-full" x-bind:class="stage(col.stage).dot"></span>
                            <span x-text="col.label"></span>
                        </h3>
                        <span class="rounded-full bg-white px-2 py-0.5 text-2xs font-semibold text-slate-600 tabular-nums shadow-xs ring-1 ring-line" x-text="col.count.toLocaleString()"></span>
                    </div>
                </header>

                <div class="ui-scroll min-h-0 flex-1 space-y-2 overflow-y-auto px-3 pb-3">
                    <template x-for="card in col.cards" x-bind:key="card.id">
                        <article draggable="true" tabindex="0" role="button"
                            x-bind:aria-label="'Open ' + card.name"
                            x-on:dragstart="dragStart($event, card, col.stage)" x-on:dragend="dragEnd()"
                            x-on:click="open(card.id)" x-on:keydown.enter.prevent="open(card.id)"
                            class="group relative cursor-grab rounded-xl bg-white p-3 shadow-card ring-1 ring-line transition hover:-translate-y-px hover:shadow-card-hover active:cursor-grabbing"
                            x-bind:class="{ 'opacity-40 rotate-1': drag && drag.id === card.id, 'ring-2 ring-brand-400': contact && drawer && contact.id === card.id }">
                            <div class="flex items-start gap-2.5">
                                <span class="relative inline-flex shrink-0">
                                    <span class="inline-flex size-9 items-center justify-center rounded-full text-xs font-semibold tracking-tight select-none" x-bind:class="card.avatar_class" x-text="card.initials" aria-hidden="true"></span>
                                    <template x-if="card.picture">
                                        <img x-bind:src="card.picture" alt="" loading="lazy" referrerpolicy="no-referrer" x-on:error="$el.remove()" class="absolute inset-0 size-9 rounded-full bg-white object-cover ring-1 ring-black/5">
                                    </template>
                                    <span class="absolute -right-1 -bottom-1" x-show="card.platform === 'instagram'"><x-ui.channel-badge platform="instagram" variant="icon" size="2xs" /></span>
                                    <span class="absolute -right-1 -bottom-1" x-show="card.platform !== 'instagram'"><x-ui.channel-badge platform="facebook" variant="icon" size="2xs" /></span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold text-ink" x-text="card.name"></p>
                                    <p class="truncate text-xs text-ink-muted" x-text="card.username ? '@' + card.username : (card.page ?? card.channel)"></p>
                                </div>
                                <template x-if="card.unread > 0">
                                    <span class="rounded-full bg-brand-600 px-1.5 py-px text-2xs font-semibold text-white tabular-nums" x-text="card.unread" x-bind:title="card.unread + ' unread'"></span>
                                </template>
                            </div>

                            <div class="mt-2.5 flex flex-wrap gap-1" x-show="card.tags.length">
                                <template x-for="t in card.tags.slice(0, 3)" x-bind:key="t">
                                    <span class="max-w-full truncate rounded-md bg-slate-100 px-1.5 py-0.5 text-2xs font-medium text-slate-600" x-text="t"></span>
                                </template>
                                <span x-show="card.tags.length > 3" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-2xs font-semibold text-slate-500" x-text="'+' + (card.tags.length - 3)"></span>
                            </div>

                            <div class="mt-2.5 flex items-center gap-2 border-t border-line pt-2 text-2xs text-ink-muted">
                                <span class="flex items-center gap-1" x-bind:title="card.last_active_full">
                                    <x-ui.icon name="clock" class="size-3" /><span x-text="card.last_active ?? 'No activity'"></span>
                                </span>
                                <span class="ml-auto flex items-center gap-1.5">
                                    <span x-show="card.has_email" title="Has email" class="text-success-600"><x-ui.icon name="mail" class="size-3.5" /><span class="sr-only">Has email</span></span>
                                    <span x-show="card.has_phone" title="Has phone" class="text-success-600"><x-ui.icon name="phone" class="size-3.5" /><span class="sr-only">Has phone</span></span>
                                    {{-- Fallback to drag and drop: keyboard / touch friendly stage menu --}}
                                    <label class="relative inline-flex size-6 items-center justify-center rounded-md text-slate-400 transition focus-within:ring-2 focus-within:ring-brand-500 hover:bg-slate-100 hover:text-slate-700" x-on:click.stop title="Move to stage">
                                        <x-ui.icon name="arrow-right" class="size-3.5" />
                                        <select class="absolute inset-0 cursor-pointer opacity-0" aria-label="Move to stage"
                                            x-on:keydown.stop x-on:change="move(card.id, col.stage, $event.target.value); $event.target.value = ''">
                                            <option value="">Move to…</option>
                                            <template x-for="(s, key) in stages" x-bind:key="key">
                                                <option x-bind:value="key" x-text="s.label" x-bind:disabled="key === col.stage"></option>
                                            </template>
                                        </select>
                                    </label>
                                </span>
                            </div>
                        </article>
                    </template>

                    <div x-show="col.cards.length === 0" class="flex h-28 flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-200 text-center text-xs text-slate-400"
                        x-bind:class="{ '!border-brand-300 text-brand-500': over === col.stage }">
                        <x-ui.icon name="users" class="mb-1.5 size-4" />
                        <span x-text="drag ? 'Drop here' : 'No contacts'"></span>
                    </div>

                    <a x-show="col.count > col.cards.length" x-bind:href="col.href"
                        class="block rounded-lg px-2 py-1.5 text-center text-xs font-medium text-brand-600 hover:bg-white hover:text-brand-700"
                        x-text="'View all ' + col.count.toLocaleString() + ' in list'"></a>
                </div>
            </section>
        </template>
    </div>
</div>
<p class="text-center text-xs text-ink-muted">
    Drag cards between columns to change a contact's stage, or use the <x-ui.icon name="arrow-right" class="inline size-3" /> menu on a card.
    Each column shows the {{ \App\Http\Controllers\Admin\ContactController::BOARD_LIMIT }} most recently active contacts.
</p>
