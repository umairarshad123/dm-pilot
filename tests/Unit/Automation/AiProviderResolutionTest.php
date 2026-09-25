<?php

namespace Tests\Unit\Automation;

use App\Models\BotSetting;
use App\Models\MetaAccount;
use App\Services\AI\AiProviderFactory;
use App\Services\AI\AiReplyProvider;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAIService;
use App\Services\Bot\BotSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ai.provider' => 'openai',
            'openai.model' => 'gpt-6-luna', 'openai.max_output_tokens' => 500,
            'anthropic.model' => 'claude-opus-5', 'anthropic.max_output_tokens' => 2000,
        ]);
    }

    private function resolve(?MetaAccount $account = null)
    {
        return app(BotSettingsResolver::class)->forAccount($account);
    }

    public function test_config_provider_is_the_default(): void
    {
        $this->assertSame('openai', $this->resolve()->aiProvider);
        $this->assertSame('gpt-6-luna', $this->resolve()->model);

        config(['ai.provider' => 'claude']);
        $settings = $this->resolve();
        $this->assertSame('claude', $settings->aiProvider);
        $this->assertSame('claude-opus-5', $settings->model);
        $this->assertSame(2000, $settings->maxOutputTokens);
    }

    public function test_account_row_overrides_global_row_and_model_follows_provider(): void
    {
        $account = MetaAccount::factory()->create();
        $other = MetaAccount::factory()->create();
        BotSetting::create(['meta_account_id' => null, 'ai_provider' => 'claude']);
        BotSetting::create(['meta_account_id' => $account->id, 'ai_provider' => 'openai']);

        $this->assertSame('openai', $this->resolve($account)->aiProvider);
        $this->assertSame('gpt-6-luna', $this->resolve($account)->model);
        $this->assertSame(500, $this->resolve($account)->maxOutputTokens);

        $this->assertSame('claude', $this->resolve($other)->aiProvider);
        $this->assertSame('claude-opus-5', $this->resolve($other)->model);
    }

    public function test_model_of_other_provider_falls_back_but_unknown_and_matching_models_are_kept(): void
    {
        $account = MetaAccount::factory()->create();
        BotSetting::create(['meta_account_id' => null, 'model' => 'gpt-6-sol']);
        BotSetting::create(['meta_account_id' => $account->id, 'ai_provider' => 'claude']);

        $this->assertSame('claude-opus-5', $this->resolve($account)->model); // gpt model under claude → default
        $this->assertSame('gpt-6-sol', $this->resolve()->model);

        BotSetting::where('meta_account_id', $account->id)->update(['model' => 'claude-haiku-4-5']);
        $this->assertSame('claude-haiku-4-5', $this->resolve($account)->model);

        BotSetting::where('meta_account_id', $account->id)->update(['model' => 'my-finetune']);
        $this->assertSame('my-finetune', $this->resolve($account)->model);
    }

    public function test_invalid_provider_value_is_ignored(): void
    {
        BotSetting::create(['meta_account_id' => null, 'ai_provider' => 'gemini']);

        $this->assertSame('openai', $this->resolve()->aiProvider);
    }

    public function test_factory_returns_provider_instances(): void
    {
        $factory = app(AiProviderFactory::class);

        $this->assertInstanceOf(OpenAIService::class, $factory->for('openai'));
        $this->assertInstanceOf(ClaudeService::class, $factory->for('claude'));
        $this->assertInstanceOf(ClaudeService::class, $factory->for('Anthropic'));
        $this->assertInstanceOf(OpenAIService::class, $factory->for('nope')); // config default

        BotSetting::create(['meta_account_id' => null, 'ai_provider' => 'claude']);
        $this->assertInstanceOf(ClaudeService::class, $factory->for($this->resolve()));

        // Back-compat container binding follows config('ai.provider').
        $this->assertInstanceOf(OpenAIService::class, app(AiReplyProvider::class));
        config(['ai.provider' => 'claude']);
        $this->assertInstanceOf(ClaudeService::class, app(AiReplyProvider::class));
    }

    public function test_available_lists_providers_for_the_ui(): void
    {
        config(['openai.api_key' => 'sk-test', 'anthropic.api_key' => null]);

        $available = app(AiProviderFactory::class)->available();

        $this->assertSame(['openai', 'claude'], array_column($available, 'key'));
        $this->assertSame(['OpenAI', 'Claude'], array_column($available, 'label'));
        $this->assertSame([true, false], array_column($available, 'configured'));
        $this->assertSame(['gpt-6-luna', 'gpt-6-sol'], $available[0]['models']);
        $this->assertSame(['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5'], $available[1]['models']);
        $this->assertSame('claude-opus-5', $available[1]['default_model']);
    }

    public function test_provider_for_model(): void
    {
        $this->assertSame('claude', AiProviderFactory::providerForModel('claude-sonnet-5'));
        $this->assertSame('openai', AiProviderFactory::providerForModel('gpt-6-luna'));
        $this->assertSame('openai', AiProviderFactory::providerForModel('o4-mini'));
        $this->assertNull(AiProviderFactory::providerForModel('llama-3'));
        $this->assertNull(AiProviderFactory::providerForModel(null));
    }
}
