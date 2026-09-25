<?php

// Global defaults. Values in the bot_settings table (global row, then per-account row) override these.
return [
    'enabled' => env('BOT_ENABLED', true),
    'history_limit' => (int) env('BOT_HISTORY_LIMIT', 20),
    'reply_delay_seconds' => (int) env('BOT_REPLY_DELAY_SECONDS', 0),
    'human_takeover_minutes' => (int) env('BOT_HUMAN_TAKEOVER_MINUTES', 60),
    'fallback_message' => env('BOT_FALLBACK_MESSAGE'), // null = no reply when AI fails

    'system_prompt' => env('BOT_SYSTEM_PROMPT', <<<'PROMPT'
You are a friendly, helpful assistant replying to customer direct messages on behalf of the business.
Keep replies short and conversational (1-3 sentences), like a real person texting.
Only use the business information provided. If you do not know something, say a team member will follow up.
Never invent prices, policies or availability. Do not use markdown formatting.
PROMPT),

    'channel_instructions' => [
        'facebook' => 'You are replying in Facebook Messenger.',
        'instagram' => 'You are replying in Instagram DMs. Keep it extra brief and casual.',
    ],

    // Keyword / welcome automations (App\Services\Automation). Rule replies are sent instead of the AI.
    'automations' => [
        'enabled' => env('BOT_AUTOMATIONS_ENABLED', true),
        // Postback payload of the Messenger "Get Started" button; it always counts as a first contact.
        'get_started_payload' => 'GET_STARTED',
    ],
];
