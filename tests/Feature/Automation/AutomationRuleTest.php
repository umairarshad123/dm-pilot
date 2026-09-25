<?php

namespace Tests\Feature\Automation;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\Automation\AutomationMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AutomationRuleTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data): array
    {
        return Validator::make($data, AutomationRule::validationRules())->errors()->toArray();
    }

    public function test_validation_rules(): void
    {
        $account = MetaAccount::factory()->create();

        $this->assertSame([], $this->validate([
            'meta_account_id' => $account->id, 'name' => 'Prices', 'trigger' => 'keyword', 'match_type' => 'contains',
            'keywords' => ['price', 'cost'], 'reply_text' => 'From $20', 'priority' => 5, 'active' => true,
        ]));
        $this->assertSame([], $this->validate(['name' => 'Hi', 'trigger' => 'welcome', 'reply_text' => 'Welcome!']));
        $this->assertSame([], $this->validate([
            'name' => 'Order', 'trigger' => 'keyword', 'match_type' => 'regex', 'keywords' => ['^order\s*\d+$'], 'reply_text' => 'x',
        ]));

        $this->assertArrayHasKey('keywords', $this->validate(['name' => 'x', 'trigger' => 'keyword', 'match_type' => 'contains', 'reply_text' => 'x']));
        $this->assertArrayHasKey('keywords', $this->validate(['name' => 'x', 'trigger' => 'keyword', 'match_type' => 'contains', 'keywords' => ['  '], 'reply_text' => 'x']));
        $this->assertArrayHasKey('keywords', $this->validate(['name' => 'x', 'trigger' => 'keyword', 'match_type' => 'regex', 'keywords' => ['(oops'], 'reply_text' => 'x']));
        $this->assertArrayHasKey('trigger', $this->validate(['name' => 'x', 'trigger' => 'default', 'reply_text' => 'x']));
        $this->assertArrayHasKey('match_type', $this->validate(['name' => 'x', 'trigger' => 'keyword', 'match_type' => 'fuzzy', 'keywords' => ['a'], 'reply_text' => 'x']));
        $this->assertArrayHasKey('meta_account_id', $this->validate(['meta_account_id' => 999, 'name' => 'x', 'trigger' => 'welcome', 'reply_text' => 'x']));
        $this->assertArrayHasKey('reply_text', $this->validate(['name' => 'x', 'trigger' => 'welcome']));
    }

    public function test_defaults_casts_and_record_trigger(): void
    {
        $rule = AutomationRule::create(['name' => 'x', 'keywords' => [' Price ', 'price'], 'reply_text' => 'y']);
        $rule->refresh();

        $this->assertSame('keyword', $rule->trigger);
        $this->assertSame('contains', $rule->match_type);
        $this->assertTrue($rule->active);
        $this->assertSame(['price'], $rule->normalizedKeywords());

        $rule->recordTrigger();
        $rule->recordTrigger();
        $this->assertSame(2, $rule->trigger_count);
        $this->assertSame(2, $rule->fresh()->trigger_count);
        $this->assertNotNull($rule->fresh()->last_triggered_at);
    }

    public function test_rules_are_deleted_with_their_account(): void
    {
        $account = MetaAccount::factory()->create();
        AutomationRule::factory()->create(['meta_account_id' => $account->id]);
        AutomationRule::factory()->create();

        $account->delete();

        $this->assertSame(1, AutomationRule::count());
    }

    public function test_matcher_first_contact_and_quick_reply_payload(): void
    {
        $conversation = Conversation::factory()->create();
        $incoming = Message::factory()->for($conversation)->create([
            'body' => 'Yes please',
            'payload' => ['message' => ['quick_reply' => ['payload' => 'BOOK_NOW']]],
        ]);
        $matcher = app(AutomationMatcher::class);

        $this->assertSame('BOOK_NOW', AutomationMatcher::postbackPayload($incoming));
        $this->assertTrue($matcher->isFirstContact($conversation));

        $rule = AutomationRule::factory()->create(['match_type' => 'exact', 'keywords' => ['book_now']]);
        $this->assertTrue($rule->is($matcher->match($conversation, $incoming)));

        Message::factory()->for($conversation)->fromBot()->create(['status' => MessageStatus::Failed]);
        $this->assertTrue($matcher->isFirstContact($conversation));
        Message::factory()->for($conversation)->create(['direction' => MessageDirection::Outgoing, 'sender_type' => 'human', 'status' => MessageStatus::Sent]);
        $this->assertFalse($matcher->isFirstContact($conversation));
        $this->assertTrue($matcher->isFirstContact($conversation, 'GET_STARTED'));
    }
}
