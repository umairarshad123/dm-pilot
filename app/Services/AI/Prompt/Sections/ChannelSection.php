<?php

namespace App\Services\AI\Prompt\Sections;

use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;

class ChannelSection implements PromptSection
{
    public function render(PromptContext $context): ?string
    {
        $instruction = trim((string) $context->settings->channelInstruction($context->platform->value));

        return $instruction === '' ? null : "CHANNEL:\n".$instruction;
    }
}
