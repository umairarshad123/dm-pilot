<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('OPENAI_MODEL', 'gpt-6-luna'),
    // Null = don't send (reasoning models reject temperature). Set e.g. 0.5 for non-reasoning models.
    'temperature' => env('OPENAI_TEMPERATURE') !== null ? (float) env('OPENAI_TEMPERATURE') : null,
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 500),
    'timeout' => (int) env('OPENAI_TIMEOUT', 30),
];
