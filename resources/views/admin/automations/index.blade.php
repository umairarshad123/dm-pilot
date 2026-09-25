{{--
    Automations. Scope follows the page switcher ("All pages" = global rules).
    Controller: App\Http\Controllers\Admin\AutomationController (+ AutomationToolsController, MessengerProfileController).
    Alpine factories: ./_scripts.blade.php.
--}}
@php
    $isPage = $account !== null;
    $pageName = $isPage ? ($account->page_name ?: 'Page #'.$account->id) : null;
    $variables = ['{first_name}' => 'First name', '{name}' => 'Full name', '{page_name}' => 'Page name'];

    $matchTypes = [
        'contains' => ['label' => 'Contains', 'help' => 'The keyword appears as a whole word anywhere: “what’s the price?” matches price (but “pricey” does not).'],
        'exact' => ['label' => 'Exact', 'help' => 'The whole message equals the keyword, ignoring case and punctuation: “Price?” matches price.'],
        'starts_with' => ['label' => 'Starts with', 'help' => 'The message begins with the keyword: “price list please” matches price list.'],
        'regex' => ['label' => 'Regex', 'help' => 'Advanced: each keyword is a regular expression (no delimiters), case-insensitive.'],
    ];

    $starters = [
        ['icon' => 'tag', 'title' => 'Pricing', 'hint' => 'price, cost, how much', 'rule' => [
            'name' => 'Pricing', 'match_type' => 'contains', 'keywords' => ['price', 'cost', 'how much', 'rates'],
            'reply_text' => "Hi {first_name}! Our prices start at … (edit me). Would you like the full price list?",
        ]],
        ['icon' => 'clock', 'title' => 'Opening hours', 'hint' => 'open, hours, timing', 'rule' => [
            'name' => 'Opening hours', 'match_type' => 'contains', 'keywords' => ['open', 'hours', 'timing', 'closing time'],
            'reply_text' => "We're open Monday to Saturday, 9am to 8pm (edit me). See you soon at {page_name}!",
        ]],
        ['icon' => 'globe', 'title' => 'Location', 'hint' => 'address, location, where', 'rule' => [
            'name' => 'Location', 'match_type' => 'contains', 'keywords' => ['address', 'location', 'where are you'],
            'reply_text' => 'You can find us at … (edit me). Here is the map link: …',
        ]],
        ['icon' => 'hand', 'title' => 'Talk to a human', 'hint' => 'human, agent, real person', 'rule' => [
            'name' => 'Talk to a human', 'match_type' => 'contains', 'keywords' => ['human', 'agent', 'real person', 'talk to someone'],
            'reply_text' => 'Sure {first_name}! A team member will reply here shortly.',
        ]],
    ];

    $routes = [
        'store' => route('admin.automations.store'),
        'update' => route('admin.automations.update', '__ID__'),
        'destroy' => route('admin.automations.destroy', '__ID__'),
        'duplicate' => route('admin.automations.duplicate', '__ID__'),
        'toggle' => route('admin.automations.toggle', '__ID__'),
        'reorder' => route('admin.automations.reorder'),
        'welcome' => route('admin.automations.welcome'),
        'regex' => route('admin.automations.api.regex'),
        'preview' => route('admin.automations.api.preview'),
        'test' => route('admin.automations.api.test'),
    ];

    $config = [
        'routes' => $routes,
        'accountId' => $account?->id,
        'pageName' => $pageName,
        'rules' => $rules,
        'starters' => array_column($starters, 'rule'),
        'matchTypes' => $matchTypes,
    ];
@endphp

<x-layouts.app title="Automations">
    <x-slot:actions>
        <x-ui.button size="sm" icon="bot" :href="route('admin.bot-settings.edit')">Bot Studio</x-ui.button>
        <x-ui.button size="sm" variant="primary" icon="plus" x-data x-on:click="$dispatch('new-rule')">New keyword rule</x-ui.button>
    </x-slot:actions>

    <div class="space-y-6" x-data="automations(@js($config))" x-on:new-rule.window="openCreate()" x-on:prefill-rule.window="openCreate($event.detail)">
        {{-- Scope --}}
        <div class="flex flex-col gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:flex-row sm:items-center sm:p-5">
            <div class="flex min-w-0 flex-1 items-center gap-3.5">
                @if ($isPage)
                    <x-ui.avatar :name="$pageName" :channel="$account->platform" size="lg" />
                @else
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white shadow-xs"><x-ui.icon name="layers" class="size-6" /></span>
                @endif
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-base font-semibold text-ink">{{ $isPage ? $pageName : 'All pages' }}</h2>
                        @if ($isPage)
                            <x-ui.channel-badge :platform="$account->platform" size="sm" />
                        @else
                            <x-ui.badge tone="dark" size="sm">Global rules</x-ui.badge>
                        @endif
                    </div>
                    <p class="mt-1 text-[13px] text-ink-muted">
                        @if ($isPage)
                            Rules for this page run first, then the rules that apply to all pages. A matching rule replies instantly instead of the AI.
                        @else
                            These rules run on every page. Pick a page in the switcher to add rules that only apply there.
                        @endif
                    </p>
                </div>
            </div>
            <x-ui.button size="sm" icon="chevrons-up-down" x-on:click="$dispatch('open-page-switcher')">Switch page</x-ui.button>
        </div>

        @unless ($automationsEnabled)
            <x-ui.alert tone="warning" title="Automations are switched off on the server">
                BOT_AUTOMATIONS_ENABLED=false: rules are saved but never sent; the AI answers everything.
            </x-ui.alert>
        @endunless

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="min-w-0 space-y-6">
                @include('admin.automations._welcome')
                @include('admin.automations._rules')
            </div>

            <div class="min-w-0 space-y-6 xl:sticky xl:top-24">
                @include('admin.automations._test')
                @include('admin.automations._profile')
            </div>
        </div>

        @include('admin.automations._editor')

        <x-ui.modal name="delete-rule" title="Delete this rule?" icon="trash" tone="danger" size="sm">
            <p>“<span class="font-medium text-ink" x-text="toDelete?.name"></span>” stops replying immediately. This cannot be undone.</p>
            <x-slot:footer>
                <x-ui.button x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button variant="danger" icon="trash" x-on:click="destroy()">Delete rule</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </div>

    @push('scripts')
        @include('admin.automations._scripts')
    @endpush
</x-layouts.app>
