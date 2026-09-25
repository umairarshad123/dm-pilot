<?php

namespace Tests\Feature\Admin;

use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;
use App\Services\Bot\BotSettingsResolver;

class BotSettingsAdminTest extends AdminTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'bot_enabled' => '1',
            'system_prompt' => '',
            'business_info' => '',
            'faqs_text' => '',
            'offers' => '',
            'channel_instructions' => ['facebook' => '', 'instagram' => ''],
            'model' => '',
            'temperature' => '',
            'max_output_tokens' => '',
            'history_limit' => '',
            'reply_delay_seconds' => '',
            'human_takeover_minutes' => '',
            'fallback_message' => '',
        ], $overrides);
    }

    public function test_global_settings_save(): void
    {
        $this->put(route('admin.bot-settings.update'), $this->payload([
            'bot_enabled' => '0',
            'system_prompt' => 'Be nice.',
            'business_info' => 'Open 9-5.',
            'faqs_text' => "Q: Do you deliver?\nA: Yes.\n\nQ: Where are you?\nA: Main street\n42.",
            'channel_instructions' => ['facebook' => '', 'instagram' => 'Keep it short'],
            'model' => 'gpt-4.1-mini',
            'temperature' => '0.3',
            'history_limit' => '12',
            'human_takeover_minutes' => '90',
        ]))->assertRedirect(route('admin.bot-settings.edit'))->assertSessionHas('success');

        $global = BotSetting::whereNull('meta_account_id')->sole();
        $this->assertFalse($global->bot_enabled);
        $this->assertSame('Be nice.', $global->system_prompt);
        $this->assertSame([
            ['question' => 'Do you deliver?', 'answer' => 'Yes.'],
            ['question' => 'Where are you?', 'answer' => "Main street\n42."],
        ], $global->faqs);
        $this->assertSame(['instagram' => 'Keep it short'], $global->channel_instructions);
        $this->assertSame(0.3, $global->temperature);
        $this->assertSame(12, $global->history_limit);
        $this->assertSame(90, $global->human_takeover_minutes);
        $this->assertNull($global->max_output_tokens);
        $this->assertNull($global->offers);

        $this->get(route('admin.bot-settings.edit'))->assertSee('Where are you?')->assertSee('Keep it short');
    }

    public function test_blank_fields_are_null_to_inherit_defaults(): void
    {
        BotSetting::global()->update(['system_prompt' => 'Old', 'model' => 'x']);

        $this->put(route('admin.bot-settings.update'), $this->payload())->assertSessionHasNoErrors();

        $global = BotSetting::global()->fresh();
        $this->assertNull($global->system_prompt);
        $this->assertNull($global->model);
        $this->assertNull($global->faqs);
        $this->assertNull($global->channel_instructions);
    }

    public function test_validation_errors(): void
    {
        $this->put(route('admin.bot-settings.update'), $this->payload(['temperature' => '5', 'history_limit' => '-1', 'model' => 'bad model!']))
            ->assertSessionHasErrors(['temperature', 'history_limit', 'model']);
    }

    public function test_per_account_override_save_inherit_and_remove(): void
    {
        BotSetting::global()->update(['system_prompt' => 'Global prompt here']);
        $account = MetaAccount::factory()->create();

        // Global values are shown as placeholders on the per-account form.
        $this->followingRedirects()->get(route('admin.bot-settings.account.edit', $account))->assertOk()->assertSee('Global prompt here');

        $this->put(route('admin.bot-settings.account.update', $account), $this->payload([
            'business_info' => 'Account-specific info',
            'reply_delay_seconds' => '5',
        ]))->assertRedirect(route('admin.bot-settings.account.edit', $account));

        $row = BotSetting::where('meta_account_id', $account->id)->sole();
        $this->assertSame('Account-specific info', $row->business_info);
        $this->assertSame(5, $row->reply_delay_seconds);
        $this->assertNull($row->system_prompt); // inherits global
        $this->assertTrue($row->bot_enabled);

        // Saving again updates the same row.
        $this->put(route('admin.bot-settings.account.update', $account), $this->payload(['bot_enabled' => '0']));
        $this->assertSame(1, BotSetting::where('meta_account_id', $account->id)->count());
        $this->assertFalse($row->fresh()->bot_enabled);
        $this->assertNull($row->fresh()->business_info);

        $this->delete(route('admin.bot-settings.account.destroy', $account))->assertRedirect(route('admin.bot-settings.account.edit', $account));
        $this->assertSame(0, BotSetting::where('meta_account_id', $account->id)->count());
        $this->assertSame(1, BotSetting::whereNull('meta_account_id')->count());
    }

    public function test_faq_list_and_provider_are_saved(): void
    {
        $this->put(route('admin.bot-settings.update'), $this->payload([
            'faqs' => [
                ['question' => ' Do you deliver? ', 'answer' => 'Yes, free in the city.'],
                ['question' => '', 'answer' => ''], // empty row dropped
                ['question' => 'Open Sunday?', 'answer' => 'No.
Mon-Sat only.'],
            ],
            'faqs_text' => 'Q: ignored
A: because the list wins',
            'ai_provider' => 'claude',
            'model' => 'claude-sonnet-5',
            'max_output_tokens' => '1500',
        ]))->assertSessionHasNoErrors();

        $global = BotSetting::global()->fresh();
        $this->assertSame([
            ['question' => 'Do you deliver?', 'answer' => 'Yes, free in the city.'],
            ['question' => 'Open Sunday?', 'answer' => 'No.
Mon-Sat only.'],
        ], $global->faqs);
        $this->assertSame('claude', $global->ai_provider);
        $this->assertSame('claude-sonnet-5', $global->model);
        $this->assertSame(1500, $global->max_output_tokens);

        // Blank provider = inherit the server default again.
        $this->put(route('admin.bot-settings.update'), $this->payload(['ai_provider' => '']))->assertSessionHasNoErrors();
        $this->assertNull(BotSetting::global()->fresh()->ai_provider);
        $this->assertNull(BotSetting::global()->fresh()->faqs);
    }

    public function test_provider_and_faq_validation(): void
    {
        $this->put(route('admin.bot-settings.update'), $this->payload([
            'ai_provider' => 'gemini',
            'faqs' => [['question' => 'Question without answer', 'answer' => ''], ['question' => '', 'answer' => 'Answer without question']],
        ]))->assertSessionHasErrors(['ai_provider', 'faqs.0.answer', 'faqs.1.question']);

        $this->assertNull(BotSetting::global()->fresh()->ai_provider);
    }

    public function test_page_override_of_provider_resolves_for_that_page_only(): void
    {
        $account = MetaAccount::factory()->create();

        $this->put(route('admin.bot-settings.account.update', $account), $this->payload(['ai_provider' => 'claude']))
            ->assertRedirect(route('admin.bot-settings.account.edit', $account));

        $resolver = app(BotSettingsResolver::class);
        $this->assertSame('claude', $resolver->forAccount($account)->aiProvider);
        $this->assertSame(config('anthropic.model'), $resolver->forAccount($account)->model);
        $this->assertSame(AiProviderFactory::defaultProvider(), $resolver->forAccount(null)->aiProvider);
    }
}
