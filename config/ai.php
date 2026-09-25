<?php

return [
    // Default LLM for replies: "openai" (config/openai.php) or "claude" (config/anthropic.php).
    // A page can override it via bot_settings.ai_provider (account row → global row → this value).
    'provider' => env('AI_PROVIDER', 'openai'),

    // Providers offered in the admin UI (App\Services\AI\AiProviderFactory::available()).
    // "config" is the config file holding the provider's api_key / model / max_output_tokens defaults.
    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'config' => 'openai',
            'models' => ['gpt-6-luna', 'gpt-6-sol'],
        ],
        'claude' => [
            'label' => 'Claude',
            'config' => 'anthropic',
            'models' => ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5'],
        ],
    ],
];
