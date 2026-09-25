<?php

namespace Tests\Unit\Pipeline;

use App\Enums\MessageStatus;
use App\Enums\Platform;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\Prompt\PromptContext;
use App\Services\AI\Prompt\PromptSection;
use App\Services\AI\PromptBuilder;
use App\Services\Bot\BotSettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructions_include_configured_sections_in_order(): void
    {
        BotSetting::create([
            'meta_account_id' => null,
            'system_prompt' => 'You are Acme assistant.',
            'business_info' => 'Acme sells bikes in Lahore.',
            'faqs' => [['question' => 'Do you deliver?', 'answer' => 'Yes, nationwide.']],
            'offers' => '10% off helmets.',
            'channel_instructions' => ['instagram' => 'Use a casual tone.'],
        ]);
        $settings = app(BotSettingsResolver::class)->forAccount(null);
        $builder = app(PromptBuilder::class);

        $ig = $builder->instructions(new PromptContext($settings, Platform::Instagram));

        $positions = array_map(fn ($needle) => strpos($ig, $needle), [
            'You are Acme assistant.',
            "BUSINESS INFORMATION:\nAcme sells bikes in Lahore.",
            "Q: Do you deliver?\nA: Yes, nationwide.",
            '10% off helmets.',
            'Use a casual tone.',
            'REPLY RULES:',
        ]);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertStringContainsString('no markdown', $ig);

        // Facebook channel instruction falls back to the config default.
        $fb = $builder->instructions(new PromptContext($settings, Platform::Facebook));
        $this->assertStringContainsString('Facebook Messenger', $fb);
        $this->assertStringNotContainsString('Use a casual tone.', $fb);
    }

    public function test_empty_sections_are_omitted_and_custom_sections_can_be_added(): void
    {
        $settings = app(BotSettingsResolver::class)->forAccount(null);
        $custom = new class implements PromptSection
        {
            public function render(PromptContext $context): ?string
            {
                return 'LEAD STAGE: new';
            }
        };

        $text = (new PromptBuilder([$custom]))->instructions(new PromptContext($settings, Platform::Facebook));
        $this->assertSame('LEAD STAGE: new', $text);

        $default = app(PromptBuilder::class)->instructions(new PromptContext($settings, Platform::Facebook));
        $this->assertStringNotContainsString('BUSINESS INFORMATION', $default);
        $this->assertStringNotContainsString('FREQUENTLY ASKED', $default);
    }

    public function test_history_maps_roles_and_skips_undelivered(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create(['body' => 'one']);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'two']);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'pending', 'status' => MessageStatus::Pending]);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'skipped', 'status' => MessageStatus::Skipped]);
        Message::factory()->for($conversation)->create([
            'body' => null, 'attachments' => [['type' => 'image', 'url' => 'x', 'payload' => []], ['type' => 'audio']],
        ]);
        $excluded = Message::factory()->for($conversation)->fromBot()->create(['body' => 'claim']);

        $history = app(PromptBuilder::class)->history($conversation, 10, [$excluded->id]);

        $this->assertSame([
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'two'],
            ['role' => 'user', 'content' => '[customer sent an image] [customer sent a voice message]'],
        ], $history);

        $limited = app(PromptBuilder::class)->history($conversation, 2);
        $this->assertSame(['[customer sent an image] [customer sent a voice message]', 'claim'], array_column($limited, 'content'));
    }
}
