<?php

namespace Tests\Feature\UI\Dashboard;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\CurrentPage;
use Tests\Feature\Admin\AdminTestCase;

class DashboardPageTest extends AdminTestCase
{
    private MetaAccount $fb;

    private MetaAccount $ig;

    /** Two pages, a few conversations and a realistic mix of messages over the last days. */
    private function seedActivity(): void
    {
        $this->fb = MetaAccount::factory()->create(['page_name' => 'Acme Store']);
        $this->ig = MetaAccount::factory()->instagram()->create(['page_name' => 'Acme Insta']);

        $alice = Conversation::factory()->for($this->fb, 'metaAccount')->create(['customer_name' => 'Alice Smith', 'lead_captured_at' => now()->subDay()]);
        $bob = Conversation::factory()->for($this->ig, 'metaAccount')->create(['customer_name' => 'Bob Jones', 'human_takeover' => true]);

        $in = Message::factory()->for($alice)->create(['body' => 'Do you ship internationally?', 'created_at' => now()->subDays(2), 'sent_at' => now()->subDays(2)]);
        Message::factory()->for($alice)->fromBot()->create(['body' => 'Yes, worldwide!', 'in_reply_to_id' => $in->id, 'created_at' => now()->subDays(2), 'sent_at' => now()->subDays(2)->addSeconds(4)]);
        Message::factory()->for($alice)->fromBot()->create(['body' => 'Welcome!', 'payload' => ['source' => 'automation']]);
        Message::factory()->for($bob)->create(['body' => 'I want to talk to a person']);
        Message::factory()->for($bob)->fromHuman()->create(['body' => 'Hi Bob, Sam here']);
        Message::factory()->for($bob)->fromBot()->failed()->create(['body' => 'AI broke']);
    }

    public function test_guest_is_redirected_and_non_admin_is_forbidden(): void
    {
        auth()->logout();
        $this->get(route('admin.dashboard'))->assertRedirect('/login');
        $this->getJson(route('admin.dashboard.data'))->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->getJson(route('admin.dashboard.data'))->assertForbidden();
    }

    public function test_fresh_install_shows_onboarding_and_empty_states(): void
    {
        config(['meta.app_id' => null, 'meta.app_secret' => null, 'meta.verify_token' => 'tok-123']);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Put your DMs on autopilot')
            ->assertSee('Connect your first page')
            ->assertSee('Finish setting up your bot')
            ->assertSee('No messages in this period')
            ->assertSee('No messages yet')
            ->assertSee('Not enough data yet')
            ->assertSee('need attention')
            ->assertSee(url('/webhooks/meta'))
            ->assertSee('META_APP_SECRET')
            ->assertSee('Missing')
            ->assertViewHas('hasPages', false)
            ->assertViewHas('checklist', fn (array $c) => ! $c['complete'] && $c['done'] < $c['total']);
    }

    public function test_dashboard_with_data_shows_kpis_charts_feed_and_leaderboard(): void
    {
        $this->seedActivity();

        $response = $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Good evening', false)
            ->assertSee('Busiest hours')
            ->assertSee('Recent activity')
            ->assertSee('Alice Smith')
            ->assertSee('Do you ship internationally?')
            ->assertSee('Acme Store')
            ->assertSee('Acme Insta')
            ->assertSee(route('admin.conversations.show', Conversation::where('customer_name', 'Alice Smith')->first()), false)
            ->assertSee('Review failed sends')
            ->assertSee('1 waiting for a human')
            ->assertDontSee('Put your DMs on autopilot');

        $kpis = collect($response->viewData('kpis'))->keyBy('key');
        $this->assertSame(2, $kpis['conversations']['value']);
        $this->assertSame(2, $kpis['incoming']['value']);
        $this->assertSame(2, $kpis['bot_replies']['value']);
        $this->assertSame(1, $kpis['human_replies']['value']);
        $this->assertSame(1, $kpis['leads']['value']);
        $this->assertSame(1, $kpis['ai_failures']['value']);
        $this->assertSame('4s', $kpis['first_response']['display']);
        $this->assertSame(['AI' => 1, 'Automation' => 1, 'Fallback' => 0], collect($kpis['bot_replies']['sub'])->pluck('value', 'label')->all());
        $this->assertNotNull($kpis['ai_failures']['href']);
        $this->assertCount(14, $response->viewData('insights')['daily']);
    }

