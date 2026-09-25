{{-- Fresh install (no pages yet): a welcoming "connect your first channel" panel. --}}
<section class="ui-card relative overflow-hidden">
    <div class="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-gradient-to-br from-brand-200/60 to-violet-200/40 blur-3xl" aria-hidden="true"></div>
    <div class="relative grid gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-center">
        <div>
            <x-ui.badge tone="brand" icon="sparkles">Welcome to {{ $brand ?? 'DM Pilot' }}</x-ui.badge>
            <h3 class="mt-4 text-xl font-semibold tracking-tight text-ink sm:text-2xl">Put your DMs on autopilot</h3>
            <p class="mt-2 max-w-xl text-[14px] text-slate-600">Connect a Facebook Page or Instagram professional account and your AI assistant will answer customers 24/7, capture leads and hand off to your team when it matters.</p>
            <div class="mt-6 flex flex-wrap gap-2">
                <x-ui.button variant="primary" icon="plus" :href="$links['connect']">Connect your first page</x-ui.button>
                <x-ui.button variant="secondary" icon="bot" :href="$links['bot']">Set up the bot first</x-ui.button>
            </div>
        </div>
        <div class="space-y-3" aria-hidden="true">
            <div class="flex items-center gap-3 rounded-xl bg-white/90 p-3 shadow-card ring-1 ring-line">
                <x-ui.channel-badge platform="facebook" variant="icon" size="md" />
                <div class="min-w-0 flex-1"><p class="text-[13px] font-semibold text-ink">Messenger</p><p class="text-xs text-ink-muted">Facebook Pages</p></div>
                <x-ui.icon name="plus" class="size-4 text-slate-400" />
            </div>
            <div class="flex items-center gap-3 rounded-xl bg-white/90 p-3 shadow-card ring-1 ring-line">
                <x-ui.channel-badge platform="instagram" variant="icon" size="md" />
                <div class="min-w-0 flex-1"><p class="text-[13px] font-semibold text-ink">Instagram</p><p class="text-xs text-ink-muted">Professional accounts</p></div>
                <x-ui.icon name="plus" class="size-4 text-slate-400" />
            </div>
            <div class="rounded-xl bg-slate-900 p-3 text-xs text-slate-300 shadow-card">
                <p><span class="font-semibold text-white">Customer:</span> Do you ship internationally?</p>
                <p class="mt-1.5"><span class="font-semibold text-brand-300">Bot:</span> Yes! We ship worldwide in 5–7 days. Want help picking a size?</p>
            </div>
        </div>
    </div>
</section>
