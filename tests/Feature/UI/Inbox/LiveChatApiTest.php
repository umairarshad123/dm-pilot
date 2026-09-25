<?php

namespace Tests\Feature\UI\Inbox;

use App\Enums\LeadStage;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Exceptions\MetaApiException;
use App\Jobs\FetchContactProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;
use Tests\Feature\Admin\AdminTestCase;

/** JSON endpoints used by the Live Chat page. */
class LiveChatApiTest extends AdminTestCase
{
    public function test_list_delta_mode_returns_only_changed_rows_with_match_flag(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');
        $quiet = Conversation::factory()->create(['customer_name' => 'Quiet']);
        $busy = Conversation::factory()->create(['customer_name' => 'Busy']);

        Carbon::setTestNow('2026-09-26 10:05:00');
        $cursor = now()->toIso8601String();

        Carbon::setTestNow('2026-09-26 10:06:00');
        Message::factory()->for($busy)->create(['body' => 'new one']);
        $busy->forceFill(['last_message_at' => now()])->save();

        $res = $this->getJson(route('admin.api.conversations.index', ['since' => $cursor, 'view' => 'unread']))->assertOk();
        $this->assertTrue($res->json('delta'));
        $this->assertSame(['Busy'], collect($res->json('data'))->pluck('name')->all());
        $this->assertTrue($res->json('data.0.matches'));
        $this->assertSame('new one', $res->json('data.0.excerpt'));
        $this->assertSame(1, $res->json('counts.unread'));
        $this->assertNotNull($res->json('server_time'));

        // Read → still reported (changed) but no longer matches the Unread tab.
        Carbon::setTestNow('2026-09-26 10:07:00');
        $cursor = now()->toIso8601String();
        Carbon::setTestNow('2026-09-26 10:08:00');
        $this->postJson(route('admin.api.conversations.read', $busy))->assertOk()->assertJsonPath('data.unread_count', 0);

        $res = $this->getJson(route('admin.api.conversations.index', ['since' => $cursor, 'view' => 'unread']))->assertOk();
        $this->assertSame([$busy->id], collect($res->json('data'))->pluck('id')->all());
        $this->assertFalse($res->json('data.0.matches'));
        $this->assertNotNull($quiet);

        Carbon::setTestNow();
    }

    public function test_list_delta_picks_up_message_status_changes(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');
        $c = Conversation::factory()->create();
        $m = Message::factory()->for($c)->fromHuman()->create(['status' => MessageStatus::Pending]);
        $cursor = now()->addMinute()->toIso8601String();

        Carbon::setTestNow('2026-09-26 10:05:00');
        $m->forceFill(['status' => MessageStatus::Failed, 'error' => 'nope'])->saveQuietly();

        $res = $this->getJson(route('admin.api.conversations.index', ['since' => $cursor]))->assertOk();
        $this->assertSame([$c->id], collect($res->json('data'))->pluck('id')->all());
        $this->assertTrue($res->json('data.0.last_failed'));

        Carbon::setTestNow();
    }

