<?php

namespace Tests\Unit\Automation;

use App\Services\Automation\ReplyTemplate;
use PHPUnit\Framework\TestCase;

class ReplyTemplateTest extends TestCase
{
    public function test_variables_are_replaced(): void
    {
        $vars = ReplyTemplate::variables('Sara  Khan', 'Acme Bikes');

        $this->assertSame(['first_name' => 'Sara', 'name' => 'Sara  Khan', 'page_name' => 'Acme Bikes'], $vars);
        $this->assertSame('Hi Sara! Welcome to Acme Bikes.', ReplyTemplate::render('Hi {first_name}! Welcome to {page_name}.', $vars));
    }

    public function test_missing_name_is_cleaned_up(): void
    {
        $vars = ReplyTemplate::variables(null, 'Acme');

        $this->assertSame('Hi! Welcome to Acme.', ReplyTemplate::render('Hi {first_name}! Welcome to {page_name}.', $vars));
        $this->assertSame('Hi, how can we help?', ReplyTemplate::render('Hi {first_name}, how can we help?', $vars));
        $this->assertSame("thanks!\nSee you", ReplyTemplate::render("{first_name}, thanks!\nSee   you", $vars));
        $this->assertSame('', ReplyTemplate::render('{name}', $vars));
    }

    public function test_text_without_gaps_is_kept_and_unknown_placeholders_untouched(): void
    {
        $vars = ReplyTemplate::variables('Ali', null);

        $this->assertSame('Price :  {unknown} Ali', ReplyTemplate::render('Price :  {unknown} {first_name}', $vars));
    }
}
