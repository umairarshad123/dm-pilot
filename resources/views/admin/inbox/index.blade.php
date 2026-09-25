{{--
    Live Chat: conversation list | thread | contact panel.
    Server: App\Http\Controllers\Admin\ConversationController (index + show deep link) builds $boot.
    Client: resources/js/pages/inbox.js (Alpine data "liveChat"), JSON API in routes/admin/inbox.php.
    Every piece of customer data is rendered with x-text / bound attributes, never as HTML.
--}}
<x-layouts.app title="Live Chat" width="full">
    <script type="application/json" id="live-chat-boot">@json($boot)</script>

    {{--
        Mount point: inbox.js registers Alpine data "liveChat", then swaps in the template below.
        The panes are absolutely positioned inside this wrapper so they never contribute content height:
        the viewport (not the longest pane) sets the height, and each pane scrolls on its own.
    --}}
    <div class="relative min-h-0 flex-1">
    <div data-live-chat-mount class="absolute inset-0 flex overflow-hidden bg-surface">
        <div class="hidden w-[320px] shrink-0 space-y-3 border-r border-line p-4 md:block" aria-hidden="true">
            <div class="ui-skeleton h-9 rounded-xl"></div>
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center gap-3 py-1.5"><div class="ui-skeleton size-11 rounded-full"></div><div class="flex-1 space-y-2"><div class="ui-skeleton h-3 w-2/3 rounded"></div><div class="ui-skeleton h-3 w-full rounded"></div></div></div>
            @endfor
        </div>
        <div class="flex flex-1 items-center justify-center"><x-ui.spinner size="md" class="text-slate-300" /></div>
    </div>
    </div>

    <template id="live-chat-template">
    <div data-live-chat x-data="liveChat" x-on:keydown.window="onKey($event)"
        class="absolute inset-0 flex overflow-hidden bg-surface">

        @include('admin.inbox.partials.list')
        @include('admin.inbox.partials.thread')
        @include('admin.inbox.partials.contact')
        @include('admin.inbox.partials.lightbox')

        {{-- Connection problems --}}
        <div x-show="offline || sessionExpired" x-cloak x-transition.opacity
            class="pointer-events-none absolute inset-x-0 top-3 z-40 flex justify-center px-4">
            <div class="pointer-events-auto flex items-center gap-2.5 rounded-full border border-line bg-white py-1.5 pr-2 pl-3 text-[13px] font-medium text-slate-700 shadow-pop">
                <template x-if="sessionExpired">
                    <span class="flex items-center gap-2.5">
                        <x-ui.icon name="lock" class="size-4 text-warning-600" />
                        Your session expired.
                        <x-ui.button size="xs" variant="primary" x-on:click="location.reload()">Reload</x-ui.button>
                    </span>
                </template>
                <template x-if="! sessionExpired">
                    <span class="flex items-center gap-2.5 pr-1">
                        <x-ui.spinner size="xs" class="text-slate-400" />
                        Reconnecting… live updates are paused.
                    </span>
                </template>
            </div>
        </div>
    </div>
    </template>

    @push('scripts')
        @vite('resources/js/pages/inbox.js')
    @endpush
</x-layouts.app>
