<?php

namespace Tests\Feature\UI\BotStudio;

use App\Exceptions\AiProviderException;
use App\Models\AutomationRule;
use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Admin\AdminTestCase;
use Tests\Feature\Automation\FakeAiProvider;

class PlaygroundEndpointTest extends AdminTestCase
{
    private FakeAiProvider $openai;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->openai = new FakeAiProvider('Hi from the AI');
        $this->app->instance(OpenAIService::class, $this->openai);
    }

    private function send(array $overrides = []): TestResponse
    {
        return $this->postJson(route('admin.bot-settings.playground'), array_merge([
            'platform' => 'facebook',
            'messages' => [['role' => 'user', 'content' => 'Hello there']],
        ], $overrides));
    }

    public function test_ai_reply_with_saved_page_settings(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme']);
        BotSetting::create(['meta_account_id' => $page->id, 'business_info' => 'We sell bikes.', 'bot_enabled' => false]);

        $this->send(['meta_account_id' => $page->id, 'platform' => 'instagram'])
            ->assertOk()
            ->assertJson(['text' => 'Hi from the AI', 'source' => 'ai', 'provider' => 'openai'])
            ->assertJsonStructure(['latency_ms', 'model']);

        $this->assertStringContainsString('We sell bikes.', $this->openai->calls[0]['instructions']);
    }

    public function test_automation_reply_includes_rule_name_and_uses_variables(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Acme']);
        AutomationRule::factory()->create(['name' => 'Pricing rule', 'keywords' => ['price'], 'reply_text' => 'Hi {first_name}! See {page_name} prices.']);

        $this->send([
            'meta_account_id' => $page->id,
            'messages' => [['role' => 'user', 'content' => 'what is the price?']],
            'customer_name' => 'Sara Khan',
        ])->assertOk()->assertJson(['source' => 'automation', 'rule_name' => 'Pricing rule', 'text' => 'Hi Sara! See Acme prices.']);

        $this->assertSame([], $this->openai->calls);
        $this->assertSame(0, AutomationRule::sole()->trigger_count); // playground never counts
    }

    public function test_get_started_payload_triggers_welcome_rule(): void
    {
        AutomationRule::factory()->welcome('Welcome to {page_name}!')->create();

        $this->send(['messages' => [['role' => 'user', 'content' => 'Get Started']], 'payload' => 'GET_STARTED'])
            ->assertOk()->assertJson(['source' => 'automation', 'text' => 'Welcome to!']);
    }

    public function test_provider_errors_are_returned_as_422(): void
    {
        $this->openai->configured = false;
        $this->send()->assertStatus(422)->assertJson(['message' => 'The OpenAI API key is not configured.']);

        $this->openai->configured = true;
        $this->openai->throw = new AiProviderException('OpenAI timed out.');
        $this->send()->assertStatus(422)->assertJson(['message' => 'OpenAI timed out.']);

        $this->app->instance(ClaudeService::class, new FakeAiProvider('Claude here'));
        BotSetting::global()->update(['ai_provider' => 'claude']);
        $this->send()->assertOk()->assertJson(['text' => 'Claude here', 'provider' => 'claude']);
    }

    public function test_input_is_validated(): void
    {
        $this->send(['platform' => 'whatsapp'])->assertStatus(422)->assertJsonValidationErrors('platform');
        $this->send(['messages' => []])->assertStatus(422)->assertJsonValidationErrors('messages');
        $this->send(['messages' => [['role' => 'system', 'content' => 'x']]])->assertStatus(422)->assertJsonValidationErrors('messages.0.role');
        $this->send(['meta_account_id' => 999])->assertStatus(422)->assertJsonValidationErrors('meta_account_id');
        $this->send(['messages' => [['role' => 'user', 'content' => 'hi'], ['role' => 'assistant', 'content' => 'hello']]])
            ->assertStatus(422)->assertJson(['message' => 'The last playground message must be from the user.']);
    }
}
