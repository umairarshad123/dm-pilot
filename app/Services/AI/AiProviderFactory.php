<?php

namespace App\Services\AI;

use App\Services\Bot\BotSettings;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the AI provider for a page (BotSettings::$aiProvider) or by key ("openai" | "claude").
 * Providers are container singletons, so tests can swap them with $this->app->instance(...).
 */
class AiProviderFactory
{
    public const OPENAI = 'openai';

    public const CLAUDE = 'claude';

    private const CLASSES = [
        self::OPENAI => OpenAIService::class,
        self::CLAUDE => ClaudeService::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(BotSettings|string|null $provider): AiReplyProvider
    {
        $key = $provider instanceof BotSettings ? $provider->aiProvider : $provider;

        return $this->container->make(self::CLASSES[self::normalize($key) ?? self::defaultProvider()]);
    }

    /**
     * Providers for the settings UI.
     *
     * @return list<array{key: string, label: string, configured: bool, default_model: string, models: list<string>}>
     */
    public function available(): array
    {
        $list = [];

        foreach (array_keys(self::CLASSES) as $key) {
            $default = self::defaultModel($key);
            $list[] = [
                'key' => $key,
                'label' => (string) config("ai.providers.$key.label", ucfirst($key)),
                'configured' => $this->for($key)->isConfigured(),
                'default_model' => $default,
                'models' => array_values(array_unique(array_filter([
                    ...(array) config("ai.providers.$key.models", []), $default,
                ]))),
            ];
        }

        return $list;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CLASSES);
    }

    /** "openai" | "claude" for a known key (case-insensitive; "anthropic" → claude), else null. */
    public static function normalize(?string $provider): ?string
    {
        $provider = strtolower(trim((string) $provider));

        return match ($provider) {
            'openai' => self::OPENAI,
            'claude', 'anthropic' => self::CLAUDE,
            default => null,
        };
    }

    /** config('ai.provider'), normalised (falls back to openai). */
    public static function defaultProvider(): string
    {
        return self::normalize((string) config('ai.provider')) ?? self::OPENAI;
    }

    /** Config file with the provider's defaults: "openai" or "anthropic". */
    public static function configKey(string $provider): string
    {
        return self::normalize($provider) === self::CLAUDE ? 'anthropic' : 'openai';
    }

    public static function defaultModel(string $provider): string
    {
        return (string) config(self::configKey($provider).'.model');
    }

    /** Which provider a model id belongs to, or null when unknown (unknown ids are passed through as-is). */
    public static function providerForModel(?string $model): ?string
    {
        $model = strtolower(trim((string) $model));

        return match (true) {
            $model === '' => null,
            str_starts_with($model, 'claude') => self::CLAUDE,
            (bool) preg_match('/^(gpt|chatgpt|o\d|codex|ft:gpt|davinci|babbage)/', $model) => self::OPENAI,
            default => null,
        };
    }
}
