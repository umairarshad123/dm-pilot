{{-- Webhook connection details (callback URL + masked verify token), collapsed unless setup is incomplete. --}}
<section id="connection-details" class="ui-card min-w-0 scroll-mt-24 self-start"
    x-data="{ open: @js(! $checklist['complete']), reveal: false }"
    x-init="if (location.hash === '#connection-details') open = true"
    x-on:hashchange.window="if (location.hash === '#connection-details') open = true">
    <button type="button" class="flex w-full items-center gap-3 rounded-(--radius-card) px-5 py-4 text-left" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="connection-body">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-ui.icon name="webhook" class="size-[18px]" /></span>
        <span class="min-w-0 flex-1">
            <span class="block text-[15px] font-semibold text-ink">Connection details</span>
            <span class="mt-0.5 block truncate text-[13px] text-ink-muted">Webhook callback URL and verify token for the Meta dashboard</span>
        </span>
        <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="{ 'rotate-180': open }" />
    </button>

    <div id="connection-body" x-show="open" x-collapse @if ($checklist['complete']) x-cloak @endif>
        <div class="space-y-5 border-t border-line px-5 py-5">
            <div>
                <label for="callback-url" class="ui-label">Callback URL</label>
                <div class="mt-1.5 flex gap-2">
                    <input id="callback-url" type="text" class="ui-input font-mono text-[13px]" value="{{ $callbackUrl }}" readonly>
                    <x-ui.copy-button target="#callback-url" size="md" icon-only label="Copy callback URL" />
                </div>
            </div>

            <div>
                <label for="verify-token" class="ui-label">Verify token</label>
                @if ($verifyToken !== '')
                    <div class="mt-1.5 flex gap-2">
                        <input id="verify-token" x-bind:type="reveal ? 'text' : 'password'" type="password" class="ui-input font-mono text-[13px]" value="{{ $verifyToken }}" readonly autocomplete="off">
                        <x-ui.button size="md" variant="secondary" x-on:click="reveal = ! reveal" x-bind:aria-label="reveal ? 'Hide token' : 'Reveal token'" aria-label="Reveal token">
                            <span x-show="! reveal"><x-ui.icon name="eye" class="size-4" /></span>
                            <span x-show="reveal" x-cloak><x-ui.icon name="eye-off" class="size-4" /></span>
                        </x-ui.button>
                        <x-ui.copy-button target="#verify-token" size="md" icon-only label="Copy verify token" />
                    </div>
                @else
                    <x-ui.alert tone="danger" class="mt-1.5" title="META_VERIFY_TOKEN is not set">Add a random string to .env and run php artisan config:clear.</x-ui.alert>
                @endif
            </div>

            <div class="rounded-xl bg-slate-50 p-4 text-[13px] text-slate-600 ring-1 ring-line ring-inset">
                <p class="font-semibold text-ink">In the Meta dashboard</p>
                <ol class="mt-2 list-decimal space-y-1 pl-5">
                    <li>Webhooks → configure the <code class="ui-code">page</code> and <code class="ui-code">instagram</code> objects with this URL and token.</li>
                    <li>Subscribe to <code class="ui-code">messages</code> (plus <code class="ui-code">messaging_postbacks</code> for Messenger).</li>
                    <li>On each page in <a class="ui-link" href="{{ $links['pages'] }}">Pages &amp; Channels</a>, click "Subscribe webhooks", then "Test connection".</li>
                    <li>Keep a queue worker running (<code class="ui-code">php artisan queue:work</code>): Meta needs a 200 within 5 seconds.</li>
                </ol>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted">
                <span>Graph API version <span class="font-mono font-medium text-slate-700">{{ $graphVersion }}</span></span>
                <span>
                    @if ($stats['last_webhook_at'])
                        Last event {{ \Illuminate\Support\Carbon::parse($stats['last_webhook_at'])->diffForHumans() }} ·
                    @endif
                    <a class="ui-link" href="{{ $links['webhooks'] }}">Webhook log</a>
                </span>
            </div>
        </div>
    </div>
</section>
