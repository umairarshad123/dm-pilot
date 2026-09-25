<?php

namespace Tests\Feature\Automation;

use App\Exceptions\AiProviderException;
use App\Models\AutomationRule;
use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAIService;
use App\Services\Bot\BotPlayground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class BotPlaygroundTest extends TestCase
{
    use RefreshDatabase;

    private MetaAccount $account;

    private FakeAiProvider $openai;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->account = MetaAccount::factory()->create(['page_name' => 'Acme']);
        $this->openai = new FakeAiProvider('AI says hi');
        $this->app->instance(OpenAIService::class, $this->openai);
    }

    private function playground(): BotPlayground
    {
        return app(BotPlayground::class);
    }

    public function test_ai_reply_uses_page_settings_prompt_and_history(): void
    {
        BotSetting::create(['meta_account_id' => $this->account->id, 'business_info' => 'We sell bikes.', 'history_limit' => 2]);

        $result = $this->playground()->reply($this->account, 'instagram', [
            ['role' => 'user', 'content' => 'first'],
            ['role' => 'assistant', 'content' => 'answer'],
            ['role' => 'system', 'content' => 'ignored'],
            ['role' => 'user', 'content' => '  do you deliver? '],
        ]);

        $this->assertSame('AI says hi', $result['text']);
        $this->assertSame('ai', $result['source']);
        $this->assertSame('openai', $result['provider']);
        $this->assertSame(config('openai.model'), $result['model']);
        $this->assertIsInt($result['latency_ms']);
        $this->assertArrayNotHasKey('rule_id', $result);

        $call = $this->openai->calls[0];
        $this->assertStringContainsString('We sell bikes.', $call['instructions']);
        $this->assertStringContainsString('Instagram', $call['instructions']);
        $this->assertSame([
            ['role' => 'assistant', 'content' => 'answer'],
            ['role' => 'user', 'content' => 'do you deliver?'],
        ], $call['input']);
    }

    public function test_keyword_rule_answers_without_ai_and_without_db_writes(): void
    {
        $rule = AutomationRule::factory()->create(['keywords' => ['price'], 'reply_text' => 'Hi {first_name}, from $20 at {page_name}.']);
        config(['openai.api_key' => null]);
        $this->openai->configured = false;

        DB::enableQueryLog();
        $result = $this->playground()->reply($this->account, 'facebook', [
            ['role' => 'user', 'content' => 'hello'],
            ['role' => 'assistant', 'content' => 'hey'],
            ['role' => 'user', 'content' => 'price?'],
        ], 'Sara Khan');

        $this->assertSame('Hi Sara, from $20 at Acme.', $result['text']);
        $this->assertSame('automation', $result['source']);
        $this->assertSame($rule->id, $result['rule_id']);
        $this->assertSame([], $this->openai->calls);
        $this->assertSame(0, $rule->fresh()->trigger_count);
        $writes = collect(DB::getQueryLog())->filter(fn ($q) => ! str_starts_with(strtolower(ltrim($q['query'])), 'select'));
        $this->assertCount(0, $writes);
    }

    public function test_welcome_only_before_the_bot_has_replied(): void
    {
        AutomationRule::factory()->welcome('Welcome!')->create(['meta_account_id' => $this->account->id]);

        $first = $this->playground()->reply($this->account, 'facebook', [['role' => 'user', 'content' => 'hello']]);
        $later = $this->playground()->reply($this->account, 'facebook', [
            ['role' => 'user', 'content' => 'hello'], ['role' => 'assistant', 'content' => 'Welcome!'], ['role' => 'user', 'content' => 'ok'],
        ]);
        $getStarted = $this->playground()->reply($this->account, 'facebook', [
            ['role' => 'user', 'content' => 'hello'], ['role' => 'assistant', 'content' => 'Welcome!'], ['role' => 'user', 'content' => 'Get Started'],
        ], payload: 'GET_STARTED');

        $this->assertSame('automation', $first['source']);
        $this->assertSame('ai', $later['source']);
        $this->assertSame('Welcome!', $getStarted['text']);
    }

    public function test_page_provider_is_used(): void
    {
        $claude = new FakeAiProvider('Claude says hi');
        $this->app->instance(ClaudeService::class, $claude);
        BotSetting::create(['meta_account_id' => $this->account->id, 'ai_provider' => 'claude', 'model' => 'claude-sonnet-5']);

        $result = $this->playground()->reply($this->account, 'facebook', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['Claude says hi', 'claude', 'claude-sonnet-5'], [$result['text'], $result['provider'], $result['model']]);
        $this->assertSame('claude-sonnet-5', $claude->calls[0]['model']);
    }

    public function test_global_settings_without_account(): void
    {
        $result = $this->playground()->reply(null, 'facebook', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('ai', $result['source']);
    }

    public function test_unconfigured_provider_throws_safe_exception(): void
    {
        $this->openai->configured = false;

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('OpenAI API key is not configured.');

        $this->playground()->reply($this->account, 'facebook', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_provider_failure_is_rethrown(): void
    {
        $this->openai->throw = new AiProviderException('OpenAI error (HTTP 500): boom', retryable: true);

        $this->expectException(AiProviderException::class);

        $this->playground()->reply($this->account, 'facebook', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_invalid_input_is_rejected(): void
    {
        foreach ([
            ['whatsapp', [['role' => 'user', 'content' => 'hi']]],
            ['facebook', []],
            ['facebook', [['role' => 'user', 'content' => 'hi'], ['role' => 'assistant', 'content' => 'yo']]],
        ] as [$platform, $history]) {
            try {
                $this->playground()->reply($this->account, $platform, $history);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
