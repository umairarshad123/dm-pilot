<?php

namespace Tests\Feature\Contacts;

use App\Enums\LeadStage;
use App\Jobs\ProcessIncomingMetaMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Pipeline\PipelineHelpers;
use Tests\TestCase;

class ContactObserversTest extends TestCase
{
    use PipelineHelpers, RefreshDatabase;

    public function test_new_conversation_starts_as_new_lead(): void
    {
        $conversation = Conversation::factory()->create();

        $this->assertSame(LeadStage::New, $conversation->fresh()->lead_stage);
        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_incoming_customer_messages_increment_unread_and_mark_read_resets(): void
    {
        $conversation = Conversation::factory()->create();

        Message::factory()->for($conversation)->count(3)->create();
        Message::factory()->for($conversation)->fromBot()->create();

        $this->assertSame(3, $conversation->fresh()->unread_count);

        $conversation->fresh()->markRead();
        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_human_reply_resets_unread(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->count(2)->create();

        Message::factory()->for($conversation)->fromHuman()->create();

        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_email_and_phone_are_captured_and_lead_is_qualified(): void
    {
        $conversation = Conversation::factory()->create();

        $message = Message::factory()->for($conversation)->create(['body' => 'Hi! mail me: Sara@Example.com or call +92 300 1234567']);

        $conversation->refresh();
        $this->assertSame('sara@example.com', $conversation->email);
        $this->assertSame('+923001234567', $conversation->phone);
        $this->assertSame(LeadStage::Qualified, $conversation->lead_stage);
        $this->assertNotNull($conversation->lead_captured_at);
        $this->assertSame([
            ['type' => 'email', 'value' => 'sara@example.com', 'message_id' => $message->id],
            ['type' => 'phone', 'value' => '+923001234567', 'message_id' => $message->id],
        ], array_map(fn ($e) => array_diff_key($e, ['at' => 1]), $conversation->meta['captured']));
    }

    public function test_capture_never_overwrites_manual_values_or_later_stages(): void
    {
        $conversation = Conversation::factory()->create([
            'email' => 'manual@shop.com', 'lead_stage' => LeadStage::Customer, 'meta' => ['keep' => 'me'],
        ]);

        Message::factory()->for($conversation)->create(['body' => 'new address: other@shop.com']);
        Message::factory()->for($conversation)->create(['body' => 'again other@shop.com']);

        $conversation->refresh();
        $this->assertSame('manual@shop.com', $conversation->email);
        $this->assertSame(LeadStage::Customer, $conversation->lead_stage);
        $this->assertSame('me', $conversation->meta['keep']);
        $this->assertCount(1, $conversation->meta['captured']); // deduplicated history
        $this->assertSame('other@shop.com', $conversation->meta['captured'][0]['value']);
    }

    public function test_bot_and_human_messages_are_not_scanned(): void
    {
        $conversation = Conversation::factory()->create();

        Message::factory()->for($conversation)->fromBot()->create(['body' => 'Email us at team@shop.com']);
        Message::factory()->for($conversation)->fromHuman()->create(['body' => 'Call +44 20 7946 0958']);

        $conversation->refresh();
        $this->assertNull($conversation->email);
        $this->assertNull($conversation->phone);
        $this->assertSame(LeadStage::New, $conversation->lead_stage);
    }

    public function test_pipeline_captures_lead_and_still_replies(): void
    {
        $this->setUpPipeline();
        $this->fakeApis('Thanks, we will email you.');
        $account = MetaAccount::factory()->create();

        ProcessIncomingMetaMessage::dispatch($this->incoming($account, ['text' => 'my email is buyer@mail.com']));

        $conversation = Conversation::sole();
        $this->assertSame('buyer@mail.com', $conversation->email);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertNotNull($conversation->last_customer_message_at);
        $this->assertSame(2, Message::count());
        $this->assertCount(1, $this->graphSends());
        $this->assertCount(0, array_filter($this->logs, fn ($l) => $l['level'] === 'warning'));
    }
}
