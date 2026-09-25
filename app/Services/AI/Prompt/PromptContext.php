<?php

namespace App\Services\AI\Prompt;

use App\Enums\Platform;
use App\Models\Conversation;
use App\Services\Bot\BotSettings;

final readonly class PromptContext
{
    public function __construct(
        public BotSettings $settings,
        public Platform $platform,
        public ?Conversation $conversation = null,
    ) {}
}
