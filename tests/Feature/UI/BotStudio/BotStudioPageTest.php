<?php

namespace Tests\Feature\UI\BotStudio;

use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Models\User;
use App\Support\CurrentPage;
use Tests\Feature\Admin\AdminTestCase;

class BotStudioPageTest extends AdminTestCase
{
    private function dump(string $name, string $html): void
    {
        if ($dir = env('UI_DUMP_DIR')) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name.'.html', $html);
        }
    }

    public function test_all_pages_edits_global_defaults(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme Bakery']);
        BotSetting::global()->update(['faqs' => [['question' => 'Do you deliver?', 'answer' => 'Yes.']]]);

        $response = $this->get(route('admin.bot-settings.edit'))->assertOk();
        $this->dump('bot-studio-global', $response->getContent());

        $response->assertSee('Global defaults')
            ->assertSee('Applies to every page unless overridden')
            ->assertSee('Acme Bakery') // scope switcher
            ->assertSee(route('admin.bot-settings.update'), false)
            ->assertSee('Save global defaults')
            ->assertSee('Friendly sales assistant')
            ->assertSee('Lead qualifier')
            ->assertSee('Do you deliver?')
            ->assertSee(config('openai.model'))
            ->assertSee('Test your bot')
            ->assertSee('bsPlayground(', false);
    }

    public function test_selected_page_edits_its_overrides_with_inherited_values(): void
    {
        BotSetting::global()->update(['system_prompt' => 'Global prompt here', 'business_info' => 'Global business info']);
        $page = MetaAccount::factory()->instagram()->create(['page_name' => 'Acme IG']);
        BotSetting::create(['meta_account_id' => $page->id, 'offers' => 'Page offer 10% off', 'bot_enabled' => false]);
        app(CurrentPage::class)->set($page->id);
        session([CurrentPage::SESSION_KEY => $page->id]);

        $response = $this->get(route('admin.bot-settings.edit'))->assertOk();
        $this->dump('bot-studio-page', $response->getContent());

        $response->assertSee('Acme IG')
            ->assertSee('Page overrides')
            ->assertSee('Global prompt here')
            ->assertSee('Inherited from global:')
            ->assertSee('Page offer 10% off')
            ->assertSee('2 overrides') // offers + bot off
            ->assertSee(route('admin.bot-settings.account.update', $page), false)
            ->assertSee(route('admin.bot-settings.account.destroy', $page), false)
            ->assertSee('Save page settings');
    }

    public function test_account_deep_link_selects_the_page(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Deep Link Page']);

        $this->get(route('admin.bot-settings.account.edit', $page))
            ->assertRedirect(route('admin.bot-settings.edit'))
            ->assertSessionHas(CurrentPage::SESSION_KEY, $page->id);

        $this->get(route('admin.bot-settings.edit'))->assertOk()->assertSee('Deep Link Page')->assertSee('Page overrides');
    }

    public function test_validation_errors_flag_their_tab(): void
    {
        $payload = [
            'bot_enabled' => '1',
            'faqs' => [['question' => 'Only a question', 'answer' => '']],
            'reply_delay_seconds' => '99999',
        ];

        $this->from(route('admin.bot-settings.edit'))->put(route('admin.bot-settings.update'), $payload)
            ->assertRedirect(route('admin.bot-settings.edit'))
            ->assertSessionHasErrors(['faqs.0.answer', 'reply_delay_seconds']);

        $html = $this->from(route('admin.bot-settings.edit'))->followingRedirects()
            ->put(route('admin.bot-settings.update'), $payload)->assertOk()->getContent();
        $this->dump('bot-studio-errors', $html);
        $this->assertStringContainsString('Every FAQ question needs an answer.', $html);
        $this->assertSame(2, substr_count($html, 'title="Has errors"'));
        $this->assertStringContainsString('Only a question', $html); // old input kept
    }

    public function test_provider_cards_show_configuration_state(): void
    {
        config(['openai.api_key' => 'sk-test', 'anthropic.api_key' => null]);

        $this->get(route('admin.bot-settings.edit'))->assertOk()
            ->assertSee('Configured')
            ->assertSee('API key missing')
            ->assertSee('claude-opus-5')
            ->assertDontSee('sk-test');
    }

    public function test_guests_and_non_admins_are_blocked(): void
    {
        $this->post(route('logout'));
        auth()->logout();

        $this->get(route('admin.bot-settings.edit'))->assertRedirect('/login');
        $this->put(route('admin.bot-settings.update'), ['bot_enabled' => '1'])->assertRedirect('/login');
        $this->postJson(route('admin.bot-settings.playground'), [])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.bot-settings.edit'))->assertForbidden();
        $this->postJson(route('admin.bot-settings.playground'), [])->assertForbidden();
    }
}
