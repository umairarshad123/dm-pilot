<?php

namespace Tests\Unit\Contacts;

use App\Enums\LeadStage;
use App\Enums\Platform;
use App\Models\Conversation;
use PHPUnit\Framework\TestCase;

class ContactHelpersTest extends TestCase
{
    private function contact(array $attributes): Conversation
    {
        return (new Conversation)->forceFill($attributes + ['platform' => Platform::Facebook, 'external_user_id' => '123456789']);
    }

    public function test_display_name_fallback_chain(): void
    {
        $this->assertSame('Jane Doe', $this->contact(['customer_name' => 'Jane Doe', 'first_name' => 'X'])->displayName());
        $this->assertSame('Ali Khan', $this->contact(['first_name' => 'Ali', 'last_name' => 'Khan'])->displayName());
        $this->assertSame('@shop.pk', $this->contact(['username' => 'shop.pk'])->displayName());
        $this->assertSame('Facebook user …6789', $this->contact([])->displayName());
        $this->assertSame('Instagram user …6789', $this->contact(['platform' => Platform::Instagram])->displayName());
    }

    public function test_initials(): void
    {
        $this->assertSame('JD', $this->contact(['customer_name' => 'jane doe smith'])->initials());
        $this->assertSame('SP', $this->contact(['username' => 'shop.pk'])->initials());
        $this->assertSame('Ä', $this->contact(['first_name' => 'ägnes'])->initials());
        $this->assertSame('?', $this->contact([])->initials());
    }

    public function test_tag_normalization(): void
    {
        $this->assertSame(['vip', 'big spender'], Conversation::normalizeTags(['  VIP ', 'vip', 'Big   Spender', '', ' ']));
        $this->assertSame(50, mb_strlen(Conversation::normalizeTag(str_repeat('a', 80))));
    }

    public function test_lead_stage_defaults_and_labels(): void
    {
        $this->assertSame(LeadStage::New, $this->contact([])->leadStage());
        $this->assertSame('Qualified', LeadStage::Qualified->label());
        $this->assertSame('green', LeadStage::Customer->color());
        $this->assertSame(['new', 'contacted', 'qualified', 'customer', 'lost'], array_keys(LeadStage::options()));
    }
}
