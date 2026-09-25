{{--
    Bot Studio. Scope follows the page switcher:
      "All pages"   → global defaults row (applies to every page unless overridden)
      a page        → that page's override row (blank field = inherit the global value)
    Controller: App\Http\Controllers\Admin\BotSettingController. Alpine factories: ./_scripts.blade.php.
--}}
@php
    use App\Http\Requests\Admin\BotSettingRequest;

    $isPage = $account !== null;
    $action = $isPage ? route('admin.bot-settings.account.update', $account) : route('admin.bot-settings.update');
    $pageName = $isPage ? ($account->page_name ?: 'Page #'.$account->id) : null;

    $tabs = [
        'persona' => ['label' => 'Persona & behavior', 'icon' => 'sparkles'],
        'knowledge' => ['label' => 'Knowledge', 'icon' => 'book'],
        'model' => ['label' => 'AI model', 'icon' => 'bot'],
        'timing' => ['label' => 'Timing & handoff', 'icon' => 'clock'],
    ];
    $activeTab = $errorTabs[0] ?? 'persona';

    // FAQs: old input after a failed save, else the stored list.
    $faqValues = old('faqs') !== null
        ? array_values(array_map(fn ($f) => ['question' => (string) ($f['question'] ?? ''), 'answer' => (string) ($f['answer'] ?? '')], array_filter((array) old('faqs'), 'is_array')))
        : $faqs;
    $faqErrors = [];
    foreach ($errors->getMessages() as $key => $messages) {
        if (preg_match('/^faqs\.(\d+)\.(question|answer)$/', $key, $m)) {
            $faqErrors[(int) $m[1]][$m[2]] = $messages[0];
        }
    }

    $storedProvider = old('ai_provider', $setting->ai_provider) ?? '';
    $storedModel = old('model', $setting->model) ?? '';
    $storedFallback = old('fallback_message', $setting->fallback_message);
    $enabled = (bool) filter_var(old('bot_enabled', $setting->bot_enabled ?? true), FILTER_VALIDATE_BOOL);

    $ph = fn ($value, int $limit = 180) => \Illuminate\Support\Str::limit(trim((string) $value), $limit, '…');

    $templates = [
        'sales' => [
            'label' => 'Friendly sales assistant', 'icon' => 'sparkles',
            'description' => 'Warm, helpful, nudges toward a purchase.',
            'prompt' => "You are a friendly sales assistant replying to customer direct messages on behalf of the business.\nKeep replies short and conversational (1-3 sentences), like a real person texting.\nHelp customers find the right product or service, answer questions using only the business information provided, and gently guide them toward ordering or booking.\nNever invent prices, stock, policies or availability. If you are unsure, say a team member will follow up.\nDo not use markdown formatting.",
        ],
        'support' => [
            'label' => 'Support agent', 'icon' => 'shield',
            'description' => 'Calm, precise, solves problems.',
            'prompt' => "You are a patient customer support agent replying to direct messages on behalf of the business.\nAcknowledge the customer's issue, then give clear, step-by-step help in plain language (1-4 short sentences).\nOnly use the business information and FAQs provided. If the issue needs a person (refunds, account changes, complaints), say a team member will take over shortly.\nNever blame the customer, never guess. Do not use markdown formatting.",
        ],
        'appointments' => [
            'label' => 'Appointment setter', 'icon' => 'calendar',
            'description' => 'Books calls and visits.',
            'prompt' => "You are an appointment setter replying to direct messages on behalf of the business.\nYour goal is to book a visit or call. Ask one short question at a time: what they need, their preferred day and time, and their name and phone number.\nConfirm the details back in one sentence. Only offer services and opening hours from the business information provided; never promise a specific slot, say the team will confirm it.\nKeep it friendly and brief. Do not use markdown formatting.",
        ],
        'leads' => [
            'label' => 'Lead qualifier', 'icon' => 'filter',
            'description' => 'Asks smart questions, captures details.',
            'prompt' => "You are a lead qualification assistant replying to direct messages on behalf of the business.\nBe friendly and concise. Learn, one question at a time: what the customer is looking for, their budget, their timeline, and the best way to contact them.\nAnswer their questions using only the business information provided, then continue qualifying.\nWhen you have their needs and contact details, thank them and say a specialist will reach out soon. Do not use markdown formatting.",
        ],
    ];
    $tones = [
        'Friendly' => 'Tone: warm and friendly, like a helpful shop assistant. Emojis are fine but sparing.',
        'Professional' => 'Tone: polite, professional and precise. No emojis or slang.',
        'Playful' => 'Tone: upbeat and playful, casual language and the occasional emoji.',
        'Concise' => 'Tone: extremely concise. One short sentence whenever possible.',
    ];

    $overrideFields = ['system_prompt', 'business_info', 'faqs', 'offers', 'channel_instructions', 'ai_provider', 'model', 'temperature', 'max_output_tokens', 'history_limit', 'reply_delay_seconds', 'human_takeover_minutes', 'fallback_message'];
    $overrideCount = fn ($row) => $row ? collect($overrideFields)->filter(fn ($f) => filled($row->{$f}))->count() + ($row->bot_enabled ? 0 : 1) : 0;
    $currentOverrides = $isPage ? $overrideCount($account->botSetting) : 0;

    $studioConfig = [
        'tab' => $activeTab,
        'tabKeys' => array_keys($tabs),
        'errorTabs' => $errorTabs,
        'isPage' => $isPage,
        'enabled' => $enabled,
        'faqs' => $faqValues,
        'faqErrors' => (object) $faqErrors,
        'inheritedFaqs' => $inheritedFaqs,
        'provider' => $storedProvider,
        'inheritedProvider' => $inheritedProvider,
        'providers' => $providers,
        'model' => $storedModel,
        'fallbackOn' => filled($storedFallback),
        'templates' => $templates,
    ];
