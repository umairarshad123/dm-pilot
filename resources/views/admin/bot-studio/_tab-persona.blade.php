{{-- Persona & behavior: master switch, system prompt (+ templates / tone), channel instructions. --}}
<div class="space-y-6">
    {{-- Master switch --}}
    <div x-bind:class="{ 'border-success-100 bg-success-50/40': enabled, 'border-danger-100 bg-danger-50/50': !enabled }"
        @class(['rounded-card border p-5 shadow-card transition-colors', 'border-success-100 bg-success-50/40' => $enabled, 'border-danger-100 bg-danger-50/50' => ! $enabled])>
        <div class="flex items-start gap-4">
            <span x-bind:class="{ 'bg-success-600': enabled, 'bg-danger-600': !enabled }"
                @class(['inline-flex size-11 shrink-0 items-center justify-center rounded-xl text-white shadow-xs transition-colors', 'bg-success-600' => $enabled, 'bg-danger-600' => ! $enabled])>
                <x-ui.icon name="bot" class="size-6" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-[15px] font-semibold text-ink">
                            {{ $isPage ? 'Bot on this page' : 'Bot on all pages' }}
                            <span class="ml-1 text-sm font-medium" x-bind:class="{ 'text-success-700': enabled, 'text-danger-700': !enabled }" x-text="enabled ? 'On' : 'Off'">{{ $enabled ? 'On' : 'Off' }}</span>
                        </h3>
                        <p class="mt-0.5 text-[13px] text-ink-muted">{{ $isPage ? 'Master switch for '.$pageName.'.' : 'Global kill switch.' }}</p>
                    </div>
                    <x-ui.toggle name="bot_enabled" :checked="$enabled" x-model="enabled" aria-label="Bot enabled" />
                </div>
                <div class="mt-3 text-[13px] leading-relaxed">
                    <p x-show="enabled" @unless ($enabled) x-cloak @endunless class="text-slate-700">
                        @if ($isPage)
                            The AI and your automations reply to new messages on this page{{ $global->bot_enabled ? '' : ', but the global kill switch is OFF, so nothing is sent until you turn it back on under Global defaults' }}.
                        @else
                            The bot replies on every page that has not been switched off individually.
                        @endif
                    </p>
                    <div x-show="!enabled" @if ($enabled) x-cloak @endif class="space-y-1 text-danger-700">
                        <p class="font-medium">{{ $isPage ? 'Nothing is sent automatically on this page.' : 'Nothing is sent automatically on ANY page.' }}</p>
                        <p class="text-danger-700/90">No AI replies, no welcome or keyword automations. Messages still arrive in Live Chat so your team can answer by hand. The playground keeps working for testing.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- System prompt --}}
    <x-ui.card title="Persona" description="The instructions the AI follows in every conversation: who it is, how it talks, what it must never do." icon="sparkles">
        <div class="space-y-5">
            <div>
                <p class="mb-2 text-2xs font-semibold tracking-wider text-slate-400 uppercase">Start from a template</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($templates as $key => $t)
                        <button type="button" x-on:click="applyTemplate(@js($key))"
                            class="group flex items-start gap-3 rounded-xl border border-line bg-white p-3 text-left shadow-xs transition hover:border-brand-200 hover:bg-brand-50/40 hover:shadow-card-hover">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                                <x-ui.icon :name="$t['icon']" class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-[13px] font-semibold text-ink">{{ $t['label'] }}</span>
                                <span class="block text-xs text-ink-muted">{{ $t['description'] }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div x-data="bsInherit(@js($inherited['system_prompt']))" class="space-y-2">
                @include('admin.bot-studio._inherit-head', ['label' => 'System prompt', 'for' => 'system_prompt'])
                <x-ui.textarea name="system_prompt" rows="9" autosize mono :counter="20000" maxlength="20000"
                    :value="$setting->system_prompt" data-inherit-input :data-inherited="$inherited['system_prompt']"
                    :placeholder="$ph($inherited['system_prompt'], 400)" />
                @include('admin.bot-studio._inherit-foot', ['name' => 'system_prompt'])
                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="mr-1 text-xs text-ink-muted">Tone:</span>
                    @foreach ($tones as $tone => $line)
                        <button type="button" x-on:click="appendTone(@js($line))"
                            class="rounded-full border border-line bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-xs transition hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">{{ $tone }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Channel instructions --}}
    <x-ui.card title="Channel-specific instructions" description="Extra lines added to the prompt only on that channel." icon="messages">
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach (['facebook' => ['Messenger', 'messenger'], 'instagram' => ['Instagram', 'instagram']] as $channel => [$channelLabel, $channelIcon])
                @php($key = 'channel_instructions.'.$channel)
                <div x-data="bsInherit(@js($inherited[$key]))" class="space-y-2">
                    @include('admin.bot-studio._inherit-head', ['label' => $channelLabel, 'for' => 'channel_instructions_'.$channel])
                    <x-ui.textarea name="channel_instructions[{{ $channel }}]" id="channel_instructions_{{ $channel }}" rows="3" autosize :counter="5000" maxlength="5000"
                        :value="$setting->channel_instructions[$channel] ?? null" data-inherit-input :placeholder="$ph($inherited[$key])" />
                    @include('admin.bot-studio._inherit-foot', ['name' => $key])
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
