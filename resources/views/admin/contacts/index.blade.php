{{--
    Contacts (CRM). Controller: App\Http\Controllers\Admin\ContactController@index.
    List view (table + bulk actions) or Board view (kanban by lead stage); the profile opens in a slide-over
    drawer (?contact=ID keeps it linkable). Alpine component: admin/contacts/_script.blade.php.
--}}
@php
    // LeadStage::color() -> literal Tailwind classes (never build class names dynamically).
    $stageStyles = [];
    foreach (\App\Enums\LeadStage::cases() as $case) {
        $stageStyles[$case->value] = ['label' => $case->label()] + match ($case->color()) {
            'blue' => ['badge' => 'bg-sky-50 text-sky-700 ring-sky-200/70', 'dot' => 'bg-sky-500', 'active' => 'bg-sky-50 text-sky-800 ring-2 ring-sky-400', 'bar' => 'from-sky-400 to-sky-500'],
            'amber' => ['badge' => 'bg-amber-50 text-amber-800 ring-amber-200/80', 'dot' => 'bg-amber-500', 'active' => 'bg-amber-50 text-amber-900 ring-2 ring-amber-400', 'bar' => 'from-amber-300 to-amber-500'],
            'green' => ['badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200/70', 'dot' => 'bg-emerald-500', 'active' => 'bg-emerald-50 text-emerald-800 ring-2 ring-emerald-400', 'bar' => 'from-emerald-400 to-emerald-500'],
            'red' => ['badge' => 'bg-rose-50 text-rose-700 ring-rose-200/70', 'dot' => 'bg-rose-500', 'active' => 'bg-rose-50 text-rose-800 ring-2 ring-rose-400', 'bar' => 'from-rose-400 to-rose-500'],
            default => ['badge' => 'bg-slate-100 text-slate-700 ring-slate-200', 'dot' => 'bg-slate-400', 'active' => 'bg-slate-100 text-slate-800 ring-2 ring-slate-400', 'bar' => 'from-slate-300 to-slate-400'],
        };
    }

    $botStyles = [
        'on' => ['label' => 'Bot on', 'class' => 'bg-success-50 text-success-700 ring-success-100', 'dot' => 'bg-success-500'],
        'off' => ['label' => 'Bot off', 'class' => 'bg-slate-100 text-slate-600 ring-slate-200', 'dot' => 'bg-slate-400'],
        'paused' => ['label' => 'Paused', 'class' => 'bg-warning-50 text-warning-700 ring-warning-100', 'dot' => 'bg-warning-500'],
        'takeover' => ['label' => 'Human', 'class' => 'bg-violet-50 text-violet-700 ring-violet-100', 'dot' => 'bg-violet-500'],
    ];

    $isBoard = $view === 'board';
    $showPageColumn = $currentPage->isAll() && $connectedPages->count() > 1;
    $viewUrl = fn (string $v) => route('admin.contacts.index', $v === 'board' ? ['view' => 'board'] + \Illuminate\Support\Arr::except($params, ['lead_stage']) : $params);

    $config = [
        'view' => $view,
        'initial' => $initialContact,
        'pageIds' => $contacts ? $contacts->getCollection()->pluck('id')->values() : [],
        'total' => $contacts?->total() ?? 0,
        'stages' => $stageStyles,
        'tags' => array_values($tags),
        'board' => $board,
        'filters' => $params,
        'urls' => [
            'show' => route('admin.contacts.index').'/__ID__',
            'bulk' => route('admin.contacts.bulk'),
            'exportAll' => route('admin.contacts.export', $params),
        ],
    ];
@endphp