@endphp

<x-layouts.app title="Bot Studio" width="wide">
    <x-slot:actions>
        <x-ui.button size="sm" icon="zap" :href="route('admin.automations.index')">Automations</x-ui.button>
        <x-ui.button size="sm" variant="soft" icon="message" class="xl:hidden"
            onclick="document.getElementById('playground').scrollIntoView({ behavior: 'smooth' })">Test your bot</x-ui.button>
    </x-slot:actions>

    <div class="space-y-6">
        @include('admin.bot-studio._scope')

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px] 2xl:grid-cols-[minmax(0,1fr)_420px]">
            {{-- Settings --}}
            <form id="bot-studio-form" method="POST" action="{{ $action }}" data-warn-unsaved novalidate
                x-data="botStudio(@js($studioConfig))" class="min-w-0">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-1 overflow-x-auto border-b border-line ui-scroll" role="tablist" aria-label="Bot settings sections">
                    @foreach ($tabs as $key => $t)
                        <button type="button" role="tab" id="tab-btn-{{ $key }}" data-tab="{{ $key }}"
                            x-on:click="tab = @js($key)"
                            x-bind:aria-selected="tab === @js($key)"
                            x-bind:tabindex="tab === @js($key) ? 0 : -1"
                            x-bind:class="{ 'border-brand-600 text-brand-700': tab === @js($key), 'border-transparent text-slate-500 hover:border-slate-300 hover:text-ink': tab !== @js($key) }"
                            @class([
                                'relative -mb-px inline-flex items-center gap-2 border-b-2 px-3 pt-2 pb-2.5 text-sm font-medium whitespace-nowrap transition',
                                'border-brand-600 text-brand-700' => $key === $activeTab,
                                'border-transparent text-slate-500' => $key !== $activeTab,
                            ])>
                            <x-ui.icon :name="$t['icon']" class="size-4" />
                            {{ $t['label'] }}
                            @if (in_array($key, $errorTabs, true))
                                <span class="size-2 rounded-full bg-danger-500 ring-2 ring-white" title="Has errors"></span>
                                <span class="sr-only">(has errors)</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                <div class="pt-6">
                    <div role="tabpanel" aria-labelledby="tab-btn-persona" x-show="tab === 'persona'" @if ($activeTab !== 'persona') x-cloak @endif>
                        @include('admin.bot-studio._tab-persona')
                    </div>
                    <div role="tabpanel" aria-labelledby="tab-btn-knowledge" x-show="tab === 'knowledge'" @if ($activeTab !== 'knowledge') x-cloak @endif>
                        @include('admin.bot-studio._tab-knowledge')
                    </div>
                    <div role="tabpanel" aria-labelledby="tab-btn-model" x-show="tab === 'model'" @if ($activeTab !== 'model') x-cloak @endif>
                        @include('admin.bot-studio._tab-model')
                    </div>
                    <div role="tabpanel" aria-labelledby="tab-btn-timing" x-show="tab === 'timing'" @if ($activeTab !== 'timing') x-cloak @endif>
                        @include('admin.bot-studio._tab-timing')
                    </div>
                </div>

                <x-ui.save-bar :label="$isPage ? 'Save page settings' : 'Save global defaults'" />
            </form>

            {{-- Playground --}}
            <aside id="playground" class="min-w-0 scroll-mt-24 xl:sticky xl:top-24">
                @include('admin.bot-studio._playground')
            </aside>
        </div>
    </div>

    @if ($isPage && $account->botSetting)
        <x-ui.modal name="reset-overrides" title="Remove all overrides for {{ $pageName }}?" icon="refresh" tone="warning" size="sm"
            description="Every setting on this page goes back to the global defaults. Automations and conversations are not affected.">
            <x-slot:footer>
                <x-ui.button x-on:click="close()">Cancel</x-ui.button>
                <form method="POST" action="{{ route('admin.bot-settings.account.destroy', $account) }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="danger">Remove overrides</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.modal>
    @endif

    @push('scripts')
        @include('admin.bot-studio._scripts')
    @endpush
</x-layouts.app>
