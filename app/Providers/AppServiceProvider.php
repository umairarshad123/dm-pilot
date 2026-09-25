<?php

namespace App\Providers;

use App\Services\AI\AiProviderFactory;
use App\Services\AI\AiReplyProvider;
use App\Services\AI\ClaudeService;
use App\Services\AI\OpenAIService;
use App\Services\AI\PromptBuilder;
use App\Services\Automation\AutomationMatcher;
use App\Services\Bot\BotPlayground;
use App\Services\Bot\BotSettingsResolver;
use App\Services\Bot\ReplyPolicy;
use App\Services\Meta\MessageSplitter;
use App\Services\Meta\MetaGraphClient;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MetaGraphClient::class);
        $this->app->singleton(MessageSplitter::class);
        $this->app->singleton(MetaMessagingService::class);
        $this->app->singleton(OpenAIService::class);
        $this->app->singleton(ClaudeService::class);
        $this->app->singleton(AiProviderFactory::class);
        // Back-compat: the globally configured provider. Per-page code should use AiProviderFactory::for($settings).
        $this->app->bind(AiReplyProvider::class, fn ($app) => $app->make(AiProviderFactory::class)->for(config('ai.provider')));
        $this->app->singleton(AutomationMatcher::class);
        $this->app->singleton(BotPlayground::class);
        $this->app->singleton(BotSettingsResolver::class);
        $this->app->singleton(ReplyPolicy::class);

        // Prompt sections run in this order; add new PromptSection classes here.
        $this->app->singleton(PromptBuilder::class, fn ($app) => new PromptBuilder(
            array_map(fn (string $section) => $app->make($section), PromptBuilder::DEFAULT_SECTIONS),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
