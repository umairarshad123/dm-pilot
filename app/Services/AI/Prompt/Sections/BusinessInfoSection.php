<?php

namespace App\Services\AI\Prompt\Sections;

use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;

class BusinessInfoSection implements PromptSection
{
    public function render(PromptContext $context): ?string
    {
        $info = trim((string) $context->settings->businessInfo);

        return $info === '' ? null : "BUSINESS INFORMATION:\n".$info;
    }
}
