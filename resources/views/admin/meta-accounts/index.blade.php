<x-layouts.app title="Pages & Channels">
    <x-slot:actions>
        <x-ui.button variant="primary" icon="plus" :href="route('admin.meta-accounts.connect')">Connect a channel</x-ui.button>
    </x-slot:actions>

    <x-ui.page-header title="Pages & Channels" description="Facebook Pages and Instagram accounts your AI assistant answers on.">
        <x-ui.button variant="ghost" size="sm" icon="key" :href="route('admin.meta-accounts.create')">Add manually</x-ui.button>
        <x-ui.button variant="ghost" size="sm" icon="settings" :href="route('admin.settings.index')">Connection settings</x-ui.button>
    </x-ui.page-header>

    @if ($total === 0)
        <div class="ui-card relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,var(--color-brand-50),transparent_65%)]" aria-hidden="true"></div>
            <x-ui.empty-state class="relative" icon="layers" title="Connect your first channel"
                description="Link a Facebook Page (and its Instagram account) and the assistant starts answering DMs within minutes.">
                <x-ui.button variant="primary" icon="facebook" :href="route('admin.meta-accounts.connect')">Continue with Facebook</x-ui.button>
                <x-ui.button icon="key" :href="route('admin.meta-accounts.connect').'#token'">Paste a token</x-ui.button>
            </x-ui.empty-state>
            <div class="relative grid gap-px border-t border-line bg-line sm:grid-cols-3">
                @foreach ([
                    ['facebook', 'Log in with Facebook', 'Pick the Pages you manage. Nothing is selected until you choose.'],
                    ['webhook', 'We subscribe webhooks', 'New DMs reach the assistant instantly, on Messenger and Instagram.'],
                    ['bot', 'Tune the bot', 'Set its tone, FAQs and hand-over rules in Bot Studio.'],
                ] as [$icon, $title, $text])
                    <div class="flex items-start gap-3 bg-white p-5">
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><x-ui.icon :name="$icon" class="size-4" /></span>
                        <div><p class="text-sm font-semibold text-ink">{{ $title }}</p><p class="mt-0.5 text-[13px] text-ink-muted">{{ $text }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <x-ui.stat label="Channels" :value="$stats['channels']" icon="layers" tone="brand" :hint="$stats['active'].' active'" />
                <x-ui.stat label="Active" :value="$stats['active']" icon="check-circle" tone="success" hint="Bot can reply" />
                <x-ui.stat label="Need attention" :value="$stats['attention']" icon="alert-triangle" :tone="$stats['attention'] > 0 ? 'warning' : 'neutral'" hint="Token or webhook issues" />
                <x-ui.stat label="Messages (7 days)" :value="number_format($stats['messages_7d'])" icon="messages" tone="purple" hint="All channels" />
            </div>

            <div class="grid items-start gap-5 xl:grid-cols-2">
                @foreach ($groups as $group)
                    <article class="ui-card overflow-hidden transition-shadow duration-200 hover:shadow-card-hover" data-channel-group>
                        @if ($group['page'])
                            <div class="p-5">
                                @include('admin.meta-accounts._channel', ['card' => $group['page'], 'compact' => false])
                            </div>

                            @forelse ($group['instagram'] as $ig)
                                <div class="relative border-t border-line bg-gradient-to-b from-slate-50/80 to-white p-5 pl-6">
                                    <span class="absolute inset-y-0 left-0 w-1 bg-instagram-gradient" aria-hidden="true"></span>
                                    <p class="mb-3 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Linked Instagram</p>
                                    @include('admin.meta-accounts._channel', ['card' => $ig, 'compact' => true])
                                </div>
                            @empty
                                <div class="border-t border-dashed border-line bg-slate-50/60 px-5 py-3" x-data="{ open: false }">
                                    <button type="button" class="flex w-full items-center gap-3 text-left" x-on:click="open = ! open" x-bind:aria-expanded="open">
                                        <span class="inline-flex size-7 items-center justify-center rounded-lg bg-white text-slate-400 ring-1 ring-line"><x-ui.icon name="instagram" class="size-3.5" /></span>
                                        <span class="flex-1 text-[13px] text-ink-muted"><span class="font-medium text-slate-700">Instagram not linked.</span> Answer Instagram DMs from here too.</span>
                                        <x-ui.icon name="chevron-down" class="size-4 text-slate-400 transition-transform" x-bind:class="{ 'rotate-180': open }" />
                                    </button>
                                    <ol x-show="open" x-collapse x-cloak class="mt-3 ml-10 list-decimal space-y-1 pl-4 text-[13px] text-ink-muted">
                                        <li>In the Instagram app: <span class="font-medium text-slate-700">Settings → Account type and tools</span> → switch to a Professional (Business or Creator) account.</li>
                                        <li>On Facebook, open the Page: <span class="font-medium text-slate-700">Settings → Linked accounts → Instagram → Connect</span>.</li>
                                        <li>In Instagram: <span class="font-medium text-slate-700">Settings → Messages → Connected tools</span> → allow access to messages.</li>
                                        <li><a href="{{ route('admin.meta-accounts.connect') }}" class="ui-link">Connect again</a> and pick this Page: its Instagram account is added automatically.</li>
                                    </ol>
                                </div>
                            @endforelse
                        @else
                            @foreach ($group['instagram'] as $ig)
                                <div class="relative p-5">
                                    <span class="absolute inset-x-0 top-0 h-1 bg-instagram-gradient" aria-hidden="true"></span>
                                    @include('admin.meta-accounts._channel', ['card' => $ig, 'compact' => false])
                                </div>
                            @endforeach
                        @endif
                    </article>
                @endforeach

                <a href="{{ route('admin.meta-accounts.connect') }}"
                    class="group flex min-h-40 flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-line p-6 text-center transition hover:border-brand-300 hover:bg-brand-50/40">
                    <span class="inline-flex size-11 items-center justify-center rounded-xl bg-white text-brand-600 shadow-xs ring-1 ring-line transition group-hover:scale-105">
                        <x-ui.icon name="plus" class="size-5" />
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">Connect another Page</span>
                        <span class="mt-0.5 block text-[13px] text-ink-muted">Messenger and Instagram, in a few clicks</span>
                    </span>
                </a>
            </div>
        </div>
    @endif
</x-layouts.app>
