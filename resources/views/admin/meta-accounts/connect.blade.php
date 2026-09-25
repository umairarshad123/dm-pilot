<x-layouts.app title="Connect a channel" width="narrow">
    <x-ui.page-header title="Connect Messenger & Instagram" description="Sign in with the Facebook account that manages your Pages. You choose exactly which Pages to connect."
        :back="route('admin.meta-accounts.index')" back-label="Pages & Channels" />

    @include('admin.meta-accounts._stepper', ['step' => 1])

    <div class="space-y-5" x-data="{ token: location.hash === '#token' || @js($errors->has('user_token')) }">
        {{-- Method 1: Facebook Login for Business --}}
        <section class="ui-card relative overflow-hidden">
            <div class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-messenger/10 blur-3xl" aria-hidden="true"></div>
            <div class="relative p-6 sm:p-7">
                <div class="flex items-start gap-4">
                    <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[#1877F2] text-white shadow-sm">
                        <x-ui.icon name="facebook" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-ink">Continue with Facebook</h2>
                            <x-ui.badge tone="brand" size="sm">Recommended</x-ui.badge>
                        </div>
                        <p class="mt-1 text-sm text-ink-muted">A secure Facebook pop-up asks which Pages and Instagram accounts to share. Tokens never leave the server.</p>
                    </div>
                </div>

                <ul class="mt-5 grid gap-2 text-[13px] text-slate-700 sm:grid-cols-2">
                    @foreach (['Page tokens that never expire', 'Linked Instagram accounts included', 'Webhooks subscribed for you', 'Pick only the client Pages you want'] as $point)
                        <li class="flex items-center gap-2"><x-ui.icon name="check-circle" class="size-4 text-success-600" /> {{ $point }}</li>
                    @endforeach
                </ul>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @if ($oauthReady)
                        <a href="{{ route('admin.meta-accounts.oauth.redirect') }}"
                            class="inline-flex h-11 items-center gap-2.5 rounded-xl bg-[#1877F2] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#166fe0] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1877F2]">
                            <x-ui.icon name="facebook" class="size-5" /> Continue with Facebook
                        </a>
                    @else
                        <span class="inline-flex h-11 cursor-not-allowed items-center gap-2.5 rounded-xl bg-slate-200 px-5 text-sm font-semibold text-slate-500" aria-disabled="true">
                            <x-ui.icon name="facebook" class="size-5" /> Continue with Facebook
                        </span>
                        <p class="text-[13px] text-warning-700">Set <code class="ui-code">META_APP_ID</code> and <code class="ui-code">META_APP_SECRET</code> in <code class="ui-code">.env</code> first.</p>
                    @endif
                </div>

                <details class="group mt-6 rounded-xl bg-slate-50 ring-1 ring-line ring-inset">
                    <summary class="flex cursor-pointer list-none items-center gap-2 px-4 py-3 text-[13px] font-medium text-slate-700">
                        <x-ui.icon name="info" class="size-4 text-slate-400" /> Permissions and one-time setup
                        <x-ui.icon name="chevron-down" class="ml-auto size-4 text-slate-400 transition-transform group-open:rotate-180" />
                    </summary>
                    <div class="space-y-4 border-t border-line px-4 py-4 text-[13px] text-ink-muted">
                        <div>
                            <p class="font-medium text-slate-700">{{ $usesConfigId ? 'Uses your Facebook Login for Business configuration (META_LOGIN_CONFIG_ID).' : 'Permissions requested:' }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($scopes as $scope)
                                    <code class="ui-code">{{ $scope }}</code>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="font-medium text-slate-700">Add this to <span class="text-ink">Facebook Login for Business → Settings → Valid OAuth Redirect URIs</span>:</p>
                            <div class="mt-2 flex items-center gap-2">
                                <code class="ui-code min-w-0 flex-1 truncate py-1.5">{{ $redirectUri }}</code>
                                <x-ui.copy-button :value="$redirectUri" size="xs" icon-only label="Copy redirect URI" />
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </section>

        {{-- Method 2: paste a token --}}
        <section id="token" class="ui-card">
            <button type="button" class="flex w-full items-center gap-4 p-5 text-left sm:px-7" x-on:click="token = ! token" x-bind:aria-expanded="token">
                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><x-ui.icon name="key" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-ink">Paste a user access token</span>
                    <span class="block text-[13px] text-ink-muted">From the Graph API Explorer. Useful when the login pop-up is not set up yet.</span>
                </span>
                <x-ui.icon name="chevron-down" class="size-4 text-slate-400 transition-transform" x-bind:class="{ 'rotate-180': token }" />
            </button>
            <div x-show="token" x-collapse @unless ($errors->has('user_token')) x-cloak @endunless>
                <form method="POST" action="{{ route('admin.meta-accounts.connect.pages') }}" autocomplete="off" class="space-y-5 border-t border-line p-5 sm:px-7">
                    @csrf
                    <ol class="list-decimal space-y-1 pl-4 text-[13px] text-ink-muted">
                        <li>Open <a class="ui-link" href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> and select your app.</li>
                        <li>Add the permissions: {{ implode(', ', $scopes) }}.</li>
                        <li>Click <span class="font-medium text-slate-700">Generate Access Token</span>, approve, then paste it below.</li>
                    </ol>
                    <x-ui.field label="User access token" for="user_token" name="user_token" hint="Exchanged for a long-lived token on the server, used once to list your Pages, then discarded.">
                        <x-ui.input type="password" name="user_token" id="user_token" value="" icon="lock" autocomplete="off" spellcheck="false" placeholder="EAAG…" />
                    </x-ui.field>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="primary" icon-right="arrow-right">Find my Pages</x-ui.button>
                    </div>
                </form>
            </div>
        </section>

        <p class="flex items-center justify-center gap-1.5 text-center text-xs text-ink-muted">
            <x-ui.icon name="lock" class="size-3.5" /> Tokens are encrypted at rest and never shown in the dashboard.
            <a href="{{ route('admin.meta-accounts.create') }}" class="ui-link ml-1">Add an account manually</a>
        </p>
    </div>
</x-layouts.app>
