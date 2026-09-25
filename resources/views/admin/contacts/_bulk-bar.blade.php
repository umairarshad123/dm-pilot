{{-- Floating bulk-action bar (list view). State lives in contactsPage (selected / allMatching / bulkMenu). --}}
@php
    $barBtn = 'inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-[13px] font-medium text-slate-200 transition hover:bg-white/10 hover:text-white disabled:opacity-50';
@endphp

<form x-ref="exportForm" method="POST" action="{{ route('admin.contacts.export') }}" class="hidden" data-no-loading>
    @csrf
    <template x-for="id in selected" x-bind:key="id"><input type="hidden" name="ids[]" x-bind:value="id"></template>
</form>

<div x-show="selectionCount > 0" x-cloak
    x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-4 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="translate-y-4 opacity-0"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-40 flex justify-center px-4 lg:pl-64">
    <div class="pointer-events-auto relative max-w-full" x-on:click.outside="bulkMenu = null" x-on:keydown.escape.stop="bulkMenu ? (bulkMenu = null) : clearSelection()">

        {{-- Tag popover (add / remove) --}}
        <div x-show="bulkMenu === 'add_tag' || bulkMenu === 'remove_tag'" x-cloak x-transition.opacity.duration.100ms
            class="absolute bottom-full left-1/2 mb-2 w-72 -translate-x-1/2 rounded-xl border border-line bg-white p-3 text-ink shadow-pop">
            <form x-on:submit.prevent="bulkTag.trim() && bulk(bulkMenu, { tag: bulkTag })" class="space-y-2.5">
                <label for="bulk-tag" class="block text-[13px] font-semibold"
                    x-text="(bulkMenu === 'add_tag' ? 'Add a tag to ' : 'Remove a tag from ') + selectionCount.toLocaleString() + (selectionCount === 1 ? ' contact' : ' contacts')"></label>
                <div class="flex gap-2">
                    <input id="bulk-tag" type="text" x-model="bulkTag" maxlength="50" autocomplete="off" placeholder="e.g. vip"
                        class="ui-input h-8 text-[13px]" x-effect="(bulkMenu === 'add_tag' || bulkMenu === 'remove_tag') && $nextTick(() => $el.focus())">
                    <x-ui.button type="submit" variant="primary" size="sm" x-bind:disabled="bulkBusy || ! bulkTag.trim()">Apply</x-ui.button>
                </div>
                <div class="flex flex-wrap gap-1" x-show="bulkTagSuggestions.length">
                    <template x-for="t in bulkTagSuggestions" x-bind:key="t">
                        <button type="button" x-on:click="bulkTag = t" x-text="t"
                            class="rounded-md bg-slate-100 px-1.5 py-0.5 text-2xs font-medium text-slate-700 hover:bg-brand-50 hover:text-brand-700"></button>
                    </template>
                </div>
            </form>
        </div>

        {{-- Stage popover --}}
        <div x-show="bulkMenu === 'stage'" x-cloak x-transition.opacity.duration.100ms
            class="absolute bottom-full left-1/2 mb-2 w-60 -translate-x-1/2 rounded-xl border border-line bg-white p-1.5 text-ink shadow-pop" role="menu">
            <p class="px-2.5 pt-1.5 pb-2 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Move to stage</p>
            <template x-for="(s, key) in stages" x-bind:key="key">
                <button type="button" role="menuitem" x-on:click="bulk('set_stage', { lead_stage: key })" x-bind:disabled="bulkBusy"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-[13px] font-medium text-slate-700 hover:bg-slate-100 hover:text-ink">
                    <span class="size-2 rounded-full" x-bind:class="s.dot"></span><span x-text="s.label"></span>
                </button>
            </template>
        </div>

        <div class="flex max-w-full items-center gap-1 rounded-2xl bg-slate-900 p-1.5 pl-4 text-white shadow-pop ring-1 ring-white/10">
            <span class="shrink-0 text-[13px] font-semibold whitespace-nowrap tabular-nums">
                <span x-text="selectionCount.toLocaleString()"></span> selected
            </span>
            <span class="mx-1.5 h-5 w-px shrink-0 bg-white/15" aria-hidden="true"></span>
            <div class="ui-scroll flex min-w-0 items-center gap-0.5 overflow-x-auto">
                <button type="button" class="{{ $barBtn }}" x-on:click="bulkMenu = bulkMenu === 'add_tag' ? null : 'add_tag'; bulkTag = ''" x-bind:class="{ 'bg-white/10 text-white': bulkMenu === 'add_tag' }">
                    <x-ui.icon name="tag" class="size-4" /> Tag
                </button>
                <button type="button" class="{{ $barBtn }}" x-on:click="bulkMenu = bulkMenu === 'remove_tag' ? null : 'remove_tag'; bulkTag = ''" x-bind:class="{ 'bg-white/10 text-white': bulkMenu === 'remove_tag' }">
                    <x-ui.icon name="minus" class="size-4" /> Untag
                </button>
                <button type="button" class="{{ $barBtn }}" x-on:click="bulkMenu = bulkMenu === 'stage' ? null : 'stage'" x-bind:class="{ 'bg-white/10 text-white': bulkMenu === 'stage' }">
                    <x-ui.icon name="trending-up" class="size-4" /> Stage
                </button>
                <button type="button" class="{{ $barBtn }}" x-on:click="exportSelected()">
                    <x-ui.icon name="download" class="size-4" /> Export
                </button>
                <button type="button" class="{{ $barBtn }} text-rose-300 hover:bg-rose-500/15 hover:text-rose-200" x-on:click="bulkMenu = null; bulkConfirm = ''; $dispatch('open-modal', 'contacts-bulk-delete')">
                    <x-ui.icon name="trash" class="size-4" /> Delete
                </button>
            </div>
            <span class="mx-1 h-5 w-px shrink-0 bg-white/15" aria-hidden="true"></span>
            <button type="button" class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white/10 hover:text-white" x-on:click="clearSelection()" aria-label="Clear selection">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
    </div>
</div>

<x-ui.modal name="contacts-bulk-delete" title="Delete selected contacts?" icon="trash" tone="danger" size="sm">
    <div class="space-y-4">
        <p>
            This permanently deletes <strong class="text-ink" x-text="selectionCount.toLocaleString() + (selectionCount === 1 ? ' contact' : ' contacts')"></strong>
            together with all of their messages. It cannot be undone.
        </p>
        <div>
            <label for="bulk-delete-confirm" class="ui-label">Type <span class="ui-code">DELETE</span> to confirm</label>
            <input id="bulk-delete-confirm" type="text" x-model="bulkConfirm" autocomplete="off" class="ui-input mt-1.5" placeholder="DELETE"
                x-on:keydown.enter.prevent="bulkConfirm === 'DELETE' && bulk('delete', { confirm: bulkConfirm })">
        </div>
    </div>
    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <x-ui.button variant="danger" icon="trash" x-bind:disabled="bulkConfirm !== 'DELETE' || bulkBusy" x-on:click="bulk('delete', { confirm: bulkConfirm })">
            Delete permanently
        </x-ui.button>
    </x-slot:footer>
</x-ui.modal>
