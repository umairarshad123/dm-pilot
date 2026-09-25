<?php

namespace Tests\Feature\UI\Settings;

use App\Http\Controllers\Admin\WebhookEventController;
use App\Models\MetaAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use Tests\Feature\Admin\AdminTestCase;

class WebhookEventsPageTest extends AdminTestCase
{
    private function event(array $attrs = []): WebhookEvent
    {
        static $n = 0;
        $n++;

        return WebhookEvent::create($attrs + [
            'object' => 'page', 'payload_hash' => hash('sha256', 'e'.$n.microtime()),
            'payload' => ['object' => 'page', 'entry' => [['id' => '555', 'messaging' => [['message' => ['text' => 'hi']]]]]],
            'status' => 'processed', 'messages_count' => 1,
        ]);
    }

    public function test_index_filters_by_status_and_object(): void
    {
        $this->event(['error' => 'Processed fine']);
        $this->event(['object' => 'instagram', 'status' => 'failed', 'error' => 'Unknown account boom']);

        $this->get(route('admin.webhook-events.index'))->assertOk()
            ->assertSee('Unknown account boom')->assertSee('Webhook events')
            ->assertViewHas('counts', fn ($c) => $c['failed'] === 1 && $c['processed'] === 1);

        $this->get(route('admin.webhook-events.index', ['status' => 'processed']))->assertOk()->assertDontSee('Unknown account boom');
        $this->get(route('admin.webhook-events.index', ['object' => 'instagram']))->assertOk()->assertSee('Unknown account boom')->assertDontSee('Processed fine');
        $this->get(route('admin.webhook-events.index', ['status' => 'bogus']))->assertRedirect();
    }

    public function test_empty_state(): void
    {
        $this->get(route('admin.webhook-events.index'))->assertOk()->assertSee('No webhook events');
    }

    public function test_show_highlights_json_and_links_channel(): void
    {
        $account = MetaAccount::factory()->create(['page_id' => '555', 'page_name' => 'Matched Page']);
        $event = $this->event(['payload' => ['object' => 'page', 'entry' => [['id' => '555', 'text' => '<script>alert(1)</script>', 'n' => 42, 'ok' => true]]]]);

        $this->get(route('admin.webhook-events.show', $event))->assertOk()
            ->assertSee('Event #'.$event->id)
            ->assertSee('Matched Page')->assertSee(route('admin.meta-accounts.edit', $account))
            ->assertSee('&quot;555&quot;', false)
            ->assertSee('<span class="text-amber-300">42</span>', false)
            ->assertSee('<span class="text-violet-300">true</span>', false)
            ->assertSee('Copy JSON')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_highlighter_escapes_everything(): void
    {
        $json = json_encode(['a"b' => 'x\\"<b>', 'n' => -1.5e3, 'z' => null], JSON_PRETTY_PRINT);
        $html = WebhookEventController::highlight($json);

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertSame(html_entity_decode(strip_tags($html), ENT_QUOTES), $json);
        $this->assertStringContainsString('text-sky-300', $html);
    }

    public function test_non_admin_blocked(): void
    {
        $event = $this->event();
        $this->actingAs(User::factory()->create())->get(route('admin.webhook-events.show', $event))->assertForbidden();
    }
}
