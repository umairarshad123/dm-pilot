<?php

namespace Tests\Feature\Admin;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;

class AdminPagesTest extends AdminTestCase
{
    public function test_dashboard_shows_counts_and_config_health(): void
    {
        config(['meta.verify_token' => 'my-verify-token-123', 'meta.app_secret' => null, 'openai.api_key' => 'sk-test']);
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create();
        Message::factory()->for($conversation)->fromBot()->create(['status' => MessageStatus::Failed, 'error' => 'boom']);
        WebhookEvent::create(['object' => 'page', 'payload_hash' => str_repeat('a', 64), 'payload' => ['x' => 1], 'status' => 'failed']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee(url('/webhooks/meta'))
            ->assertSee('META_APP_SECRET')
            ->assertSee('AI failures')
            ->assertSee('Missing')
            ->assertSee('Webhook processing')
            ->assertViewHas('stats', fn (array $s) => $s['accounts'] === 1
                && $s['open_conversations'] === 1
                && $s['failed_outgoing_24h'] === 1
                && $s['webhooks_failed'] === 1)
            ->assertViewHas('kpis', fn (array $k) => collect($k)->firstWhere('key', 'incoming')['value'] === 1
                && collect($k)->firstWhere('key', 'ai_failures')['value'] === 1);
    }

    public function test_conversations_index_lists_and_filters(): void
    {
        $fb = MetaAccount::factory()->create(['page_name' => 'FB Page']);
        $ig = MetaAccount::factory()->instagram()->create(['page_name' => 'IG Account']);
        $alice = Conversation::factory()->for($fb, 'metaAccount')->create(['customer_name' => 'Alice Smith']);
        $bob = Conversation::factory()->for($ig, 'metaAccount')->create(['customer_name' => 'Bob Jones', 'human_takeover' => true]);
        Message::factory()->for($alice)->create(['body' => 'Hello from Alice']);

        $this->get('/admin/conversations')->assertOk()->assertSee('Alice Smith')->assertSee('Bob Jones')->assertSee('Hello from Alice');
        $this->get('/admin/conversations?platform=instagram')->assertOk()->assertDontSee('Alice Smith')->assertSee('Bob Jones');
        $this->get('/admin/conversations?q=alice')->assertOk()->assertSee('Alice Smith')->assertDontSee('Bob Jones');
        $this->get('/admin/conversations?q='.$bob->external_user_id)->assertOk()->assertSee('Bob Jones')->assertDontSee('Alice Smith');
        $this->get('/admin/conversations?takeover=1')->assertOk()->assertSee('Bob Jones')->assertDontSee('Alice Smith');
        $this->get('/admin/conversations?account='.$fb->id)->assertOk()->assertSee('Alice Smith')->assertDontSee('Bob Jones');
    }

    public function test_conversation_detail_shows_thread(): void
    {
        $conversation = Conversation::factory()->create(['customer_name' => 'Carol', 'bot_paused_until' => now()->addMinutes(30)]);
        Message::factory()->for($conversation)->create(['body' => 'Do you deliver?', 'attachments' => [['type' => 'image', 'url' => 'https://cdn.example.com/a.jpg']]]);
        Message::factory()->for($conversation)->fromBot()->create(['body' => 'Yes we do!']);
        Message::factory()->for($conversation)->create([
            'direction' => MessageDirection::Outgoing, 'sender_type' => SenderType::Human,
            'status' => MessageStatus::Failed, 'error' => 'Outside messaging window', 'body' => '<script>alert(1)</script>',
        ]);

        // The Live Chat page boots from inline JSON (rendered client-side with x-text).
        $this->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Carol')
            ->assertSee('Do you deliver?')
            ->assertSee('Yes we do!')
            ->assertSee('https:\/\/cdn.example.com\/a.jpg', false)
            ->assertSee('Outside messaging window')
            ->assertSee('Bot paused')
            ->assertSee('Clear pause')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('\u003Cscript\u003E', false);
    }

    public function test_webhook_events_list_and_detail(): void
    {
        $event = WebhookEvent::create([
            'object' => 'instagram', 'payload_hash' => str_repeat('b', 64),
            'payload' => ['object' => 'instagram', 'entry' => [['id' => '178414000']]],
            'status' => 'failed', 'messages_count' => 2, 'error' => 'Unknown account',
        ]);

        $this->get('/admin/webhook-events')->assertOk()->assertSee('Unknown account')->assertSee('instagram');
        $this->get('/admin/webhook-events?status=processed')->assertOk()->assertDontSee('Unknown account');
        $this->get(route('admin.webhook-events.show', $event))->assertOk()->assertSee('&quot;178414000&quot;', false);
    }

    public function test_meta_account_pages_render(): void
    {
        $account = MetaAccount::factory()->create();

        $this->get('/admin/meta-accounts')->assertOk()->assertSee($account->page_name);
        $this->get('/admin/meta-accounts/create')->assertOk();
        $this->get(route('admin.meta-accounts.edit', $account))->assertOk();
        $this->get('/admin/meta-accounts/connect')->assertOk()->assertSee('pages_messaging');
    }

    public function test_bot_settings_pages_render(): void
    {
        $account = MetaAccount::factory()->create();

        $this->get('/admin/bot-settings')->assertOk()->assertSee(config('openai.model'))->assertSee($account->page_name);
        $this->followingRedirects()->get(route('admin.bot-settings.account.edit', $account))->assertOk()->assertSee('Inherited from global');
    }

    public function test_automations_page_renders(): void
    {
        AutomationRule::factory()->create(['name' => 'Pricing rule']);

        $this->get(route('admin.automations.index'))->assertOk()->assertSee('Keyword rules')->assertSee('Pricing rule')->assertSee('Test a message');
    }
}