    public function test_period_selector_and_page_filter(): void
    {
        $this->seedActivity();
        Message::factory()->for(Conversation::where('customer_name', 'Alice Smith')->first())
            ->create(['body' => 'Old message from last month', 'created_at' => now()->subDays(20)]);

        $this->get(route('admin.dashboard', ['period' => 30]))->assertOk()
            ->assertViewHas('days', 30)
            ->assertViewHas('insights', fn ($i) => count($i['daily']) === 30)
            ->assertSee('aria-current="page"', false);

        $this->get(route('admin.dashboard', ['period' => 999]))->assertOk()->assertViewHas('days', DashboardController::DEFAULT_PERIOD);

        $this->withSession([CurrentPage::SESSION_KEY => $this->ig->id]);

        $response = $this->get(route('admin.dashboard', ['period' => 7]))->assertOk()
            ->assertViewHas('selectedPageId', $this->ig->id)
            ->assertSee('Bob Jones')
            ->assertSee('Viewing');

        $kpis = collect($response->viewData('kpis'))->keyBy('key');
        $this->assertSame(1, $kpis['conversations']['value']);
        $this->assertSame(1, $kpis['incoming']['value']);
        // The feed is scoped to the selected page, the leaderboard still lists every page (as a switcher).
        $this->assertTrue(collect($response->viewData('insights')['recent'])->every(fn ($r) => $r['meta_account_id'] === $this->ig->id));
        $this->assertCount(2, $response->viewData('leaderboard'));
    }

    public function test_json_endpoint_returns_analytics_and_live_html(): void
    {
        $this->seedActivity();

        $this->getJson(route('admin.dashboard.data', ['period' => 7]))
            ->assertOk()
            ->assertJsonPath('period', 7)
            ->assertJsonPath('page.label', 'All pages')
            ->assertJsonPath('overview.metrics.incoming_messages.value', 2)
            ->assertJsonCount(7, 'daily')
            ->assertJsonCount(8, 'kpis')
            ->assertJsonPath('stats.accounts', 2)
            ->assertJsonStructure(['html', 'hash', 'heatmap' => ['matrix'], 'by_channel' => ['facebook', 'instagram'], 'recent' => [['contact_name', 'excerpt', 'at']]])
            ->assertJsonMissingPath('recent.0.access_token');

        $json = $this->getJson(route('admin.dashboard.data'))->json();
        $this->assertStringContainsString('Busiest hours', $json['html']);
        $this->assertSame(md5($json['html']), $json['hash']);
    }

    public function test_output_is_escaped(): void
    {
        $account = MetaAccount::factory()->create(['page_name' => '<b>Evil</b> Page']);
        $c = Conversation::factory()->for($account, 'metaAccount')->create(['customer_name' => '<script>alert(1)</script>']);
        Message::factory()->for($c)->create(['body' => '<img src=x onerror=alert(2)>']);

        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringNotContainsString('<b>Evil</b>', $html);
    }

    public function test_health_checks_and_checklist_detection(): void
    {
        config(['meta.app_id' => '123', 'meta.app_secret' => 'secret', 'meta.verify_token' => 'tok', 'meta.verify_signature' => true, 'queue.default' => 'database', 'openai.api_key' => 'sk-test', 'ai.provider' => 'openai']);
        $this->seedActivity();
        WebhookEvent::create(['object' => 'page', 'payload_hash' => str_repeat('c', 64), 'payload' => ['x' => 1], 'status' => 'processed']);

        $response = $this->get(route('admin.dashboard'))->assertOk();
        $health = collect($response->viewData('health'))->keyBy('key');

        $this->assertTrue($health['app_id']['ok']);
        $this->assertTrue($health['queue']['ok']);
        $this->assertTrue($health['account']['ok']);
        $this->assertTrue($health['webhooks_failed']['ok']);

        $steps = collect($response->viewData('checklist')['steps'])->pluck('done', 'key');
        $this->assertTrue($steps['credentials']);
        $this->assertTrue($steps['page']);
        $this->assertTrue($steps['webhook']);
        $this->assertTrue($steps['reply']);
        $this->assertFalse($steps['bot']);

        // A failed webhook event in the last day flags processing.
        WebhookEvent::create(['object' => 'page', 'payload_hash' => str_repeat('d', 64), 'payload' => ['x' => 2], 'status' => 'failed']);
        $health = collect($this->get(route('admin.dashboard'))->viewData('health'))->keyBy('key');
        $this->assertFalse($health['webhooks_failed']['ok']);
    }

    public function test_duration_and_compact_formatting(): void
    {
        $this->assertSame('4s', DashboardController::duration(4));
        $this->assertSame('1m 12s', DashboardController::duration(72));
        $this->assertSame('2m', DashboardController::duration(120));
        $this->assertSame('1h 5m', DashboardController::duration(3900));
        $this->assertSame('—', DashboardController::duration(null));
        $this->assertSame('1,284', DashboardController::compact(1284));
        $this->assertSame('129K', DashboardController::compact(129000));
        $this->assertSame('4.2M', DashboardController::compact(4200000));
    }
}
