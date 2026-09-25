<?php

namespace Tests\Feature\UI\Automations;

use App\Models\AutomationRule;
use App\Models\MetaAccount;
use App\Models\User;
use App\Support\CurrentPage;
use Tests\Feature\Admin\AdminTestCase;

class AutomationsPageTest extends AdminTestCase
{
    private function dump(string $name, string $html): void
    {
        if ($dir = env('UI_DUMP_DIR')) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.$name.'.html', $html);
        }
    }

    public function test_all_pages_lists_global_rules_only(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme Bakery']);
        AutomationRule::factory()->create(['name' => 'Global pricing', 'keywords' => ['price']]);
        AutomationRule::factory()->create(['name' => 'Page hours', 'meta_account_id' => $page->id]);
        AutomationRule::factory()->welcome('Welcome to {page_name}!')->create();

        $response = $this->get(route('admin.automations.index'))->assertOk();
        $this->dump('automations-all', $response->getContent());

        $response->assertSee('Global pricing')
            ->assertDontSee('Page hours')
            ->assertSee('Welcome to {page_name}!')
            ->assertSee('Acme Bakery · 1') // page-specific rules shortcut
            ->assertSee('Pick a page first') // messenger profile needs a page
            ->assertSee('Test a message')
            ->assertSee('automations(', false);
    }

    public function test_selected_page_lists_its_rules_and_global_rules(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme Bakery', 'settings' => ['messenger_profile' => [
            'greeting' => 'Hello from Acme', 'get_started' => true, 'ice_breakers' => [['question' => 'Opening hours?', 'payload' => 'HOURS']], 'updated_at' => now()->toIso8601String(),
        ]]]);
        $other = MetaAccount::factory()->create(['page_name' => 'Other Page']);
        AutomationRule::factory()->create(['name' => 'Global pricing']);
        AutomationRule::factory()->create(['name' => 'Page hours', 'meta_account_id' => $page->id]);
        AutomationRule::factory()->create(['name' => 'Other page rule', 'meta_account_id' => $other->id]);
        AutomationRule::factory()->welcome('Global welcome')->create();
        session([CurrentPage::SESSION_KEY => $page->id]);

        $response = $this->get(route('admin.automations.index'))->assertOk();
        $this->dump('automations-page', $response->getContent());

        $response->assertSee('Global pricing')
            ->assertSee('Page hours')
            ->assertDontSee('Other page rule')
            ->assertSee('Global welcome') // inherited welcome
            ->assertSee('Hello from Acme')
            ->assertSee('Opening hours?')
            ->assertSee('Save to Meta')
            ->assertDontSee('Pick a page first');
    }

    public function test_instagram_page_explains_ice_breakers_only(): void
    {
        $page = MetaAccount::factory()->instagram()->create();
        session([CurrentPage::SESSION_KEY => $page->id]);

        $this->get(route('admin.automations.index'))->assertOk()
            ->assertSee('Instagram supports ice breakers only')
            ->assertDontSee('id="profile-greeting"', false);
    }

    public function test_empty_state_offers_starter_rules(): void
    {
        $this->get(route('admin.automations.index'))->assertOk()
            ->assertSee('No keyword rules yet')
            ->assertSee('Opening hours')
            ->assertSee('Talk to a human');
    }

    public function test_guests_and_non_admins_are_blocked(): void
    {
        $rule = AutomationRule::factory()->create();
        $page = MetaAccount::factory()->create();
        auth()->logout();

        $this->get(route('admin.automations.index'))->assertRedirect('/login');
        $this->postJson(route('admin.automations.store'), [])->assertUnauthorized();
        $this->postJson(route('admin.automations.api.test'), ['text' => 'hi'])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.automations.index'))->assertForbidden();
        $this->deleteJson(route('admin.automations.destroy', $rule))->assertForbidden();
        $this->getJson(route('admin.automations.profile.show', $page))->assertForbidden();
        $this->assertModelExists($rule);
    }
}
