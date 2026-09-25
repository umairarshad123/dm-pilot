<?php

return [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    // Includes adaptive-thinking tokens, so keep headroom above the visible reply length.
    'max_output_tokens' => (int) env('ANTHROPIC_MAX_OUTPUT_TOKENS', 2000),
    // Short DM replies don't need deep reasoning: low effort keeps latency and cost down.
    'effort' => env('ANTHROPIC_EFFORT', 'low'),
    'timeout' => (float) env('ANTHROPIC_TIMEOUT', 60),
];
