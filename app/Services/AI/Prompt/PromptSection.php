<?php

namespace App\Services\AI\Prompt;

/**
 * One block of the system instructions. Return null to omit the section.
 * Add new sections (lead qualification, CRM context, tools…) by implementing this and registering it
 * in AppServiceProvider.
 */
interface PromptSection
{
    public function render(PromptContext $context): ?string;
}
