<?php

namespace Tests\Feature\UI\Automations;

use App\Models\AutomationRule;
use App\Models\MetaAccount;
use App\Support\CurrentPage;
use Tests\Feature\Admin\AdminTestCase;

class AutomationRulesApiTest extends AdminTestCase
{
    private function rulePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Pricing',
            'match_type' => 'contains',
            'keywords' => ['price', 'How much'],
            'reply_text' => 'Hi {first_name}! From $20.',
            'active' => true,
            'meta_account_id' => null,
        ], $overrides);
    }

    public function test_create_update_and_delete_a_rule(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme']);

        $id = $this->postJson(route('admin.automations.store'), $this->rulePayload(['meta_account_id' => $page->id]))
            ->assertCreated()
            ->assertJsonPath('rule.name', 'Pricing')
            ->assertJsonPath('rule.scope_label', 'Acme')
            ->assertJsonPath('rule.keywords', ['price', 'How much'])
            ->json('rule.id');

        $rule = AutomationRule::findOrFail($id);
        $this->assertSame('keyword', $rule->trigger);
        $this->assertSame($page->id, $rule->meta_account_id);

        $this->putJson(route('admin.automations.update', $rule), $this->rulePayload(['name' => 'Prices', 'keywords' => 'cost, fee', 'match_type' => 'exact', 'priority' => 5]))
            ->assertOk()->assertJsonPath('rule.scope_label', 'All pages');

        $rule->refresh();
        $this->assertSame('Prices', $rule->name);
        $this->assertSame(['cost', 'fee'], $rule->keywords);
        $this->assertSame('exact', $rule->match_type);
        $this->assertSame(5, $rule->priority);
        $this->assertNull($rule->meta_account_id);

        $this->deleteJson(route('admin.automations.destroy', $rule))->assertOk();
        $this->assertModelMissing($rule);
    }

    public function test_plain_form_posts_redirect_back_with_a_toast(): void
    {
        $this->post(route('admin.automations.store'), $this->rulePayload())
            ->assertRedirect(route('admin.automations.index'))->assertSessionHas('success');
        $this->assertSame(1, AutomationRule::count());
    }

    public function test_validation_including_invalid_regex(): void
    {
        $this->postJson(route('admin.automations.store'), $this->rulePayload(['name' => '', 'keywords' => [], 'reply_text' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'keywords', 'reply_text']);

        $this->postJson(route('admin.automations.store'), $this->rulePayload(['match_type' => 'regex', 'keywords' => ['^(hi|hello']]))
            ->assertUnprocessable()->assertJsonValidationErrors('keywords');

        $this->postJson(route('admin.automations.store'), $this->rulePayload(['match_type' => 'fuzzy']))
            ->assertUnprocessable()->assertJsonValidationErrors('match_type');

        $this->postJson(route('admin.automations.store'), $this->rulePayload(['meta_account_id' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors('meta_account_id');

        $this->postJson(route('admin.automations.store'), $this->rulePayload(['match_type' => 'regex', 'keywords' => ['^(hi|hello)\b']]))
            ->assertCreated();
    }

    public function test_live_regex_check(): void
    {
        $this->postJson(route('admin.automations.api.regex'), ['patterns' => ['^price\b', '(unclosed', '']])
            ->assertOk()
            ->assertJsonPath('errors.0', null)
            ->assertJsonPath('errors.2', 'Pattern is empty.')
            ->assertJson(fn ($json) => $json->where('errors.1', fn ($e) => str_starts_with($e, 'Invalid regular expression'))->etc());
    }

    public function test_toggle_duplicate_and_reorder(): void
    {
        $a = AutomationRule::factory()->create(['name' => 'A', 'priority' => 0]);
        $b = AutomationRule::factory()->create(['name' => 'B', 'priority' => 0, 'trigger_count' => 9]);
        $c = AutomationRule::factory()->create(['name' => 'C', 'priority' => 0]);

        $this->patchJson(route('admin.automations.toggle', $a), ['active' => false])->assertOk()->assertJsonPath('rule.active', false);
        $this->assertFalse($a->fresh()->active);
        $this->patchJson(route('admin.automations.toggle', $a), [])->assertUnprocessable();

        $copy = $this->postJson(route('admin.automations.duplicate', $b))->assertCreated()->json('rule');
        $this->assertSame('B (copy)', $copy['name']);
        $this->assertFalse($copy['active']);
        $this->assertSame(0, $copy['trigger_count']);

        $this->postJson(route('admin.automations.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();
        $this->assertSame(['C', 'A', 'B'], AutomationRule::whereIn('id', [$a->id, $b->id, $c->id])->inMatchOrder()->pluck('name')->all());

        // Mixed scopes cannot be reordered together.
        $pageRule = AutomationRule::factory()->create(['meta_account_id' => MetaAccount::factory()->create()->id]);
        $this->postJson(route('admin.automations.reorder'), ['ids' => [$a->id, $pageRule->id]])->assertUnprocessable();
    }

    public function test_welcome_message_upsert_per_scope(): void
    {
        $page = MetaAccount::factory()->create();

        $this->putJson(route('admin.automations.welcome'), ['meta_account_id' => null, 'active' => true, 'reply_text' => 'Hi {first_name}!'])->assertOk();
        $this->putJson(route('admin.automations.welcome'), ['meta_account_id' => null, 'active' => false, 'reply_text' => 'Hello again'])->assertOk();
        $this->putJson(route('admin.automations.welcome'), ['meta_account_id' => $page->id, 'active' => true, 'reply_text' => 'Page welcome'])->assertOk();
        $this->putJson(route('admin.automations.welcome'), ['meta_account_id' => null, 'active' => true, 'reply_text' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('reply_text');

        $global = AutomationRule::where('trigger', 'welcome')->whereNull('meta_account_id')->sole();
        $this->assertSame('Hello again', $global->reply_text);
        $this->assertFalse($global->active);
        $this->assertSame('Page welcome', AutomationRule::where('trigger', 'welcome')->where('meta_account_id', $page->id)->sole()->reply_text);
    }

    public function test_preview_renders_variables(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme']);

        $this->postJson(route('admin.automations.api.preview'), ['text' => 'Hi {first_name}, welcome to {page_name}!', 'meta_account_id' => $page->id])
            ->assertOk()->assertJson(['text' => 'Hi Sara, welcome to Acme!']);
    }

    public function test_test_a_message_reports_rule_welcome_or_ai(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme']);
        AutomationRule::factory()->create(['name' => 'Page pricing', 'meta_account_id' => $page->id, 'keywords' => ['price'], 'reply_text' => 'Page price for {first_name}']);
        AutomationRule::factory()->create(['name' => 'Global pricing', 'keywords' => ['price']]);
        AutomationRule::factory()->welcome('Welcome to {page_name}')->create();
        session([CurrentPage::SESSION_KEY => $page->id]);

        $this->postJson(route('admin.automations.api.test'), ['text' => 'What is the PRICE?'])
            ->assertOk()->assertJson(['matched' => true, 'reason' => 'keyword', 'rule' => ['name' => 'Page pricing'], 'reply' => 'Page price for Sara']);

        $this->postJson(route('admin.automations.api.test'), ['text' => 'price?', 'meta_account_id' => null])
            ->assertOk()->assertJsonPath('rule.name', 'Global pricing');

        $this->postJson(route('admin.automations.api.test'), ['text' => 'hello', 'first_contact' => true])
            ->assertOk()->assertJson(['matched' => true, 'reason' => 'welcome', 'reply' => 'Welcome to Acme']);

        $this->postJson(route('admin.automations.api.test'), ['text' => 'hello', 'first_contact' => false])
            ->assertOk()->assertJson(['matched' => false, 'reason' => 'ai']);

        $this->postJson(route('admin.automations.api.test'), ['text' => ''])->assertUnprocessable();
        $this->assertSame(0, (int) AutomationRule::sum('trigger_count'));
    }
}
