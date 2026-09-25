{{--
    UI style guide: every component with copy-paste usage. GET /admin/_ui (admin.ui).
    Keep in sync with resources/views/components/ui/* and docs/UI_GUIDE.md.
--}}
@php
    $sections = [
        'foundations' => 'Foundations', 'buttons' => 'Buttons', 'badges' => 'Badges & channels', 'avatars' => 'Avatars',
        'cards' => 'Cards & stats', 'forms' => 'Forms', 'tabs' => 'Tabs', 'overlays' => 'Modal & dropdown',
        'feedback' => 'Alerts & toasts', 'tables' => 'Tables & filters', 'empty' => 'Empty & loading', 'headers' => 'Headers',
        'icons' => 'Icons',
    ];
    $icons = ['home', 'dashboard', 'message', 'messages', 'inbox', 'users', 'user', 'bot', 'zap', 'layers', 'settings', 'sliders', 'plus', 'minus', 'search', 'send', 'pause', 'play', 'check', 'check-circle', 'x', 'x-circle', 'chevron-down', 'chevron-up', 'chevron-left', 'chevron-right', 'chevrons-up-down', 'arrow-left', 'arrow-right', 'arrow-up-right', 'arrow-down-right', 'trending-up', 'trending-down', 'external-link', 'link', 'copy', 'trash', 'edit', 'refresh', 'logout', 'sparkles', 'chart', 'activity', 'filter', 'clock', 'alert-triangle', 'alert-circle', 'info', 'eye', 'eye-off', 'tag', 'phone', 'mail', 'dots', 'dots-vertical', 'menu', 'palette', 'shield', 'webhook', 'globe', 'lock', 'key', 'calendar', 'image', 'paperclip', 'smile', 'book', 'hand', 'download', 'upload', 'facebook', 'messenger', 'instagram'];
    $demoRows = [
        ['name' => 'Alice Smith', 'channel' => 'instagram', 'msg' => 'Do you ship internationally?', 'state' => ['success', 'Bot on'], 'time' => '2m ago'],
        ['name' => 'Bilal Khan', 'channel' => 'facebook', 'msg' => 'I want to talk to a person', 'state' => ['warning', 'Human takeover'], 'time' => '14m ago'],
        ['name' => 'Chen Wei', 'channel' => 'facebook', 'msg' => 'Thanks, that worked!', 'state' => ['neutral', 'Closed'], 'time' => 'Yesterday'],
    ];
@endphp

<x-layouts.app title="UI style guide" width="wide">
    <x-slot:actions>
        <x-ui.button variant="secondary" size="sm" icon="book" href="https://tailwindcss.com/docs" target="_blank" rel="noopener">Tailwind docs</x-ui.button>
    </x-slot:actions>

    <x-ui.page-header eyebrow="Design system" title="Components & patterns"
        description="Everything here is a Blade component in resources/views/components/ui. Copy the snippet under each example. Full guide: docs/UI_GUIDE.md." />

    <div class="grid gap-10 lg:grid-cols-[180px_minmax(0,1fr)]">
        <nav class="hidden lg:block" aria-label="Style guide sections">
            <ul class="sticky top-24 space-y-0.5 text-[13px]">
                @foreach ($sections as $id => $label)
                    <li><a href="#{{ $id }}" class="block rounded-md px-2.5 py-1.5 font-medium text-slate-600 hover:bg-slate-100 hover:text-ink">{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 space-y-14">

            {{-- Foundations --}}
            <section id="foundations" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Foundations" description="Tokens live in resources/css/app.css (@theme). 4px spacing grid; cards use p-5, page sections gap-6/space-y-6." />
                <x-ui.card title="Colour" description="Brand = bg-brand-600 / text-brand-700. Neutrals = slate. Semantic = success / warning / danger.">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
                        @foreach ([
                            ['bg-brand-600', 'brand-600'], ['bg-brand-50 ring-1 ring-brand-100', 'brand-50'], ['bg-ink', 'ink'], ['bg-ink-muted', 'ink-muted'],
                            ['bg-canvas ring-1 ring-line', 'canvas'], ['bg-success-500', 'success-500'], ['bg-warning-500', 'warning-500'], ['bg-danger-500', 'danger-500'],
                            ['bg-messenger', 'messenger'], ['bg-instagram-gradient', 'instagram-gradient'], ['bg-line', 'line'], ['bg-slate-100', 'slate-100'],
                        ] as [$swatch, $name])
                            <div>
                                <div class="{{ $swatch }} h-14 rounded-lg"></div>
                                <p class="mt-1.5 font-mono text-2xs text-ink-muted">{{ $name }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
                <x-ui.card title="Type scale" description="Inter, self-hosted. Body text-sm (14px); secondary text-[13px] text-ink-muted; labels text-2xs uppercase tracking-wider.">
                    <div class="space-y-3">
                        <p class="text-2xl font-semibold tracking-tight">Page title · text-2xl semibold</p>
                        <p class="text-[17px] font-semibold tracking-tight">Top bar title · text-[17px] semibold</p>
                        <p class="text-base font-semibold">Section title · text-base semibold</p>
                        <p class="text-[15px] font-semibold">Card title · text-[15px] semibold</p>
                        <p class="text-sm">Body · text-sm, the default for everything</p>
                        <p class="text-[13px] text-ink-muted">Secondary · text-[13px] text-ink-muted</p>
                        <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Overline · text-2xs uppercase</p>
                        <p><code class="ui-code">ui-code</code> inline code, <a href="#" class="ui-link">ui-link</a> inline link</p>
                    </div>
                </x-ui.card>
            </section>

            {{-- Buttons --}}
            <section id="buttons" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Buttons" description="x-ui.button: variants, sizes, icons, links, loading and disabled. Submit buttons show a spinner automatically while the form submits." />
                <x-ui.card>
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button variant="primary" icon="plus">Primary</x-ui.button>
                            <x-ui.button>Secondary</x-ui.button>
                            <x-ui.button variant="soft" icon="sparkles">Soft</x-ui.button>
                            <x-ui.button variant="ghost">Ghost</x-ui.button>
                            <x-ui.button variant="danger" icon="trash">Danger</x-ui.button>
                            <x-ui.button variant="danger-soft">Danger soft</x-ui.button>
                            <x-ui.button variant="link" icon-right="arrow-right">Link</x-ui.button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button variant="primary" size="xs">Extra small</x-ui.button>
                            <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                            <x-ui.button variant="primary">Medium</x-ui.button>
                            <x-ui.button variant="primary" size="lg">Large</x-ui.button>
                            <x-ui.button icon="refresh" aria-label="Refresh" />
                            <x-ui.button variant="ghost" icon="dots" aria-label="More" />
                            <x-ui.button variant="primary" loading>Saving</x-ui.button>
                            <x-ui.button disabled>Disabled</x-ui.button>
                            <x-ui.button :href="route('admin.dashboard')" icon-right="external-link">As link</x-ui.button>
                        </div>
                    </div>
                    <x-slot:footer>
                        <x-ui.code class="w-full text-left">@verbatim<x-ui.button variant="primary" icon="plus">New</x-ui.button>
<x-ui.button type="submit" variant="primary">Save</x-ui.button>      {{-- auto spinner on submit --}}
<x-ui.button :href="route('admin.meta-accounts.connect')" icon="plus">Connect</x-ui.button>
<x-ui.button variant="ghost" size="sm" icon="trash" aria-label="Delete" />  {{-- icon-only --}}@endverbatim</x-ui.code>
                    </x-slot:footer>
                </x-ui.card>
            </section>

            {{-- Badges --}}
            <section id="badges" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Badges & channels" />
                <x-ui.card>
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach (['neutral', 'brand', 'info', 'success', 'warning', 'danger', 'purple', 'dark'] as $tone)
                                <x-ui.badge :tone="$tone">{{ ucfirst($tone) }}</x-ui.badge>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge tone="success" dot>Bot active</x-ui.badge>
                            <x-ui.badge tone="warning" icon="hand">Human takeover</x-ui.badge>
                            <x-ui.badge tone="danger" dot size="sm">Failed</x-ui.badge>
                            <x-ui.badge tone="brand" icon="sparkles" size="sm">AI</x-ui.badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.channel-badge platform="facebook" />
                            <x-ui.channel-badge platform="instagram" />
                            <x-ui.channel-badge platform="facebook" size="sm" />
                            <x-ui.channel-badge platform="instagram" variant="plain" />
                            <x-ui.channel-badge platform="facebook" variant="icon" size="xs" />
                            <x-ui.channel-badge platform="instagram" variant="icon" size="sm" />
                            <x-ui.channel-badge platform="facebook" variant="icon" />
                            <x-ui.channel-badge platform="instagram" variant="icon" />
                        </div>
                    </div>
                    <x-slot:footer>
                        <x-ui.code class="w-full text-left">@verbatim<x-ui.badge tone="success" dot>Active</x-ui.badge>
<x-ui.channel-badge :platform="$account->platform" />                     {{-- pill --}}
<x-ui.channel-badge platform="instagram" variant="icon" size="sm" />   {{-- round icon --}}@endverbatim</x-ui.code>
                    </x-slot:footer>
                </x-ui.card>
            </section>

            {{-- Avatars --}}
            <section id="avatars" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Avatars" description="Initials get a deterministic colour from the name. Add a channel badge or a status dot." />
                <x-ui.card>
                    <div class="flex flex-wrap items-center gap-4">
                        <x-ui.avatar name="Alice Smith" size="xs" />
                        <x-ui.avatar name="Bilal Khan" size="sm" />
                        <x-ui.avatar name="Chen Wei" />
                        <x-ui.avatar name="Dana Ortiz" size="lg" channel="instagram" />
                        <x-ui.avatar name="Evan Ross" size="xl" channel="facebook" status="online" />
                        <x-ui.avatar name="Acme Store" size="lg" square channel="facebook" />
                        <x-ui.avatar name="Fatima" status="away" />
                    </div>
                    <x-slot:footer>
                        <x-ui.code class="w-full text-left">@verbatim<x-ui.avatar :name="$c->customer_name" :channel="$c->platform" size="lg" status="online" />
<x-ui.avatar :name="$page->page_name" square size="sm" />@endverbatim</x-ui.code>
                    </x-slot:footer>
                </x-ui.card>
            </section>

            {{-- Cards & stats --}}
            <section id="cards" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Cards & stats" description="KPI row: grid gap-4 sm:grid-cols-2 xl:grid-cols-4." />
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.stat label="Conversations" value="1,284" delta="+12.5%" hint="vs last week" icon="message" />
                    <x-ui.stat label="AI resolution rate" value="87%" delta="+3.1%" icon="sparkles" tone="purple" />
                    <x-ui.stat label="Waiting for a human" value="4" delta="+2" invert icon="hand" tone="warning" />
                    <x-ui.stat label="Failed sends (24h)" value="0" delta="0%" icon="alert-triangle" tone="danger" hint="all good" />
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.card title="Card with actions" description="Header, body and footer slots." icon="bot">
                        <x-slot:actions>
                            <x-ui.button size="sm" variant="ghost" icon="edit">Edit</x-ui.button>
                        </x-slot:actions>
                        <p class="text-slate-600">Cards are the main surface. Use <code class="ui-code">:padded="false"</code> for flush lists and tables.</p>
                        <x-slot:footer>
                            <x-ui.button size="sm">Cancel</x-ui.button>
                            <x-ui.button size="sm" variant="primary">Save</x-ui.button>
                        </x-slot:footer>
                    </x-ui.card>
                    <x-ui.card title="Flush list" :padded="false">
                        <ul class="divide-y divide-line">
                            @foreach ($demoRows as $row)
                                <li class="flex items-center gap-3 px-5 py-3">
                                    <x-ui.avatar :name="$row['name']" :channel="$row['channel']" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $row['name'] }}</p>
                                        <p class="truncate text-[13px] text-ink-muted">{{ $row['msg'] }}</p>
                                    </div>
                                    <span class="text-xs text-slate-400">{{ $row['time'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                </div>
                <x-ui.code>@verbatim<x-ui.stat label="Messages today" :value="number_format($n)" delta="+12%" icon="message" hint="vs yesterday" />
<x-ui.card title="Webhook" description="..." icon="webhook">
    <x-slot:actions>...</x-slot:actions>
    ...
    <x-slot:footer>...</x-slot:footer>
</x-ui.card>@endverbatim</x-ui.code>
            </section>

            {{-- Forms --}}
            <section id="forms" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Forms" description="Inputs read old() and $errors automatically. Pass label/hint to auto-wrap in x-ui.field, or wrap manually." />
                <form x-data x-on:submit.prevent="toast('Demo form, nothing was saved', 'info')">
                    <x-ui.card title="Settings form" description="Settings rows use x-ui.field inline; the save bar sticks to the bottom.">
                        <div class="space-y-6">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui.input name="demo_name" label="Bot name" value="Ava" hint="Shown to customers" required />
                                <x-ui.input name="demo_email" type="email" label="Alert email" icon="mail" placeholder="team@acme.com" optional />
                                <x-ui.select name="demo_model" label="Model" :options="['gpt-4o-mini' => 'GPT-4o mini', 'claude-sonnet' => 'Claude Sonnet']" value="gpt-4o-mini" />
                                <x-ui.field label="Reply delay" for="demo_delay" hint="Seconds before the bot answers">
                                    <x-ui.input name="demo_delay" id="demo_delay" type="number" value="2">
                                        <x-slot:trailing>sec</x-slot:trailing>
                                    </x-ui.input>
                                </x-ui.field>
                            </div>
                            <x-ui.field label="Invalid example" for="demo_bad" error="This field is required.">
                                <x-ui.input name="demo_bad" id="demo_bad" aria-invalid="true" />
                            </x-ui.field>
                            <x-ui.textarea name="demo_prompt" label="System prompt" rows="4" autosize :counter="500" maxlength="500" hint="Auto-grows; counter turns red past the limit." value="You are a friendly assistant for Acme Store." />
                            <div class="h-px bg-line"></div>
                            <x-ui.field inline label="Bot enabled" hint="Reply automatically to new messages">
                                <x-ui.toggle name="demo_enabled" :checked="true" label="Enabled" description="Turn off to pause AI replies on every page." />
                            </x-ui.field>
                            <x-ui.field inline label="Hand-off" hint="When should a human take over?">
                                <div class="space-y-3">
                                    <x-ui.checkbox type="radio" name="demo_handoff" value="ask" :checked="true" label="When the customer asks" />
                                    <x-ui.checkbox type="radio" name="demo_handoff" value="never" label="Never" />
                                    <x-ui.checkbox name="demo_notify" label="Email me on hand-off" description="Sent to the alert email above." />
                                </div>
                            </x-ui.field>
                            <x-ui.toggle size="sm" label="Small toggle" />
                        </div>
                    </x-ui.card>
                    <x-ui.save-bar />
                </form>
                <x-ui.code>@verbatim<form method="POST" action="{{ route('admin.bot-settings.update') }}" data-warn-unsaved>
    @csrf @method('PUT')
    <x-ui.card title="Behaviour">
        <div class="space-y-6">
            <x-ui.input name="bot_name" label="Bot name" :value="$settings->bot_name" required />
            <x-ui.textarea name="system_prompt" label="System prompt" autosize :counter="4000" maxlength="4000" :value="$settings->system_prompt" />
            <x-ui.field inline label="Enabled" hint="...">
                <x-ui.toggle name="enabled" :checked="$settings->enabled" label="Bot replies automatically" />
            </x-ui.field>
        </div>
    </x-ui.card>
    <x-ui.save-bar />
</form>@endverbatim</x-ui.code>
            </section>

            {{-- Tabs --}}
            <section id="tabs" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Tabs" description="Client tabs (Alpine, arrow keys, optional #hash) or link tabs for server navigation." />
                <x-ui.card>
                    <x-ui.tabs :tabs="['general' => ['label' => 'General', 'icon' => 'sliders'], 'knowledge' => ['label' => 'Knowledge', 'icon' => 'book', 'count' => 12], 'handoff' => 'Hand-off']" default="general">
                        <x-ui.tab-panel name="general" default><p class="text-slate-600">General settings panel.</p></x-ui.tab-panel>
                        <x-ui.tab-panel name="knowledge"><p class="text-slate-600">12 FAQ entries.</p></x-ui.tab-panel>
                        <x-ui.tab-panel name="handoff"><p class="text-slate-600">Hand-off rules.</p></x-ui.tab-panel>
                    </x-ui.tabs>
                    <div class="mt-8">
                        <x-ui.tabs variant="pills" :tabs="['all' => 'All', 'open' => 'Open', 'closed' => 'Closed']" />
                    </div>
                    <div class="mt-8">
                        <x-ui.tabs :tabs="[
                            ['label' => 'Global', 'href' => route('admin.ui'), 'active' => true],
                            ['label' => 'Per page', 'href' => '#', 'active' => false],
                        ]" />
                    </div>
                    <x-slot:footer>
                        <x-ui.code class="w-full text-left">@verbatim<x-ui.tabs :tabs="['general' => 'General', 'faq' => ['label' => 'FAQ', 'count' => 12]]" default="general" hash>
    <x-ui.tab-panel name="general" default>...</x-ui.tab-panel>
    <x-ui.tab-panel name="faq">...</x-ui.tab-panel>
</x-ui.tabs>@endverbatim</x-ui.code>
                    </x-slot:footer>
                </x-ui.card>
            </section>

            {{-- Overlays --}}
            <section id="overlays" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Modal & dropdown" />
                <x-ui.card>
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button x-data x-on:click="$dispatch('open-modal', 'demo-modal')">Open modal</x-ui.button>
                        <x-ui.button variant="danger-soft" icon="trash" x-data x-on:click="$dispatch('open-modal', 'demo-confirm')">Confirm delete</x-ui.button>
                        <x-ui.dropdown align="left">
                            <x-slot:trigger><x-ui.button icon-right="chevron-down">Dropdown</x-ui.button></x-slot:trigger>
                            <x-ui.dropdown-divider label="Conversation" />
                            <x-ui.dropdown-item icon="edit">Rename</x-ui.dropdown-item>
                            <x-ui.dropdown-item icon="tag" description="Add a label">Tag</x-ui.dropdown-item>
                            <x-ui.dropdown-item icon="check" active>Selected item</x-ui.dropdown-item>
                            <x-ui.dropdown-divider />
                            <x-ui.dropdown-item icon="trash" danger>Delete</x-ui.dropdown-item>
                        </x-ui.dropdown>
                        <x-ui.tooltip text="Tooltips work on hover and focus"><x-ui.button variant="ghost" icon="info" aria-label="Info" /></x-ui.tooltip>
                        <x-ui.copy-button value="https://example.com/webhooks/meta" label="Copy URL" />
                    </div>
                    <x-slot:footer>
                        <x-ui.code class="w-full text-left">@verbatim<x-ui.button x-data x-on:click="$dispatch('open-modal', 'delete-page')">Delete</x-ui.button>
<x-ui.modal name="delete-page" title="Delete this page?" icon="trash" tone="danger" size="sm">
    ...
    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <form method="POST" action="...">@csrf @method('DELETE')
            <x-ui.button type="submit" variant="danger">Delete</x-ui.button></form>
    </x-slot:footer>
</x-ui.modal>@endverbatim</x-ui.code>
                    </x-slot:footer>
                </x-ui.card>

                <x-ui.modal name="demo-modal" title="Invite a teammate" description="They will get an email with a sign-in link." icon="users">
                    <div class="space-y-4">
                        <x-ui.input name="invite_email" label="Email" type="email" placeholder="name@company.com" />
                        <x-ui.select name="invite_role" label="Role" :options="['admin' => 'Admin', 'agent' => 'Agent']" />
                    </div>
                    <x-slot:footer>
                        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button variant="primary" x-on:click="close(); toast('Invite sent')">Send invite</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
                <x-ui.modal name="demo-confirm" title="Delete Acme Store?" description="Its conversations and messages are removed too. This cannot be undone." icon="trash" tone="danger" size="sm">
                    <x-slot:footer>
                        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button variant="danger" x-on:click="close(); toast('Deleted (demo)', 'error')">Delete</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            </section>

            {{-- Feedback --}}
            <section id="feedback" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Alerts & toasts" description="session('success'|'status'|'error'|'warning'|'info') become toasts automatically. From JS: toast('Saved') or $dispatch('toast', {message, type})." />
                <div class="space-y-3">
                    <x-ui.alert tone="info" title="Heads up">Instagram webhooks require the Meta app to be Live.</x-ui.alert>
                    <x-ui.alert tone="success">Webhooks subscribed for Acme Store.</x-ui.alert>
                    <x-ui.alert tone="warning" title="Outside the 24h window" dismissible>Meta may reject standard messages to this customer.</x-ui.alert>
                    <x-ui.alert tone="danger" title="Token expired">
                        Reconnect the page to keep replying.
                        <x-slot:actions><x-ui.button size="sm" variant="danger">Reconnect</x-ui.button></x-slot:actions>
                    </x-ui.alert>
                    <x-ui.alert tone="brand" title="New: AI summaries">Get a one-line summary of every conversation.</x-ui.alert>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button x-data x-on:click="toast('Settings saved')" icon="check">Success toast</x-ui.button>
                    <x-ui.button x-data x-on:click="toast('Could not reach Meta', 'error')" icon="alert-circle">Error toast</x-ui.button>
                    <x-ui.button x-data x-on:click="toast('Token expires in 3 days', 'warning')" icon="alert-triangle">Warning toast</x-ui.button>
                    <x-ui.button x-data x-on:click="$dispatch('toast', { message: 'Syncing pages…', type: 'info' })" icon="info">Info toast</x-ui.button>
                </div>
            </section>

            {{-- Tables --}}
            <section id="tables" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Tables & filters" description="Filter bar above, table in a card, pagination in the footer (->links('components.ui.pagination'))." />
                <x-ui.filter-bar :action="route('admin.ui')" search="demo_q" placeholder="Search name or ID">
                    <x-ui.select name="demo_channel" size="sm" class="w-auto" placeholder="All channels" :options="['facebook' => 'Messenger', 'instagram' => 'Instagram']" :value="request('demo_channel')" />
                    <x-ui.select name="demo_state" size="sm" class="w-auto" placeholder="Any state" :options="['bot' => 'Bot on', 'human' => 'Human takeover']" :value="request('demo_state')" />
                    <x-slot:right><x-ui.button size="sm" icon="download">Export</x-ui.button></x-slot:right>
                </x-ui.filter-bar>
                <x-ui.table hoverable>
                    <x-slot:head>
                        <tr><th>Customer</th><th>Last message</th><th>State</th><th class="text-right">Activity</th><th class="w-0"></th></tr>
                    </x-slot:head>
                    @foreach ($demoRows as $row)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-ui.avatar :name="$row['name']" :channel="$row['channel']" size="sm" />
                                    <span class="font-medium text-ink">{{ $row['name'] }}</span>
                                </div>
                            </td>
                            <td class="max-w-xs truncate text-ink-muted">{{ $row['msg'] }}</td>
                            <td><x-ui.badge :tone="$row['state'][0]" dot>{{ $row['state'][1] }}</x-ui.badge></td>
                            <td class="text-right whitespace-nowrap text-ink-muted">{{ $row['time'] }}</td>
                            <td><x-ui.button variant="ghost" size="xs" icon="chevron-right" aria-label="Open" /></td>
                        </tr>
                    @endforeach
                    <x-slot:footer>
                        <p class="text-[13px] text-ink-muted">Showing <span class="font-medium text-ink">1–3</span> of <span class="font-medium text-ink">3</span></p>
                    </x-slot:footer>
                </x-ui.table>
                <x-ui.code>@verbatim<x-ui.filter-bar :action="route('admin.contacts.index')" search="q">
    <x-ui.select name="platform" size="sm" class="w-auto" placeholder="All channels" :options="[...]" :value="request('platform')" />
</x-ui.filter-bar>
<x-ui.table hoverable>
    <x-slot:head><tr><th>Customer</th><th class="text-right">Activity</th></tr></x-slot:head>
    @forelse ($rows as $row) <tr><td>...</td><td class="text-right">...</td></tr>
    @empty <tr><td colspan="2"><x-ui.empty-state compact icon="users" title="No contacts yet" /></td></tr>
    @endforelse
    <x-slot:footer>{{ $rows->links('components.ui.pagination') }}</x-slot:footer>
</x-ui.table>@endverbatim</x-ui.code>
            </section>

            {{-- Empty + loading --}}
            <section id="empty" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Empty & loading states" />
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-ui.card :padded="false">
                        <x-ui.empty-state icon="users" title="No contacts yet" description="People appear here after they message one of your pages." compact>
                            <x-ui.button variant="primary" icon="plus" :href="route('admin.meta-accounts.connect')">Connect a page</x-ui.button>
                        </x-ui.empty-state>
                    </x-ui.card>
                    <x-ui.card title="Loading">
                        <div class="space-y-5">
                            <x-ui.skeleton avatar lines="2" />
                            <x-ui.skeleton avatar lines="2" />
                            <x-ui.skeleton lines="3" />
                            <div class="flex items-center gap-3 text-[13px] text-ink-muted"><x-ui.spinner size="sm" class="text-brand-600" /> Loading conversations…</div>
                        </div>
                    </x-ui.card>
                </div>
            </section>

            {{-- Headers --}}
            <section id="headers" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Page & section headers" description="The layout's top bar already shows the page title; add x-ui.page-header for description, back link or avatar." />
                <x-ui.card>
                    <x-ui.page-header title="Alice Smith" description="Customer since May 2026 · 23 messages" :back="route('admin.ui')" back-label="Live Chat" class="!mb-0">
                        <x-slot:leading><x-ui.avatar name="Alice Smith" size="xl" channel="instagram" /></x-slot:leading>
                        <x-slot:meta><x-ui.badge tone="success" dot>Bot on</x-ui.badge><x-ui.badge>Open</x-ui.badge></x-slot:meta>
                        <x-ui.button icon="pause">Pause bot</x-ui.button>
                        <x-ui.button variant="primary" icon="hand">Take over</x-ui.button>
                    </x-ui.page-header>
                    <div class="mt-8 border-t border-line pt-6">
                        <x-ui.section-header title="Connected pages" :count="3" description="Pages the bot replies on.">
                            <x-ui.button size="sm" icon="plus">Add</x-ui.button>
                        </x-ui.section-header>
                    </div>
                </x-ui.card>
            </section>

            {{-- Icons --}}
            <section id="icons" class="scroll-mt-24 space-y-5">
                <x-ui.section-header title="Icons" description="x-ui.icon with a name and a size class. Lucide-style paths using currentColor; add more in components/ui/icon.blade.php." />
                <x-ui.card>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
                        @foreach ($icons as $icon)
                            <div class="flex flex-col items-center gap-2 rounded-lg p-3 text-slate-600 hover:bg-slate-50">
                                <x-ui.icon :name="$icon" class="size-5" />
                                <span class="w-full truncate text-center font-mono text-2xs text-ink-muted">{{ $icon }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </section>
        </div>
    </div>
</x-layouts.app>