    public function test_list_paginates(): void
    {
        Conversation::factory()->count(5)->create();

        $this->getJson(route('admin.api.conversations.index', ['per_page' => 2, 'page' => 3]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_messages_after_id_since_and_before_id(): void
    {
        $c = Conversation::factory()->create();
        $messages = Message::factory()->for($c)->count(5)->create();
        $pending = Message::factory()->for($c)->fromBot()->create(['status' => MessageStatus::Pending, 'body' => 'thinking']);
        $url = route('admin.api.conversations.messages', $c);

        // Polling: only newer than after_id
        $this->getJson($url.'?after_id='.$messages[4]->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');

        // Status change on an already-seen message is returned with since=
        $this->travel(10)->seconds();
        $cursor = now()->toIso8601String();
        $this->travel(10)->seconds();
        $pending->forceFill(['status' => MessageStatus::Sent])->save();
        $this->getJson($url.'?after_id='.$pending->id.'&since='.urlencode($cursor))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $pending->id)->assertJsonPath('data.0.status', 'sent');

        // Older history
        $res = $this->getJson($url.'?before_id='.$messages[3]->id.'&limit=2')->assertOk();
        $this->assertSame([$messages[1]->id, $messages[2]->id], collect($res->json('data'))->pluck('id')->all());
        $this->assertTrue($res->json('has_more'));
        $this->assertFalse($this->getJson($url.'?before_id='.$messages[1]->id.'&limit=2')->json('has_more'));

        $this->getJson($url.'?after_id=abc')->assertUnprocessable();
    }

    public function test_message_payload_labels_sources_and_sanitizes_attachments(): void
    {
        $other = User::factory()->create(['name' => 'Omar Agent']);
        $c = Conversation::factory()->create();
        Message::factory()->for($c)->create(['body' => null, 'attachments' => [
            ['type' => 'image', 'url' => 'https://cdn.example.com/i.jpg', 'payload' => []],
            ['type' => 'file', 'url' => 'javascript:alert(1)', 'payload' => []],
            ['type' => 'audio', 'url' => null, 'payload' => ['url' => 'https://cdn.example.com/a.mp4']],
        ]]);
        Message::factory()->for($c)->fromBot()->create(['payload' => ['source' => 'automation']]);
        Message::factory()->for($c)->fromBot()->create(['payload' => ['source' => 'fallback']]);
        Message::factory()->for($c)->fromBot()->create(['payload' => null]);
        Message::factory()->for($c)->fromHuman()->create(['payload' => ['sent_by_user_id' => $this->admin->id]]);
        Message::factory()->for($c)->fromHuman()->create(['payload' => ['sent_by_user_id' => $other->id]]);
        Message::factory()->for($c)->fromHuman()->create(['payload' => null]);
        Message::factory()->for($c)->fromHuman()->failed()->create(['error' => '(#10) This message is sent outside of allowed window.']);

        $data = $this->getJson(route('admin.api.conversations.messages', $c))->assertOk()->json('data');

        $this->assertSame([null, 'Automation', 'Fallback', 'Bot', 'You', 'Omar', 'Page inbox', 'Page inbox'], array_column($data, 'sender_label'));
        $this->assertSame(['image', 'file', 'audio'], array_column($data[0]['attachments'], 'kind'));
        $this->assertSame('https://cdn.example.com/i.jpg', $data[0]['attachments'][0]['url']);
        $this->assertNull($data[0]['attachments'][1]['url'], 'javascript: URLs are dropped');
        $this->assertSame('https://cdn.example.com/a.mp4', $data[0]['attachments'][2]['url']);
        $this->assertStringContainsString('24 hours', $data[7]['error_hint']);
        $this->assertSame('failed', $data[7]['status']);
    }

    public function test_reply_success_returns_the_stored_message(): void
    {
        $c = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('sendHumanReply')
            ->once()
            ->withArgs(fn (Conversation $conv, string $text, $user) => $conv->is($c) && $text === 'Hi there!' && $user->is($this->admin))
            ->andReturnUsing(fn (Conversation $conv, string $text, $user) => $conv->messages()->create([
                'direction' => MessageDirection::Outgoing, 'sender_type' => SenderType::Human, 'status' => MessageStatus::Sent,
                'body' => $text, 'payload' => ['sent_by_user_id' => $user->id], 'sent_at' => now(),
            ])));

        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => 'Hi there!'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Hi there!')
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.sender_label', 'You')
            ->assertJsonPath('conversation.id', $c->id);
    }

    public function test_reply_failure_returns_admin_safe_error_and_the_failed_row(): void
    {
        $c = Conversation::factory()->create();

        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('sendHumanReply')
            ->once()
            ->andReturnUsing(function (Conversation $conv, string $text) {
                $conv->messages()->create([
                    'direction' => MessageDirection::Outgoing, 'sender_type' => SenderType::Human,
                    'status' => MessageStatus::Failed, 'body' => $text, 'error' => '(#10) outside window',
                ]);

                throw new MetaApiException('Message could not be sent: outside the 24-hour messaging window.', 10);
            }));

        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => 'Hello'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Message could not be sent: outside the 24-hour messaging window.')
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.body', 'Hello')
            ->assertJsonPath('conversation.id', $c->id);
    }

    public function test_reply_unexpected_error_is_generic(): void
    {
        $c = Conversation::factory()->create();
        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldReceive('sendHumanReply')->andThrow(new \RuntimeException('secret token EAA123 leaked')));

        $res = $this->postJson(route('admin.api.conversations.reply', $c), ['text' => 'Hello'])->assertUnprocessable();
        $this->assertSame('Could not reach Meta. Please try again.', $res->json('message'));
        $this->assertStringNotContainsString('EAA123', $res->getContent());
    }

