<?php

namespace Tests\Feature\Admin;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;
use Mockery\MockInterface;
use RuntimeException;

class ConversationActionsTest extends AdminTestCase
{
    public function test_disable_bot_calls_service(): void
    {
        $conversation = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('setBotEnabled')
            ->once()
            ->withArgs(fn (Conversation $c, bool $enabled) => $c->is($conversation) && $enabled === false)
            ->andReturn($conversation));

        $this->from(route('admin.conversations.show', $conversation))
            ->post(route('admin.conversations.bot', $conversation), ['enabled' => '0'])
            ->assertRedirect(route('admin.conversations.show', $conversation))
            ->assertSessionHas('success');
    }

    public function test_takeover_calls_service(): void
    {
        $conversation = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('setHumanTakeover')
            ->once()
            ->withArgs(fn (Conversation $c, bool $enabled) => $c->is($conversation) && $enabled === true)
            ->andReturn($conversation));

        $this->post(route('admin.conversations.takeover', $conversation), ['enabled' => '1'])->assertSessionHas('success');
    }

    public function test_toggle_requires_boolean(): void
    {
        $conversation = Conversation::factory()->create();

        $this->post(route('admin.conversations.bot', $conversation), ['enabled' => 'maybe'])->assertSessionHasErrors('enabled');
    }

    public function test_clear_pause_and_status(): void
    {
        $conversation = Conversation::factory()->create(['bot_paused_until' => now()->addHour()]);

        $this->post(route('admin.conversations.clear-pause', $conversation))->assertSessionHas('success');
        $this->assertNull($conversation->fresh()->bot_paused_until);

        $this->post(route('admin.conversations.status', $conversation), ['status' => 'closed'])->assertSessionHas('success');
        $this->assertSame('closed', $conversation->fresh()->status);

        $this->post(route('admin.conversations.status', $conversation), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->assertSame('closed', $conversation->fresh()->status);
    }

    public function test_manual_reply_calls_service(): void
    {
        $conversation = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('sendHumanReply')
            ->once()
            ->withArgs(fn (Conversation $c, string $text, $user) => $c->is($conversation) && $text === 'Hi there!' && $user->is($this->admin))
            ->andReturn(new Message(['direction' => MessageDirection::Outgoing, 'sender_type' => SenderType::Human, 'status' => MessageStatus::Sent])));

        $this->from(route('admin.conversations.show', $conversation))
            ->post(route('admin.conversations.reply', $conversation), ['text' => 'Hi there!'])
            ->assertRedirect(route('admin.conversations.show', $conversation))
            ->assertSessionHas('success', 'Reply sent.');
    }

    public function test_manual_reply_failure_flashes_error(): void
    {
        $conversation = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('sendHumanReply')
            ->once()
            ->andThrow(new RuntimeException('(#10) Message sent outside allowed window')));

        $this->from(route('admin.conversations.show', $conversation))
            ->post(route('admin.conversations.reply', $conversation), ['text' => 'Hello'])
            ->assertRedirect(route('admin.conversations.show', $conversation))
            ->assertSessionHas('error', 'Reply failed: (#10) Message sent outside allowed window')
            ->assertSessionHasInput('text', 'Hello');

        $this->get(route('admin.conversations.show', $conversation))->assertSee('outside allowed window');
    }

    public function test_manual_reply_requires_text(): void
    {
        $conversation = Conversation::factory()->create();
        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldNotReceive('sendHumanReply'));

        $this->post(route('admin.conversations.reply', $conversation), ['text' => ''])->assertSessionHasErrors('text');
    }

    public function test_api_conversations_and_messages(): void
    {
        $conversation = Conversation::factory()->create(['customer_name' => 'Dana']);
        $first = Message::factory()->for($conversation)->create(['body' => 'first']);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'second']);

        $this->getJson('/admin/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.customer_name', 'Dana')
            ->assertJsonPath('data.0.last_message', 'second')
            ->assertJsonPath('meta.total', 1);

        $this->getJson(route('admin.api.conversations.messages', $conversation))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'first')
            ->assertJsonPath('data.1.sender_type', 'bot');

        $this->getJson(route('admin.api.conversations.messages', $conversation).'?after_id='.$first->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'second');
    }

    public function test_api_toggles(): void
    {
        $conversation = Conversation::factory()->create();

        $this->mock(ConversationService::class, function (MockInterface $m) {
            $m->shouldReceive('setBotEnabled')->once()->andReturnUsing(function (Conversation $c, bool $enabled) {
                $c->forceFill(['bot_enabled' => $enabled])->save();

                return $c;
            });
            $m->shouldReceive('setHumanTakeover')->once()->andReturnUsing(function (Conversation $c, bool $enabled) {
                $c->forceFill(['human_takeover' => $enabled])->save();

                return $c;
            });
        });

        $this->postJson(route('admin.api.conversations.bot', $conversation), ['enabled' => false])
            ->assertOk()->assertJsonPath('data.bot_enabled', false);
        $this->postJson(route('admin.api.conversations.takeover', $conversation), ['enabled' => true])
            ->assertOk()->assertJsonPath('data.human_takeover', true)->assertJsonPath('data.bot_can_reply', false);
        $this->postJson(route('admin.api.conversations.status', $conversation), ['status' => 'closed'])
            ->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson(route('admin.api.conversations.status', $conversation), ['status' => 'nope'])
            ->assertUnprocessable();
    }
}
