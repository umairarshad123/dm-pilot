<?php

namespace Tests\Feature\Automation;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Jobs\GenerateAndSendReply;
use App\Jobs\ProcessIncomingMetaMessage;
use App\Models\AutomationRule;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\AI\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Pipeline\PipelineHelpers;
use Tests\TestCase;

class AutomationReplyTest extends TestCase
{
    use PipelineHelpers, RefreshDatabase;

    private MetaAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPipeline();
        $this->account = MetaAccount::factory()->create(['page_name' => 'Acme Bikes']);
    }

    private function send(string $text, string $mid = 'm_in_1', array $overrides = []): void
    {
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, [
            'text' => $text, 'messageId' => $mid, 'raw' => ['message' => ['mid' => $mid, 'text' => $text]],
        ] + $overrides));
    }

    /** Like fakeApis() but every Graph send gets a unique message id (several replies per test). */
    private function fakeApisMulti(string $reply = 'AI answer'): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiBody($reply)),
            'graph.facebook.com/*' => fn () => Http::response(['recipient_id' => '9001', 'message_id' => 'm_out_'.uniqid('', true)]),
        ]);
    }

    private function lastReply(): ?Message
    {
        return Message::where('direction', MessageDirection::Outgoing)->latest('id')->first();
    }

    public function test_keyword_rule_replaces_the_ai_reply(): void
    {
        $this->fakeApis('AI answer');
        $rule = AutomationRule::factory()->create(['keywords' => ['price', 'cost'], 'reply_text' => 'Plans start at $20 on {page_name}.']);

        $this->send('Hi, what is the PRICE?');

        $reply = $this->lastReply();
        $this->assertSame('Plans start at $20 on Acme Bikes.', $reply->body);
        $this->assertSame(MessageStatus::Sent, $reply->status);
        $this->assertSame(SenderType::Bot, $reply->sender_type);
        $this->assertSame(['source' => 'automation', 'automation_rule_id' => $rule->id, 'trigger' => 'keyword'], $reply->payload);
        $this->assertSame('Plans start at $20 on Acme Bikes.', $this->graphSends()[0]['message']['text']);
        $this->assertSame(0, $this->openAiCalls());

        $rule->refresh();
        $this->assertSame(1, $rule->trigger_count);
        $this->assertNotNull($rule->last_triggered_at);
        $this->assertTrue(collect($this->logs)->contains(fn ($l) => $l['message'] === 'Automation rule matched.'
            && $l['context']['automation_rule_id'] === $rule->id));
    }

    public function test_no_matching_rule_uses_ai_and_records_source(): void
    {
        $this->fakeApis('AI answer');
        AutomationRule::factory()->create(['keywords' => ['refund']]);
        AutomationRule::factory()->inactive()->create(['keywords' => ['price']]);
        AutomationRule::factory()->create(['keywords' => ['price'], 'meta_account_id' => MetaAccount::factory()->create()->id]);

        $this->send('what is the price');

        $reply = $this->lastReply();
        $this->assertSame('AI answer', $reply->body);
        $this->assertSame('ai', $reply->payload['source']);
        $this->assertSame('openai', $reply->payload['provider']);
        $this->assertSame(1, $this->openAiCalls());
    }

    public function test_page_rules_win_over_global_then_priority(): void
    {
        $this->fakeApis();
        AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'global high', 'priority' => 100]);
        AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'page low', 'priority' => 1, 'meta_account_id' => $this->account->id]);
        AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'page high', 'priority' => 5, 'meta_account_id' => $this->account->id]);

        $this->send('price?');

        $this->assertSame('page high', $this->lastReply()->body);
    }

    public function test_welcome_rule_answers_the_first_message_only(): void
    {
        $this->fakeApisMulti('AI answer');
        AutomationRule::factory()->welcome('Hi {first_name}! Welcome to {page_name}.')->create();

        $this->send('hello');
        $this->assertSame('Hi! Welcome to Acme Bikes.', $this->lastReply()->body);
        $this->assertSame(0, $this->openAiCalls());

        $this->send('do you have helmets', 'm_in_2');
        $this->assertSame('AI answer', $this->lastReply()->body);
    }

    public function test_welcome_uses_first_name_and_page_welcome_overrides_global(): void
    {
        $this->fakeApis();
        AutomationRule::factory()->welcome('Global welcome')->create(['priority' => 50]);
        AutomationRule::factory()->welcome('Hey {first_name}, page welcome')->create(['meta_account_id' => $this->account->id]);
        Conversation::factory()->for($this->account)->create(['external_user_id' => '9001', 'customer_name' => 'Sara Khan']);

        $this->send('hello');

        $this->assertSame('Hey Sara, page welcome', $this->lastReply()->body);
    }

    public function test_keyword_beats_welcome_on_first_message(): void
    {
        $this->fakeApis();
        AutomationRule::factory()->welcome()->create();
        AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'Keyword reply']);

        $this->send('price');

        $this->assertSame('Keyword reply', $this->lastReply()->body);
    }

    public function test_welcome_after_debounced_burst(): void
    {
        $this->fakeApis();
        AutomationRule::factory()->welcome('Welcome!')->create();
        Bus::fake([GenerateAndSendReply::class]);

        $this->send('hi');
        $this->send('anyone there?', 'm_in_2');

        foreach (Message::where('direction', MessageDirection::Incoming)->orderBy('id')->pluck('id') as $id) {
            app()->call([(new GenerateAndSendReply($id))->withFakeQueueInteractions(), 'handle']);
        }

        $this->assertSame(1, Message::where('direction', MessageDirection::Outgoing)->count());
        $this->assertSame('Welcome!', $this->lastReply()->body);
        $this->assertSame(MessageStatus::Sent, $this->lastReply()->status);
    }

    public function test_get_started_postback_triggers_welcome_even_for_returning_customer(): void
    {
        $this->fakeApis('AI answer');
        AutomationRule::factory()->welcome('Welcome back!')->create();
        $conversation = Conversation::factory()->for($this->account)->create(['external_user_id' => '9001']);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'earlier reply', 'created_at' => now()->subDay()]);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, [
            'text' => 'Get Started', 'messageId' => 'postback_1',
            'raw' => ['postback' => ['title' => 'Get Started', 'payload' => 'GET_STARTED']],
        ]));

        $this->assertSame('Welcome back!', $this->lastReply()->body);
    }

    public function test_ice_breaker_payload_matches_keyword_rule(): void
    {
        $this->fakeApis();
        AutomationRule::factory()->create([
            'match_type' => AutomationRule::MATCH_EXACT, 'keywords' => ['HOURS_PAYLOAD'], 'reply_text' => 'We are open 9-5.',
        ]);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, [
            'text' => 'What are your hours?', 'messageId' => 'postback_2',
            'raw' => ['postback' => ['title' => 'What are your hours?', 'payload' => 'HOURS_PAYLOAD']],
        ]));

        $this->assertSame('We are open 9-5.', $this->lastReply()->body);
    }

    public function test_rules_work_without_ai_key_but_other_messages_are_skipped(): void
    {
        $this->fakeApisMulti();
        config(['openai.api_key' => null]);
        AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'From $20.']);

        $this->send('price please');
        $this->assertSame('From $20.', $this->lastReply()->body);

        $this->send('something else', 'm_in_2');
        $this->assertSame(1, Message::where('direction', MessageDirection::Outgoing)->count());
        $this->assertCount(1, $this->graphSends());
        $this->assertTrue(collect($this->logs)->contains(fn ($l) => ($l['context']['reason'] ?? null) === 'ai_not_configured'));
    }

    public function test_retry_reuses_stored_rule_reply_without_double_counting(): void
    {
        config(['meta.sender_actions' => false]);
        $rule = AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'From $20.']);
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary', 'code' => 2]], 500)
                ->push(['message_id' => 'm_ok']),
        ]);
        Bus::fake([GenerateAndSendReply::class]);
        $this->send('price');
        $id = Message::where('direction', MessageDirection::Incoming)->sole()->id;

        $job = (new GenerateAndSendReply($id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);
        $job->assertReleased();
        $this->assertSame(MessageStatus::Pending, $this->lastReply()->status);

        $retry = (new GenerateAndSendReply($id))->withFakeQueueInteractions();
        $retry->job->attempts = 2;
        app()->call([$retry, 'handle']);

        $this->assertSame(MessageStatus::Sent, $this->lastReply()->status);
        $this->assertSame('From $20.', $this->lastReply()->body);
        $this->assertSame(1, $rule->fresh()->trigger_count);
    }

    public function test_automations_can_be_disabled_by_config(): void
    {
        $this->fakeApis('AI answer');
        config(['bot.automations.enabled' => false]);
        AutomationRule::factory()->create(['keywords' => ['price']]);

        $this->send('price');

        $this->assertSame('AI answer', $this->lastReply()->body);
    }

    public function test_per_page_claude_provider_is_used(): void
    {
        $this->fakeApis('OpenAI answer');
        $claude = new FakeAiProvider('Claude answer');
        $this->app->instance(ClaudeService::class, $claude);
        BotSetting::create(['meta_account_id' => $this->account->id, 'ai_provider' => 'claude']);
        config(['openai.api_key' => null]); // only Claude is "configured"

        $this->send('hello there');

        $reply = $this->lastReply();
        $this->assertSame('Claude answer', $reply->body);
        $this->assertSame(['source' => 'ai', 'provider' => 'claude', 'model' => config('anthropic.model')], $reply->payload);
        $this->assertCount(1, $claude->calls);
        $this->assertSame(0, $this->openAiCalls());
    }
}
