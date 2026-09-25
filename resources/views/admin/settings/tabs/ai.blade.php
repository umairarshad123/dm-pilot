{{-- Settings → AI providers --}}
<div class="space-y-6">
    <x-ui.section-header title="AI providers" description="Which language models can write replies. Pick a provider per Page in Bot Studio." />

    <div class="grid gap-5 lg:grid-cols-2">
        @forelse ($providers as $provider)
            @php($isDefault = $provider['key'] === $defaultProvider)
            <section @class(['ui-card relative overflow-hidden p-5', 'ring-2 ring-brand-500/60' => $isDefault])>
                <div class="flex items-start gap-3.5">
                    <span @class([
                        'inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-white shadow-xs',
                        'bg-gradient-to-br from-slate-700 to-slate-900' => $provider['key'] === 'openai',
                        'bg-gradient-to-br from-orange-400 to-amber-600' => $provider['key'] !== 'openai',
                    ])>
                        <x-ui.icon name="sparkles" class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-[15px] font-semibold text-ink">{{ $provider['label'] }}</h3>
                            @if ($isDefault)<x-ui.badge tone="brand" size="sm">Default</x-ui.badge>@endif
                        </div>
                        <p class="mt-0.5 text-[13px] text-ink-muted">Default model <code class="ui-code">{{ $provider['default_model'] ?: 'not set' }}</code></p>
                    </div>
                    <x-ui.badge :tone="$provider['configured'] ? 'success' : 'danger'" dot>{{ $provider['configured'] ? 'Configured' : 'No API key' }}</x-ui.badge>
                </div>

                <div class="mt-4">
                    <p class="text-2xs font-semibold tracking-wider text-slate-400 uppercase">Models offered</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($provider['models'] as $model)
                            <span @class(['rounded-md px-2 py-0.5 font-mono text-xs ring-1 ring-inset', 'bg-brand-50 text-brand-700 ring-brand-100' => $model === $provider['default_model'], 'bg-slate-50 text-slate-600 ring-line' => $model !== $provider['default_model']])>{{ $model }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-ink-muted ring-1 ring-line ring-inset">
                    .env: @foreach ($envKeys[$provider['key']] ?? [] as $key)<code class="ui-code">{{ $key }}</code>@if (! $loop->last), @endif @endforeach
                </div>
            </section>
        @empty
            <x-ui.card class="lg:col-span-2"><x-ui.empty-state compact icon="sparkles" title="No providers registered" /></x-ui.card>
        @endforelse
    </div>

    <x-ui.alert tone="neutral" title="Changing providers or keys">
        Settings here are read-only for safety. Edit <code class="ui-code">.env</code> on the server
        (<code class="ui-code">AI_PROVIDER</code>, <code class="ui-code">OPENAI_API_KEY</code>, <code class="ui-code">ANTHROPIC_API_KEY</code>, model variables),
        then run <code class="ui-code">php artisan config:cache</code> and restart the queue worker (<code class="ui-code">php artisan queue:restart</code>).
    </x-ui.alert>
</div>
