<?php

namespace App\Services\AI\Prompt\Sections;

use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;

class OffersSection implements PromptSection
{
    public function render(PromptContext $context): ?string
    {
        $offers = trim((string) $context->settings->offers);

        return $offers === '' ? null : "CURRENT OFFERS AND PRICING (only quote prices listed here):\n".$offers;
    }
}
