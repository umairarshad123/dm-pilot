<?php

namespace Tests\Unit\Pipeline;

use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\Bot\BotSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotSettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_defaults_apply_without_rows(): void
    {
        config(['bot.history_limit' => 12, 'openai.model' => 'model-x', 'openai.temperature' => null]);

        $settings = app(BotSettingsResolver::class)->forAccount(null);

        $this->assertTrue($settings->botEnabled);
        $this->assertSame(12, $settings->historyLimit);
        $this->assertSame('model-x', $settings->model);
        $this->assertNull($settings->temperature);
        $this->assertSame(config('bot.system_prompt'), $settings->systemPrompt);
    }

    public function test_account_row_overrides_global_which_overrides_config(): void
    {
        config(['bot.human_takeover_minutes' => 60, 'bot.reply_delay_seconds' => 0]);
        $account = MetaAccount::factory()->create();
        $other = MetaAccount::factory()->create();

        BotSetting::create([
            'meta_account_id' => null, 'business_info' => 'Global info', 'human_takeover_minutes' => 30,
            'reply_delay_seconds' => 5, 'channel_instructions' => ['facebook' => 'Global FB', 'instagram' => 'Global IG'],
            'faqs' => [['question' => 'Hours?', 'answer' => '9-5'], ['question' => '', 'answer' => 'dropped']],
        ]);
        BotSetting::create([
            'meta_account_id' => $account->id, 'business_info' => '   ', 'human_takeover_minutes' => 15,
            'model' => 'acct-model', 'channel_instructions' => ['instagram' => 'Account IG'],
        ]);

        $settings = app(BotSettingsResolver::class)->forAccount($account);

        $this->assertSame('Global info', $settings->businessInfo); // blank account value does not override
        $this->assertSame(15, $settings->humanTakeoverMinutes);
        $this->assertSame(5, $settings->replyDelaySeconds);
        $this->assertSame('acct-model', $settings->model);
        $this->assertSame('Global FB', $settings->channelInstruction('facebook'));
        $this->assertSame('Account IG', $settings->channelInstruction('instagram'));
        $this->assertSame([['question' => 'Hours?', 'answer' => '9-5']], $settings->faqs);

        $this->assertSame(30, app(BotSettingsResolver::class)->forAccount($other)->humanTakeoverMinutes);
    }

    public function test_bot_enabled_is_a_kill_switch_at_every_level(): void
    {
        $account = MetaAccount::factory()->create();
        $resolver = app(BotSettingsResolver::class);

        BotSetting::create(['meta_account_id' => $account->id, 'bot_enabled' => false]);
        $this->assertFalse($resolver->forAccount($account)->botEnabled);

        BotSetting::query()->delete();
        BotSetting::create(['meta_account_id' => null, 'bot_enabled' => false]);
        BotSetting::create(['meta_account_id' => $account->id, 'bot_enabled' => true]);
        $this->assertFalse($resolver->forAccount($account)->botEnabled);

        BotSetting::query()->delete();
        config(['bot.enabled' => 'false']);
        $this->assertFalse($resolver->forAccount($account)->botEnabled);
    }
}
