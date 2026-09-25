<?php

namespace App\Services\AI\Prompt\Sections;

use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;

class FaqSection implements PromptSection
{
    public function render(PromptContext $context): ?string
    {
        if ($context->settings->faqs === []) {
            return null;
        }

        $lines = array_map(
            fn (array $faq) => "Q: {$faq['question']}\nA: {$faq['answer']}",
            $context->settings->faqs,
        );

        return "FREQUENTLY ASKED QUESTIONS:\n".implode("\n\n", $lines);
    }
}