    public function test_reply_validation(): void
    {
        $c = Conversation::factory()->create();
        $this->mock(ConversationService::class, fn (MockInterface $m) => $m->shouldNotReceive('sendHumanReply'));

        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => ''])->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => str_repeat('a', 4001)])->assertUnprocessable()->assertJsonValidationErrors('text');
    }

    public function test_reply_through_the_real_service_with_inactive_page_fails_cleanly(): void
    {
        $account = MetaAccount::factory()->create(['active' => false]);
        $c = Conversation::factory()->for($account, 'metaAccount')->create();

        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => 'Hello'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The Meta account for this conversation is missing or inactive.')
            ->assertJsonPath('conversation.account_active', false);

        $this->assertSame(0, $c->messages()->count());
    }

    public function test_toggles_return_full_conversation_state(): void
    {
        $c = Conversation::factory()->create(['bot_paused_until' => now()->addMinutes(42)]);

        $this->postJson(route('admin.api.conversations.bot', $c), ['enabled' => false])
            ->assertOk()->assertJsonPath('data.bot_enabled', false)->assertJsonPath('data.bot_can_reply', false);
        $this->postJson(route('admin.api.conversations.bot', $c), ['enabled' => true])->assertOk()->assertJsonPath('data.bot_enabled', true);

        $this->postJson(route('admin.api.conversations.takeover', $c), ['enabled' => true])
            ->assertOk()->assertJsonPath('data.human_takeover', true)->assertJsonPath('data.needs_human', true);

        $this->postJson(route('admin.api.conversations.takeover', $c), ['enabled' => false])
            ->assertOk()->assertJsonPath('data.human_takeover', false)->assertJsonPath('data.bot_paused_until', null);

        $c->forceFill(['bot_paused_until' => now()->addMinutes(10)])->save();
        $this->postJson(route('admin.api.conversations.clear-pause', $c))->assertOk()->assertJsonPath('data.bot_paused_until', null)->assertJsonPath('data.bot_can_reply', true);

        $this->postJson(route('admin.api.conversations.status', $c), ['status' => 'closed'])->assertOk()->assertJsonPath('data.status', 'closed');
        $this->postJson(route('admin.api.conversations.bot', $c), ['enabled' => 'maybe'])->assertUnprocessable();
    }

    public function test_contact_updates_are_validated_and_normalized(): void
    {
        $c = Conversation::factory()->create(['customer_name' => 'Old Name']);
        $url = route('admin.api.conversations.contact', $c);

        $this->patchJson($url, ['email' => 'Jane@Example.COM'])->assertOk()->assertJsonPath('data.email', 'jane@example.com');
        $this->patchJson($url, ['phone' => '+92 300 123-4567'])->assertOk()->assertJsonPath('data.phone', '+923001234567');
        $this->patchJson($url, ['customer_name' => 'Jane Doe'])->assertOk()->assertJsonPath('data.name', 'Jane Doe')->assertJsonPath('data.initials', 'JD');
        $this->patchJson($url, ['lead_stage' => 'customer'])->assertOk()->assertJsonPath('data.lead_stage', 'customer');
        $this->patchJson($url, ['notes' => 'Wants the blue one'])->assertOk()->assertJsonPath('data.notes', 'Wants the blue one');
        $this->patchJson($url, ['tags' => ['VIP', ' Wholesale ', 'vip']])
            ->assertOk()->assertJsonPath('data.tags', ['vip', 'wholesale'])->assertJsonPath('tags', ['vip', 'wholesale']);

        // Only the sent key changes
        $fresh = $c->fresh();
        $this->assertSame('jane@example.com', $fresh->email);
        $this->assertSame(LeadStage::Customer, $fresh->lead_stage);

        $this->patchJson($url, ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson($url, ['phone' => 'call me'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->patchJson($url, ['lead_stage' => 'bogus'])->assertUnprocessable()->assertJsonValidationErrors('lead_stage');
        $this->assertSame('jane@example.com', $c->fresh()->email);

        // Clearing
        $this->patchJson($url, ['email' => ''])->assertOk()->assertJsonPath('data.email', null);
        $this->patchJson($url, ['tags' => []])->assertOk()->assertJsonPath('data.tags', []);

        // Unknown / protected fields are ignored
        $this->patchJson($url, ['status' => 'closed', 'bot_enabled' => false])->assertOk();
        $this->assertSame('open', $c->fresh()->status);
        $this->assertTrue($c->fresh()->bot_enabled);
    }

    public function test_refresh_profile_queues_a_fetch(): void
    {
        Bus::fake();
        $c = Conversation::factory()->create();

        $this->postJson(route('admin.api.conversations.refresh-profile', $c))->assertOk()->assertJsonStructure(['message']);

        Bus::assertDispatched(FetchContactProfile::class, fn (FetchContactProfile $job) => $job->conversationId === $c->id && $job->force);
    }

    public function test_show_endpoint_returns_detail(): void
    {
        $ig = MetaAccount::factory()->instagram()->create(['page_name' => 'Shop IG']);
        $c = Conversation::factory()->for($ig, 'metaAccount')->create(['last_customer_message_at' => now()->subHours(30)]);

        $this->getJson(route('admin.api.conversations.show', $c))
            ->assertOk()
            ->assertJsonPath('data.platform', 'instagram')
            ->assertJsonPath('data.page_name', 'Shop IG')
            ->assertJsonPath('data.char_limit', 1000)
            ->assertJsonPath('data.char_unit', 'bytes')
            ->assertJsonPath('data.within_window', false)
            ->assertJsonPath('data.account_active', true);
    }

    public function test_api_requires_admin(): void
    {
        $c = Conversation::factory()->create();
        auth()->logout();

        $this->getJson(route('admin.api.conversations.index'))->assertUnauthorized();
        $this->postJson(route('admin.api.conversations.reply', $c), ['text' => 'x'])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->getJson(route('admin.api.conversations.messages', $c))->assertForbidden();
        $this->patchJson(route('admin.api.conversations.contact', $c), ['email' => 'a@b.co'])->assertForbidden();
        $this->postJson(route('admin.api.conversations.read', $c))->assertForbidden();
        $this->assertNull($c->fresh()->email);
    }
}
