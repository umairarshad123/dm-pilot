{{-- AI model: provider cards, model picker (suggested + custom), temperature (OpenAI only), max output tokens. --}}
@php($inheritedProviderLabel = collect($providers)->firstWhere('key', $inheritedProvider)['label'] ?? ucfirst($inheritedProvider))
<div class="space-y-6">
    {{-- Live summary --}}
    <div class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-slate-900 px-5 py-4 text-white shadow-card">
        <span class="inline-flex size-9 items-center justify-center rounded-lg bg-white/10"><x-ui.icon name="bot" class="size-5" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-2xs font-semibold tracking-wider text-white/50 uppercase">{{ $isPage ? 'Replies on this page use' : 'Replies use (unless a page overrides)' }}</p>
            <p class="truncate text-[15px] font-semibold">
                <span x-text="providerLabel(effectiveProvider)">{{ $inheritedProviderLabel }}</span>
                <span class="text-white/40">·</span>
                <span class="font-mono text-[13px] font-medium text-white/90" x-text="activeModel"></span>
            </p>
        </div>
        <span x-show="!providerInfo.configured" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-danger-500/20 px-2.5 py-1 text-xs font-medium text-danger-100 ring-1 ring-danger-400/40 ring-inset">
            <x-ui.icon name="alert-triangle" class="size-3.5" /> API key missing: AI replies will fail
        </span>
    </div>

    <x-ui.card title="Provider" description="Which AI company writes the replies. Keys are set in the server .env and never shown here." icon="layers">
        <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="AI provider">
            <label x-bind:class="{ 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-100': provider === '', 'border-line bg-white hover:border-line-strong': provider !== '' }"
                class="relative flex cursor-pointer flex-col gap-1 rounded-xl border p-4 shadow-xs transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-500">
                <input type="radio" name="ai_provider" value="" x-model="provider" class="sr-only" @checked($storedProvider === '')>
                <span class="flex items-center gap-2 text-[13.5px] font-semibold text-ink"><x-ui.icon name="link" class="size-4 text-slate-400" />{{ $isPage ? 'Inherit' : 'Server default' }}</span>
                <span class="text-xs text-ink-muted">{{ $isPage ? 'Use the global choice' : 'From AI_PROVIDER' }}: <span class="font-medium text-slate-700">{{ $inheritedProviderLabel }}</span></span>
                <x-ui.icon name="check-circle" class="absolute top-3 right-3 size-4 text-brand-600" x-show="provider === ''" x-cloak />
            </label>
            @foreach ($providers as $p)
                <label x-bind:class="{ 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-100': provider === @js($p['key']), 'border-line bg-white hover:border-line-strong': provider !== @js($p['key']), 'cursor-not-allowed opacity-60': @js(! $p['configured']) && provider !== @js($p['key']) }"
                    class="relative flex cursor-pointer flex-col gap-1 rounded-xl border p-4 shadow-xs transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brand-500">
                    <input type="radio" name="ai_provider" value="{{ $p['key'] }}" x-model="provider" class="sr-only" @checked($storedProvider === $p['key'])
                        x-bind:disabled="@js(! $p['configured']) && provider !== @js($p['key'])">
                    <span class="flex items-center gap-2 text-[13.5px] font-semibold text-ink">
                        <x-ui.icon :name="$p['key'] === 'claude' ? 'sparkles' : 'bot'" class="size-4 text-slate-500" />{{ $p['label'] }}
                    </span>
                    @if ($p['configured'])
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-success-700"><span class="size-1.5 rounded-full bg-success-500"></span>Configured</span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-danger-600"><span class="size-1.5 rounded-full bg-danger-500"></span>API key missing</span>
                    @endif
                    <span class="font-mono text-2xs text-ink-muted">default {{ $p['default_model'] }}</span>
                    <x-ui.icon name="check-circle" class="absolute top-3 right-3 size-4 text-brand-600" x-show="provider === '{{ $p['key'] }}'" x-cloak />
                </label>
            @endforeach
        </div>
        @error('ai_provider')
            <p class="ui-error mt-2 flex items-center gap-1"><x-ui.icon name="alert-circle" class="size-3.5" />{{ $message }}</p>
        @enderror
        @if (collect($providers)->contains('configured', false))
            <p class="ui-hint mt-3">A provider without an API key cannot be selected. Add <span class="ui-code">OPENAI_API_KEY</span> or <span class="ui-code">ANTHROPIC_API_KEY</span> to the server .env to enable it.</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Model" description="Bigger models write better answers but cost more and reply slower. Cost notes are approximate." icon="sliders">
        <input type="hidden" name="model" value="{{ $storedModel }}" x-bind:value="modelValue">
        <div class="grid gap-2" role="radiogroup" aria-label="Model">
            <label x-bind:class="{ 'border-brand-500 bg-brand-50/50 ring-1 ring-brand-200': modelChoice === '', 'border-line hover:border-line-strong': modelChoice !== '' }"
                class="flex cursor-pointer items-center gap-3 rounded-xl border bg-white px-4 py-3 transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-500">
                <input type="radio" name="_model_choice" value="" x-model="modelChoice" class="size-4 accent-brand-600">
                <span class="min-w-0 flex-1">
                    <span class="block text-[13.5px] font-medium text-ink">{{ $isPage ? 'Inherit' : 'Provider default' }}
                        <span class="ml-1 font-mono text-xs text-ink-muted" x-text="providerInfo.inherited_model"></span></span>
                    <span class="block text-xs text-ink-muted">{{ $isPage ? 'Whatever the global defaults use for this provider.' : 'The model set in the server config for this provider.' }}</span>
                </span>
            </label>
            <template x-for="m in providerInfo.models" :key="effectiveProvider + m">
                <label x-bind:class="{ 'border-brand-500 bg-brand-50/50 ring-1 ring-brand-200': modelChoice === m, 'border-line hover:border-line-strong': modelChoice !== m }"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border bg-white px-4 py-3 transition has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-brand-500">
                    <input type="radio" name="_model_choice" x-bind:value="m" x-model="modelChoice" class="size-4 accent-brand-600">
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[13px] font-medium text-ink" x-text="m"></span>
                            <template x-if="hint(m)">
                                <span class="rounded-full px-2 py-0.5 text-2xs font-semibold"
                                    x-bind:class="{ 'bg-success-50 text-success-700': hint(m).cost === 1, 'bg-sky-50 text-sky-700': hint(m).cost === 2, 'bg-violet-50 text-violet-700': hint(m).cost === 3 }"
                                    x-text="hint(m).tier"></span>
                            </template>
                        </span>
                        <span class="block text-xs text-ink-muted" x-text="hint(m) ? hint(m).note : 'Suggested model'"></span>
                    </span>
                    <span class="hidden shrink-0 font-mono text-xs tracking-widest sm:block" x-show="hint(m)" aria-hidden="true">
                        <span class="text-ink" x-text="'$'.repeat(hint(m)?.cost || 0)"></span><span class="text-slate-300" x-text="'$'.repeat(3 - (hint(m)?.cost || 0))"></span>
                    </span>
                </label>
            </template>
            <div x-bind:class="{ 'border-brand-500 bg-brand-50/50 ring-1 ring-brand-200': modelChoice === '__custom', 'border-line': modelChoice !== '__custom' }"
                class="rounded-xl border bg-white px-4 py-3 transition">
                <label class="flex cursor-pointer items-center gap-3">
                    <input type="radio" name="_model_choice" value="__custom" x-model="modelChoice" class="size-4 accent-brand-600"
                        x-on:change="$nextTick(() => $refs.customModel.focus())">
                    <span class="text-[13.5px] font-medium text-ink">Custom model id</span>
                </label>
                <div x-show="modelChoice === '__custom'" x-collapse x-cloak>
                    <div class="pt-3 pl-7">
                        <input type="text" x-ref="customModel" x-model="customModel" maxlength="100" class="ui-input h-9 font-mono text-[13px]"
                            x-bind:placeholder="effectiveProvider === 'claude' ? 'claude-…' : 'gpt-…'" aria-label="Custom model id" @error('model') aria-invalid="true" @enderror>
                        <p class="ui-hint mt-1.5">Must be a model the selected provider offers. A model from the other provider is replaced by the provider default.</p>
                    </div>
                </div>
            </div>
        </div>
        @error('model')
            <p class="ui-error mt-2 flex items-center gap-1"><x-ui.icon name="alert-circle" class="size-3.5" />{{ $message }}</p>
        @enderror
    </x-ui.card>

    <x-ui.card title="Generation" icon="settings">
        <div class="grid gap-6 sm:grid-cols-2">
            <div x-data="bsInherit(null)" x-effect="inherited = providerInfo.inherited_max_output_tokens" class="space-y-2">
                @include('admin.bot-studio._inherit-head', ['label' => 'Max output tokens', 'for' => 'max_output_tokens'])
                <x-ui.input type="number" name="max_output_tokens" min="1" max="32000" step="1" :value="$setting->max_output_tokens" data-inherit-input
                    x-bind:placeholder="inherited ?? ''" />
                <p class="ui-hint" x-show="effectiveProvider === 'claude'">Includes Claude's thinking tokens: keep headroom (1,500+).</p>
                <p class="ui-hint" x-show="effectiveProvider !== 'claude'">Caps reply length and cost. 300–800 suits DMs.</p>
                @include('admin.bot-studio._inherit-foot', ['name' => 'max_output_tokens', 'unit' => 'tokens'])
            </div>

            <div x-show="providerInfo.supports_temperature">
                <div x-data="bsInherit(null)" x-effect="inherited = providerInfo.inherited_temperature" class="space-y-2">
                    @include('admin.bot-studio._inherit-head', ['label' => 'Temperature', 'for' => 'temperature'])
                    <x-ui.input type="number" name="temperature" min="0" max="2" step="0.1" :value="$setting->temperature" data-inherit-input
                        x-bind:placeholder="inherited ?? 'Not sent'" />
                    <p class="ui-hint">0 = focused, 1+ = creative. Leave blank for reasoning models (gpt-6, o-series): they reject temperature and replies would fail.</p>
                    @include('admin.bot-studio._inherit-foot', ['name' => 'temperature'])
                </div>
            </div>
            <div x-show="!providerInfo.supports_temperature" x-cloak class="flex items-start gap-2.5 rounded-xl bg-slate-50 p-4 text-[13px] text-ink-muted">
                <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-slate-400" />
                <span>Claude manages creativity itself, so temperature is not used. Any saved value is kept for when you switch back to OpenAI.</span>
            </div>
        </div>
    </x-ui.card>
</div>
