<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BotSettingRequest;
use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;
use App\Support\CurrentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

/**
 * Bot Studio. Follows the page switcher: "All pages" edits the global defaults row, a selected page edits
 * that page's override row (blank = inherit from global). The playground lives in BotStudioPlaygroundController.
 */
class BotSettingController extends Controller
{
    /** Static, approximate guidance shown next to suggested models. */
    private const MODEL_HINTS = [
        'gpt-6-luna' => ['tier' => 'Lowest cost', 'note' => 'Fast and cheapest. Great default for short DM replies.', 'cost' => 1],
        'gpt-6-sol' => ['tier' => 'Balanced', 'note' => 'Smarter answers for trickier questions at a higher price.', 'cost' => 2],
        'claude-haiku-4-5' => ['tier' => 'Low cost', 'note' => 'Fast, inexpensive Claude model.', 'cost' => 1],
        'claude-sonnet-5' => ['tier' => 'Balanced', 'note' => 'Strong quality at a moderate price.', 'cost' => 2],
        'claude-opus-5' => ['tier' => 'Highest quality', 'note' => 'Best reasoning and tone. Highest cost per reply.', 'cost' => 3],
    ];

    public function edit(CurrentPage $currentPage, AiProviderFactory $providers): View
    {
        return $this->studio($currentPage->account(), $currentPage, $providers);
    }

    public function update(BotSettingRequest $request): RedirectResponse
    {
        BotSetting::global()->fill($request->settingAttributes())->save();

        return redirect()->route('admin.bot-settings.edit')->with('success', 'Global bot settings saved. They apply to every page without an override.');
    }

    /** Deep link to a page's overrides: selects that page in the switcher and opens the studio. */
    public function editAccount(MetaAccount $metaAccount, CurrentPage $currentPage): RedirectResponse
    {
        $currentPage->set($metaAccount->id);

        return redirect()->route('admin.bot-settings.edit');
    }

    public function updateAccount(BotSettingRequest $request, MetaAccount $metaAccount): RedirectResponse
    {
        BotSetting::updateOrCreate(['meta_account_id' => $metaAccount->id], $request->settingAttributes());

        return redirect()->route('admin.bot-settings.account.edit', $metaAccount)
            ->with('success', 'Settings for '.($metaAccount->page_name ?: 'this page').' saved.');
    }

    public function destroyAccount(MetaAccount $metaAccount): RedirectResponse
    {
        BotSetting::where('meta_account_id', $metaAccount->id)->delete();

        return redirect()->route('admin.bot-settings.account.edit', $metaAccount)
            ->with('success', 'Overrides removed. '.($metaAccount->page_name ?: 'This page').' now uses the global defaults.');
    }

    private function studio(?MetaAccount $account, CurrentPage $currentPage, AiProviderFactory $factory): View
    {
        $global = BotSetting::global();
        $setting = $account
            ? ($account->botSetting ?? new BotSetting(['meta_account_id' => $account->id, 'bot_enabled' => true]))
            : $global;

        $inheritFrom = $account ? $global : null; // page → global row → config; global → config
        $defaultProvider = AiProviderFactory::defaultProvider();
        $inheritedProvider = AiProviderFactory::normalize($inheritFrom?->ai_provider) ?? $defaultProvider;

        $providers = array_map(function (array $p) use ($inheritFrom) {
            $key = $p['key'];
            $config = AiProviderFactory::configKey($key);
            $parentModel = $inheritFrom?->model;
            $modelFits = filled($parentModel) && in_array(AiProviderFactory::providerForModel($parentModel), [null, $key], true);

            return $p + [
                'supports_temperature' => $key === AiProviderFactory::OPENAI,
                'inherited_model' => $modelFits ? $parentModel : $p['default_model'],
                'inherited_max_output_tokens' => $inheritFrom?->max_output_tokens ?? config($config.'.max_output_tokens'),
                'inherited_temperature' => $key === AiProviderFactory::OPENAI ? ($inheritFrom?->temperature ?? config('openai.temperature')) : null,
                'hints' => Arr::only(self::MODEL_HINTS, $p['models']),
            ];
        }, $factory->available());

        $configChannels = (array) config('bot.channel_instructions', []);
        $inherited = [
            'system_prompt' => $inheritFrom?->system_prompt ?: config('bot.system_prompt'),
            'business_info' => $inheritFrom?->business_info,
            'offers' => $inheritFrom?->offers,
            'channel_instructions.facebook' => $inheritFrom?->channel_instructions['facebook'] ?? $configChannels['facebook'] ?? null,
            'channel_instructions.instagram' => $inheritFrom?->channel_instructions['instagram'] ?? $configChannels['instagram'] ?? null,
            'history_limit' => $inheritFrom?->history_limit ?? config('bot.history_limit'),
            'reply_delay_seconds' => $inheritFrom?->reply_delay_seconds ?? config('bot.reply_delay_seconds'),
            'human_takeover_minutes' => $inheritFrom?->human_takeover_minutes ?? config('bot.human_takeover_minutes'),
            'fallback_message' => $inheritFrom?->fallback_message ?: config('bot.fallback_message'),
        ];

        $errors = session('errors')?->getBag('default');

        return view('admin.bot-studio.edit', [
            'setting' => $setting,
            'account' => $account,
            'global' => $global,
            'inherited' => $inherited,
            'inheritedFaqs' => BotSettingRequest::normalizeFaqList((array) ($inheritFrom?->faqs ?? [])),
            'inheritedProvider' => $inheritedProvider,
            'providers' => $providers,
            'pages' => $currentPage->connectedPages()->load('botSetting'),
            'errorTabs' => $errors ? BotSettingRequest::tabsWithErrors($errors->keys()) : [],
            'configEnabled' => filter_var(config('bot.enabled', true), FILTER_VALIDATE_BOOL),
            'faqs' => BotSettingRequest::normalizeFaqList((array) ($setting->faqs ?? [])),
        ]);
    }
}