<x-layouts.app title="Contacts" width="wide">
    <div x-data="contactsPage(@js($config))" x-on:keydown.escape.window="drawer && escape()" class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-ink">Contacts</h2>
                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-sm font-semibold text-brand-700 tabular-nums ring-1 ring-brand-100 ring-inset">{{ number_format($summary['total']) }}</span>
                </div>
                <p class="mt-1 text-sm text-ink-muted">
                    Everyone who has messaged {{ $currentPage->isAll() ? 'your pages' : $currentPage->label() }}: profiles, lead stages, tags and notes in one place.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <nav class="inline-flex items-center gap-1 rounded-xl bg-slate-100 p-1" aria-label="View">
                    <a href="{{ $viewUrl('list') }}" @if (! $isBoard) aria-current="page" @endif
                        @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition', 'bg-white text-ink shadow-xs' => ! $isBoard, 'text-slate-600 hover:text-ink' => $isBoard])>
                        <x-ui.icon name="menu" class="size-4" /> List
                    </a>
                    <a href="{{ $viewUrl('board') }}" @if ($isBoard) aria-current="page" @endif
                        @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition', 'bg-white text-ink shadow-xs' => $isBoard, 'text-slate-600 hover:text-ink' => ! $isBoard])>
                        <x-ui.icon name="layers" class="size-4" /> Board
                    </a>
                </nav>
                <x-ui.button :href="request()->fullUrlWithoutQuery(['contact'])" icon="refresh" aria-label="Refresh" title="Refresh" />
                <x-ui.button :href="route('admin.contacts.export', $params)" icon="download" data-export>Export CSV</x-ui.button>
            </div>
        </div>

        {{-- KPIs --}}
        <div id="contacts-stats">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                @php($reachPct = $summary['total'] > 0 ? round($summary['reachable'] / $summary['total'] * 100) : 0)
                <x-ui.stat label="Total contacts" :value="number_format($summary['total'])" icon="users" :hint="$currentPage->label()" />
                <x-ui.stat label="New this week" :value="number_format($summary['new_week'])" icon="sparkles" tone="purple" hint="first message in the last 7 days" />
                <x-ui.stat label="Reachable" :value="number_format($summary['reachable'])" icon="mail" tone="success" :hint="$reachPct.'% shared an email or phone'" />
                <x-ui.stat label="Customers" :value="number_format($summary['customers'])" icon="check-circle" tone="warning" :hint="number_format($summary['qualified']).' qualified in the pipeline'" />
            </div>
        </div>

        @if (! $hasAnyContacts)
            <x-ui.card :padded="false">
                <x-ui.empty-state icon="users" title="No contacts yet"
                    description="Every person who sends a message to one of your connected pages shows up here automatically, with their profile, tags and lead stage.">
                    @if ($connectedPages->isEmpty())
                        <x-ui.button variant="primary" icon="plus" :href="route('admin.meta-accounts.connect')">Connect a page</x-ui.button>
                    @endif
                    <x-ui.button icon="message" :href="route('admin.conversations.index')">Open Live Chat</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            {{-- Segments --}}
            <div id="contacts-segments">
                <nav class="ui-scroll -mx-1 flex items-center gap-2 overflow-x-auto px-1 pb-1" aria-label="Segments">
                    @foreach ($segments as $segment)
                        <a href="{{ $segment['href'] }}" @if ($segment['active']) aria-current="page" @endif
                            @class([
                                'group inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 pr-2 pl-3 text-[13px] font-medium whitespace-nowrap ring-1 transition ring-inset',
                                'bg-slate-900 text-white ring-slate-900 shadow-xs' => $segment['active'],
                                'bg-white text-slate-600 ring-line-strong hover:bg-slate-50 hover:text-ink' => ! $segment['active'],
                            ])>
                            <x-ui.icon :name="$segment['icon']" @class(['size-3.5', 'text-white/70' => $segment['active'], 'text-slate-400 group-hover:text-slate-500' => ! $segment['active']]) />
                            {{ $segment['label'] }}
                            <span @class([
                                'rounded-full px-1.5 py-px text-2xs font-semibold tabular-nums',
                                'bg-white/15 text-white' => $segment['active'],
                                'bg-slate-100 text-slate-600' => ! $segment['active'],
                            ])>{{ number_format($segment['count']) }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>

            {{-- Filters --}}
            <x-ui.filter-bar :action="route('admin.contacts.index')" search="q" placeholder="Search name, @username, email or phone"
                :reset="route('admin.contacts.index', $isBoard ? ['view' => 'board'] : [])">
                @if ($isBoard)<input type="hidden" name="view" value="board">@endif
                @if (request('reachable') === '1')<input type="hidden" name="reachable" value="1">@endif

                <x-ui.select name="platform" size="sm" class="w-auto" placeholder="All channels"
                    :options="['facebook' => 'Messenger', 'instagram' => 'Instagram']" :value="$filters['platform'] ?? null" aria-label="Channel" />

                @unless ($isBoard)
                    @php($stageValue = $filters['lead_stage'] ?? null)
                    <div class="relative">
                        @if ($stageValue)
                            <span class="{{ $stageStyles[$stageValue]['dot'] }} pointer-events-none absolute top-1/2 left-3 z-[1] size-2 -translate-y-1/2 rounded-full" aria-hidden="true"></span>
                        @endif
                        <x-ui.select name="lead_stage" size="sm" placeholder="All stages" :options="\App\Enums\LeadStage::options()" :value="$stageValue"
                            :class="$stageValue ? 'w-auto pl-7' : 'w-auto'" aria-label="Lead stage" />
                    </div>
                @endunless

                @if (count($tags))
                    <x-ui.select name="tag" size="sm" class="w-auto max-w-44" placeholder="All tags"
                        :options="array_combine($tags, $tags)" :value="$filters['tag'] ?? null" aria-label="Tag" />
                @endif

                <x-ui.select name="has_email" size="sm" class="w-auto" placeholder="Email: any" :options="['1' => 'Has email', '0' => 'No email']" :value="$filters['has_email'] ?? null" aria-label="Email" />
                <x-ui.select name="has_phone" size="sm" class="w-auto" placeholder="Phone: any" :options="['1' => 'Has phone', '0' => 'No phone']" :value="$filters['has_phone'] ?? null" aria-label="Phone" />

                @if ($currentPage->isAll() && $connectedPages->count() > 1)
                    <x-ui.select name="page_id" size="sm" class="w-auto max-w-48" placeholder="All pages"
                        :options="$connectedPages->mapWithKeys(fn ($p) => [$p->id => $p->page_name ?: 'Page #'.$p->id])->all()" :value="$filters['page_id'] ?? null" aria-label="Page" />
                @endif

                <label @class([
                    'inline-flex h-8 cursor-pointer items-center gap-2 rounded-lg px-3 text-[13px] font-medium ring-1 transition select-none ring-inset',
                    'bg-brand-50 text-brand-700 ring-brand-200' => isset($filters['unread']),
                    'bg-white text-slate-600 ring-line-strong hover:bg-slate-50' => ! isset($filters['unread']),
                ])>
                    <input type="checkbox" name="unread" value="1" @checked(isset($filters['unread'])) class="size-3.5 rounded accent-brand-600">
                    Unread only
                </label>

                <x-slot:right>
                    <x-ui.select name="sort" size="sm" class="w-auto" aria-label="Sort"
                        :options="['recent' => 'Recently active', 'oldest' => 'Least recently active', 'created' => 'Newest contacts', 'name' => 'Name A–Z', 'unread' => 'Most unread']"
                        :value="$filters['sort'] ?? 'recent'" />
                </x-slot:right>
            </x-ui.filter-bar>

            @if ($isBoard)
                @include('admin.contacts._board')
            @else
                {{-- "Select all matching" banner --}}
                <div x-show="allOnPage && total > pageIds.length" x-cloak
                    class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-xl bg-brand-50 px-4 py-2.5 text-[13px] text-brand-800 ring-1 ring-brand-100 ring-inset">
                    <template x-if="! allMatching">
                        <p>All <strong x-text="pageIds.length"></strong> contacts on this page are selected.
                            <button type="button" class="ui-link ml-1" x-on:click="allMatching = true">Select all <span x-text="total.toLocaleString()"></span> matching contacts</button></p>
                    </template>
                    <template x-if="allMatching">
                        <p>All <strong x-text="total.toLocaleString()"></strong> matching contacts are selected.
                            <button type="button" class="ui-link ml-1" x-on:click="clearSelection()">Clear selection</button></p>
                    </template>
                </div>

                <div id="contacts-results">
                    @include('admin.contacts._list')
                </div>

                @include('admin.contacts._bulk-bar')
            @endif
        @endif

        @include('admin.contacts._drawer')
    </div>

    @push('scripts')
        @include('admin.contacts._script')
    @endpush
</x-layouts.app>
