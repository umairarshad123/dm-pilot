<?php

namespace Tests\Feature\UI\Pages;

use App\Enums\Platform;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\User;
use App\Services\Meta\MetaMessagingService;
use App\Support\CurrentPage;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Feature\Admin\AdminTestCase;

class ChannelsPageTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['meta.app_id' => '1122334455', 'meta.app_secret' => 'secret', 'meta.graph_version' => 'v26.0']);
    }

    public function test_cards_group_page_with_its_instagram_account(): void
    {
        $page = MetaAccount::factory()->create(['page_name' => 'Apex Growth', 'page_id' => '5550001']);
        $ig = MetaAccount::factory()->instagram()->create(['page_name' => 'apex.growth', 'page_id' => '5550001']);
        $lonely = MetaAccount::factory()->create(['page_name' => 'Lonely Page']);
        $conversation = Conversation::factory()->for($page, 'metaAccount')->create();
        Message::factory()->for($conversation)->create();

        $response = $this->get(route('admin.meta-accounts.index'))->assertOk();

        $response->assertSee('Apex Growth')->assertSee('@apex.growth')->assertSee('Linked Instagram')
            ->assertSee('Lonely Page')->assertSee('Instagram not linked')
            ->assertSee('Messages (7 days)')
            ->assertSee(route('admin.meta-accounts.open', $page))
            ->assertSee(route('admin.meta-accounts.test', $ig))
            ->assertSee('disconnect-'.$lonely->id)
            ->assertDontSee($page->access_token, false);

        $groups = $response->viewData('groups');
        $this->assertCount(2, $groups);
        $pair = collect($groups)->first(fn ($g) => $g['page']['account']->is($page));
        $this->assertTrue($pair['instagram'][0]['account']->is($ig));
        $this->assertSame(1, $pair['page']['messages_7d']);
        $this->assertNotNull($pair['page']['last_inbound_at']);
    }

    public function test_standalone_instagram_and_status_pills(): void
    {
        MetaAccount::factory()->instagram()->create(['page_name' => 'solo.ig', 'page_id' => null, 'auth_type' => MetaAccount::AUTH_INSTAGRAM_LOGIN]);
        MetaAccount::factory()->create(['page_name' => 'Paused Page', 'active' => false]);
        MetaAccount::factory()->create(['page_name' => 'Broken Page', 'settings' => ['token_error' => 'Error validating access token']]);
        MetaAccount::factory()->create(['page_name' => 'Unsub Page', 'settings' => ['webhook_subscribed' => false]]);

        $this->get(route('admin.meta-accounts.index'))->assertOk()
            ->assertSee('@solo.ig')->assertSee('Instagram Login')
            ->assertSee('Paused')->assertSee('Token problem')->assertSee('Error validating access token')
            ->assertSee('Not subscribed')
            ->assertViewHas('stats', fn ($s) => $s['channels'] === 4 && $s['active'] === 3 && $s['attention'] === 2);
    }

    public function test_empty_state(): void
    {
        $this->get(route('admin.meta-accounts.index'))->assertOk()
            ->assertSee('Connect your first channel')->assertSee('Continue with Facebook');
    }

    public function test_test_connection_stores_picture_and_subscription(): void
    {
        $account = MetaAccount::factory()->create(['page_id' => '777', 'settings' => ['token_error' => 'old']]);
        Http::fake([
            'graph.facebook.com/v26.0/777/subscribed_apps*' => Http::response(['data' => [['id' => '1122334455', 'subscribed_fields' => ['messages', 'message_echoes']]]]),
            'graph.facebook.com/v26.0/777?*' => Http::response(['picture' => ['data' => ['url' => 'https://scontent.example/p.jpg']]]),
        ]);
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('testConnection')->once()
            ->andReturn(['ok' => true, 'id' => '777', 'name' => 'Page', 'expires_at' => null, 'scopes' => []]));

        $this->post(route('admin.meta-accounts.test', $account))->assertSessionHas('success');

        $account->refresh();
        $this->assertNull($account->setting('token_error'));
        $this->assertSame('https://scontent.example/p.jpg', $account->setting('picture_url'));
        $this->assertTrue($account->setting('webhook_subscribed'));
        $this->assertSame(['messages', 'message_echoes'], $account->setting('webhook_fields'));
        Http::assertSent(fn ($r) => ! str_contains($r->url(), $account->access_token));
    }

    public function test_failed_test_marks_token_problem(): void
    {
        $account = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('testConnection')->once()
            ->andReturn(['ok' => false, 'error' => 'Session has expired']));

        $this->post(route('admin.meta-accounts.test', $account))->assertSessionHas('error');

        $this->assertSame('Session has expired', $account->fresh()->setting('token_error'));
        Http::assertNothingSent();
    }

    public function test_subscribe_records_state(): void
    {
        $account = MetaAccount::factory()->create();
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')->twice()
            ->andReturn(['ok' => false, 'error' => 'Permission denied'], ['ok' => true, 'fields' => ['messages']]));

        $this->post(route('admin.meta-accounts.subscribe', $account))->assertSessionHas('error');
        $this->assertFalse($account->fresh()->setting('webhook_subscribed'));
        $this->assertSame('Permission denied', $account->fresh()->setting('webhook_error'));

        $this->post(route('admin.meta-accounts.subscribe', $account))->assertSessionHas('success');
        $this->assertTrue($account->fresh()->setting('webhook_subscribed'));
        $this->assertNull($account->fresh()->setting('webhook_error'));
    }

    public function test_toggle_pause_and_disconnect_resets_page_context(): void
    {
        $account = MetaAccount::factory()->create(['page_name' => 'Acme']);

        $this->from(route('admin.meta-accounts.index'))->post(route('admin.meta-accounts.toggle', $account))
            ->assertRedirect(route('admin.meta-accounts.index'))->assertSessionHas('success', fn ($m) => str_contains($m, 'paused'));
        $this->assertFalse($account->fresh()->active);

        $this->withSession([CurrentPage::SESSION_KEY => $account->id])
            ->delete(route('admin.meta-accounts.destroy', $account))
            ->assertRedirect(route('admin.meta-accounts.index'));
        $this->assertModelMissing($account);
        $this->assertNull(session(CurrentPage::SESSION_KEY));
    }

    public function test_open_switches_page_context_and_redirects(): void
    {
        $account = MetaAccount::factory()->create();

        $this->post(route('admin.meta-accounts.open', $account), ['to' => 'chat'])->assertRedirect(route('admin.conversations.index'));
        $this->assertSame($account->id, session(CurrentPage::SESSION_KEY));

        $this->post(route('admin.meta-accounts.open', $account), ['to' => 'bot'])->assertRedirect(route('admin.bot-settings.edit'));
        $this->post(route('admin.meta-accounts.open', $account), ['to' => 'nope'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_edit_form_never_renders_token(): void
    {
        $account = MetaAccount::factory()->create(['page_name' => 'Edit Me']);

        $this->get(route('admin.meta-accounts.edit', $account))->assertOk()
            ->assertSee('Edit Me')->assertSee('Replace token')->assertSee($account->maskedToken())
            ->assertDontSee($account->access_token, false);

        $this->get(route('admin.meta-accounts.create'))->assertOk()->assertSee('Add a channel manually');
    }

    public function test_guests_and_non_admins_are_blocked(): void
    {
        $account = MetaAccount::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.meta-accounts.index'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.meta-accounts.open', $account))->assertForbidden();

        auth()->logout();
        $this->get(route('admin.meta-accounts.index'))->assertRedirect(route('login'));
        $this->post(route('admin.meta-accounts.toggle', $account))->assertRedirect(route('login'));
        $this->assertSame(Platform::Facebook, $account->fresh()->platform);
    }
}
