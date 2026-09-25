<?php

namespace App\Services\AI\Prompt\Sections;

use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;

class SystemPromptSection implements PromptSection
{
    public function render(PromptContext $context): ?string
    {
        return trim($context->settings->systemPrompt) ?: null;
    }
}
