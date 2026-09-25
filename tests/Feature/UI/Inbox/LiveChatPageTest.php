<?php

namespace Tests\Feature\UI\Inbox;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Support\CurrentPage;
use Tests\Feature\Admin\AdminTestCase;

/** The Live Chat page (index + deep link) and its inline boot data. */
class LiveChatPageTest extends AdminTestCase
{
    /** @return array<string, mixed> the decoded <script id="live-chat-boot"> JSON */
    private function boot(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="live-chat-boot">(.*?)</script>#s', $html, $m), 'boot JSON missing');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_index_renders_three_pane_shell_with_boot_data(): void
    {
        $fb = MetaAccount::factory()->create(['page_name' => 'Acme Page']);
        $c = Conversation::factory()->for($fb, 'metaAccount')->create(['customer_name' => 'Alice Smith', 'profile_pic_url' => 'https://cdn.example.com/p.jpg']);
        Message::factory()->for($c)->create(['body' => 'Hello there']);

        $html = $this->get(route('admin.conversations.index'))
            ->assertOk()
            ->assertSee('data-live-chat', false)
            ->assertSee('x-data="liveChat"', false)
            ->assertSee('Pick a conversation')
            ->assertSee('Needs human')
            ->assertSee('Bot active')
            ->assertSee('/build/assets/inbox-', false)
            ->getContent();

        $boot = $this->boot($html);
        $this->assertNull($boot['selected']);
        $this->assertSame('all', $boot['filters']['view']);
        $this->assertSame('Alice Smith', $boot['list']['data'][0]['name']);
        $this->assertSame('Hello there', $boot['list']['data'][0]['excerpt']);
        $this->assertSame('customer', $boot['list']['data'][0]['last_sender']);
        $this->assertSame('Acme Page', $boot['list']['data'][0]['page_name']);
        $this->assertSame('https://cdn.example.com/p.jpg', $boot['list']['data'][0]['avatar']);
        $this->assertSame(1, $boot['list']['data'][0]['unread_count']);
        $this->assertSame(1, $boot['list']['counts']['unread']);
        $this->assertStringContainsString('/admin/api/conversations/__ID__/messages', $boot['routes']['messages']);
        $this->assertTrue($boot['has_pages']);
    }

    public function test_empty_inbox_shows_friendly_empty_state(): void
    {
        $html = $this->get(route('admin.conversations.index'))
            ->assertOk()
            ->assertSee('No conversations yet')
            ->assertSee('Send a DM to your Page to see it here.')
            ->getContent();

        $this->assertSame([], $this->boot($html)['list']['data']);
        $this->assertFalse($this->boot($html)['has_pages']);
    }

    public function test_tab_filters(): void
    {
        $unread = Conversation::factory()->create(['customer_name' => 'Unread Ursula', 'unread_count' => 2]);
        $takeover = Conversation::factory()->create(['customer_name' => 'Takeover Tom', 'human_takeover' => true]);
        $paused = Conversation::factory()->create(['customer_name' => 'Paused Pat', 'bot_paused_until' => now()->addHour()]);
        $failed = Conversation::factory()->create(['customer_name' => 'Failed Fay']);
        Message::factory()->for($failed)->fromHuman()->failed()->create();
        $closed = Conversation::factory()->create(['customer_name' => 'Closed Cleo', 'status' => 'closed']);
        $expired = Conversation::factory()->create(['customer_name' => 'Expired Eve', 'bot_paused_until' => now()->subHour()]);
        $botOff = Conversation::factory()->create(['customer_name' => 'Botoff Bea', 'bot_enabled' => false]);

        $names = fn (string $view) => collect($this->getJson(route('admin.api.conversations.index', ['view' => $view]))->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

        $this->assertSame(['Failed Fay', 'Paused Pat', 'Takeover Tom'], $names('human'));
        $this->assertSame(['Closed Cleo'], $names('closed'));
        $this->assertContains('Unread Ursula', $names('unread'));
        $this->assertNotContains('Takeover Tom', $names('unread'));
        $this->assertSame(['Expired Eve', 'Failed Fay', 'Unread Ursula'], $names('bot'));
        $this->assertCount(7, $names('all'));

        $counts = $this->getJson(route('admin.api.conversations.index'))->json('counts');
        $this->assertSame(['all' => 7, 'unread' => 1, 'human' => 3, 'bot' => 3, 'closed' => 1], $counts);

        $row = collect($this->getJson(route('admin.api.conversations.index'))->json('data'))->firstWhere('name', 'Failed Fay');
        $this->assertTrue($row['last_failed']);
        $this->assertTrue($row['needs_human']);
        $this->assertSame('human', $row['last_sender']);
        $this->assertNotNull($botOff);

        // Page view uses the same filter
        $boot = $this->boot($this->get(route('admin.conversations.index', ['view' => 'closed']))->assertOk()->getContent());
        $this->assertSame(['Closed Cleo'], collect($boot['list']['data'])->pluck('name')->all());
        $this->assertSame('closed', $boot['filters']['view']);

        $this->get(route('admin.conversations.index', ['view' => 'bogus']))->assertSessionHasErrors('view');
    }

    public function test_channel_and_search_filters(): void
    {
        $ig = MetaAccount::factory()->instagram()->create();
        Conversation::factory()->create(['customer_name' => 'Messenger Mia']);
        Conversation::factory()->for($ig, 'metaAccount')->create(['customer_name' => 'Insta Ian', 'username' => 'ian.shop', 'email' => 'ian@example.com']);

        $names = fn (array $q) => collect($this->getJson(route('admin.api.conversations.index', $q))->json('data'))->pluck('name')->all();

        $this->assertSame(['Insta Ian'], $names(['platform' => 'instagram']));
        $this->assertSame(['Messenger Mia'], $names(['platform' => 'facebook']));
        $this->assertSame(['Insta Ian'], $names(['q' => '@ian.shop']));
        $this->assertSame(['Insta Ian'], $names(['q' => 'ian@example']));
        $this->assertSame(['Messenger Mia'], $names(['q' => 'mia']));
    }

    public function test_list_respects_selected_page(): void
    {
        $a = MetaAccount::factory()->create(['page_name' => 'Page A']);
        $b = MetaAccount::factory()->create(['page_name' => 'Page B']);
        Conversation::factory()->for($a, 'metaAccount')->create(['customer_name' => 'From A']);
        Conversation::factory()->for($b, 'metaAccount')->create(['customer_name' => 'From B']);

        $this->withSession([CurrentPage::SESSION_KEY => $a->id]);

        $this->assertSame(['From A'], collect($this->getJson(route('admin.api.conversations.index'))->json('data'))->pluck('name')->all());
        $boot = $this->boot($this->get(route('admin.conversations.index'))->getContent());
        $this->assertSame(['From A'], collect($boot['list']['data'])->pluck('name')->all());
        $this->assertSame('Page A', $boot['page_label']);
    }

    public function test_deep_link_opens_conversation_and_marks_it_read(): void
    {
        $c = Conversation::factory()->create(['customer_name' => 'Dana', 'email' => 'dana@example.com', 'meta' => ['captured' => [
            ['type' => 'email', 'value' => 'dana@example.com', 'message_id' => 1, 'at' => now()->toIso8601String()],
        ]]]);
        Message::factory()->for($c)->create(['body' => 'Hi']);
        Message::factory()->for($c)->fromBot()->create(['body' => 'Hello from AI', 'payload' => ['source' => 'ai']]);
        $this->assertSame(1, $c->fresh()->unread_count);

        $boot = $this->boot($this->get(route('admin.conversations.show', $c))->assertOk()->getContent());

        $this->assertSame(0, $c->fresh()->unread_count);
        $this->assertSame($c->id, $boot['selected']['id']);
        $this->assertSame('Dana', $boot['selected']['name']);
        $this->assertSame('dana@example.com', $boot['selected']['captured'][0]['value']);
        $this->assertSame(2, $boot['selected']['messages_count']);
        $this->assertSame(2000, $boot['selected']['char_limit']);
        $this->assertSame(['Hi', 'Hello from AI'], array_column($boot['messages'], 'body'));
        $this->assertSame('AI', $boot['messages'][1]['sender_label']);
        $this->assertFalse($boot['has_older']);
    }

    public function test_deep_link_to_unknown_conversation_404s(): void
    {
        $this->get('/admin/conversations/999999')->assertNotFound();
    }

    public function test_message_bodies_and_names_are_never_rendered_as_html(): void
    {
        $c = Conversation::factory()->create(['customer_name' => '<img src=x onerror=alert(1)>']);
        Message::factory()->for($c)->create(['body' => '<script>alert("xss")</script>']);

        $html = $this->get(route('admin.conversations.show', $c))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $boot = $this->boot($html);
        $this->assertSame('<script>alert("xss")</script>', $boot['messages'][0]['body']);
        // Templates render customer text only through x-text (textContent), never x-html.
        $this->assertStringNotContainsString('x-html', $html);
    }

    public function test_page_requires_admin(): void
    {
        $c = Conversation::factory()->create();
        auth()->logout();

        $this->get(route('admin.conversations.index'))->assertRedirect('/login');
        $this->get(route('admin.conversations.show', $c))->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get(route('admin.conversations.index'))->assertForbidden();
    }

    public function test_long_threads_load_the_latest_page_first(): void
    {
        $c = Conversation::factory()->create();
        Message::factory()->for($c)->count(65)->create();
        $last = Message::factory()->for($c)->create([
            'direction' => MessageDirection::Outgoing, 'sender_type' => SenderType::Human, 'status' => MessageStatus::Sent, 'body' => 'latest',
        ]);

        $boot = $this->boot($this->get(route('admin.conversations.show', $c))->getContent());

        $this->assertCount(60, $boot['messages']);
        $this->assertTrue($boot['has_older']);
        $this->assertSame($last->id, end($boot['messages'])['id']);
    }
}
