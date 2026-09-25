<?php

namespace Tests\Feature\Admin;

use App\Enums\Platform;
use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

class MetaAccountAdminTest extends AdminTestCase
{
    private const TOKEN = 'EAAGsecretPageToken1234567890abcdefXYZ';

    public function test_create_stores_encrypted_token_and_never_renders_it(): void
    {
        $this->post(route('admin.meta-accounts.store'), [
            'platform' => 'facebook',
            'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
            'page_id' => '1234567890',
            'page_name' => 'My Page',
            'access_token' => self::TOKEN,
            'active' => '1',
        ])->assertRedirect(route('admin.meta-accounts.index'))->assertSessionHas('success');

        $account = MetaAccount::sole();
        $this->assertSame(self::TOKEN, $account->access_token);
        $this->assertSame(Platform::Facebook, $account->platform);
        $this->assertTrue($account->active);

        $raw = DB::table('meta_accounts')->value('access_token');
        $this->assertNotSame(self::TOKEN, $raw);
        $this->assertStringNotContainsString(self::TOKEN, $raw);

        foreach ([route('admin.meta-accounts.index'), route('admin.meta-accounts.edit', $account), route('admin.bot-settings.edit'), '/admin'] as $url) {
            $this->get($url)->assertOk()->assertDontSee(self::TOKEN, false)->assertDontSee(substr(self::TOKEN, 4, 20), false);
        }
        $this->get(route('admin.meta-accounts.index'))->assertSee($account->maskedToken());
    }

    public function test_blank_token_on_edit_keeps_existing(): void
    {
        $account = MetaAccount::factory()->create(['access_token' => self::TOKEN, 'page_id' => '111']);

        $this->put(route('admin.meta-accounts.update', $account), [
            'platform' => 'facebook', 'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
            'page_id' => '111', 'page_name' => 'Renamed', 'access_token' => '', 'active' => '0',
        ])->assertRedirect(route('admin.meta-accounts.index'));

        $account->refresh();
        $this->assertSame(self::TOKEN, $account->access_token);
        $this->assertSame('Renamed', $account->page_name);
        $this->assertFalse($account->active);
    }

    public function test_new_token_on_edit_replaces_it(): void
    {
        $account = MetaAccount::factory()->create(['access_token' => self::TOKEN]);

        $this->put(route('admin.meta-accounts.update', $account), [
            'platform' => 'facebook', 'auth_type' => MetaAccount::AUTH_FACEBOOK_LOGIN,
            'page_id' => $account->page_id, 'access_token' => 'EAAGbrandNewToken0987654321', 'active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame('EAAGbrandNewToken0987654321', $account->fresh()->access_token);
    }

    public function test_validation(): void
    {
        MetaAccount::factory()->create(['page_id' => '555']);

        $this->post(route('admin.meta-accounts.store'), ['platform' => 'facebook', 'auth_type' => 'facebook_login', 'active' => '1'])
            ->assertSessionHasErrors(['page_id', 'access_token']);
        $this->post(route('admin.meta-accounts.store'), ['platform' => 'instagram', 'auth_type' => 'facebook_login', 'active' => '1', 'access_token' => self::TOKEN])
            ->assertSessionHasErrors(['instagram_account_id']);
        $this->post(route('admin.meta-accounts.store'), ['platform' => 'facebook', 'auth_type' => 'facebook_login', 'page_id' => '555', 'active' => '1', 'access_token' => self::TOKEN])
            ->assertSessionHasErrors(['page_id']);
        $this->post(route('admin.meta-accounts.store'), ['platform' => 'twitter', 'auth_type' => 'x', 'active' => '1'])
            ->assertSessionHasErrors(['platform', 'auth_type']);

        $this->assertSame(1, MetaAccount::count());
    }

    public function test_old_input_never_contains_token_after_validation_error(): void
    {
        $this->from(route('admin.meta-accounts.create'))->post(route('admin.meta-accounts.store'), [
            'platform' => 'facebook', 'auth_type' => 'facebook_login', 'active' => '1', 'access_token' => self::TOKEN,
        ])->assertSessionHasErrors('page_id')->assertSessionHasInput('platform', 'facebook');

        $this->assertFalse(session()->hasOldInput('access_token'));
        $this->get(route('admin.meta-accounts.create'))->assertDontSee(self::TOKEN, false);
    }

    public function test_toggle_and_delete(): void
    {
        $account = MetaAccount::factory()->create(['active' => true]);

        $this->post(route('admin.meta-accounts.toggle', $account))->assertSessionHas('success');
        $this->assertFalse($account->fresh()->active);

        $this->delete(route('admin.meta-accounts.destroy', $account))->assertRedirect(route('admin.meta-accounts.index'));
        $this->assertModelMissing($account);
    }

    public function test_test_connection_success_and_failure(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => []])]);
        $account = MetaAccount::factory()->create();

        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('testConnection')
            ->twice()
            ->andReturn(
                ['ok' => true, 'id' => $account->page_id, 'name' => 'Acme Page', 'expires_at' => null, 'scopes' => ['pages_messaging']],
                ['ok' => false, 'error' => 'Invalid OAuth access token.'],
            ));

        $this->post(route('admin.meta-accounts.test', $account))->assertSessionHas('success', fn (string $m) => str_contains($m, 'Acme Page') && str_contains($m, 'pages_messaging'));
        $this->assertNotNull($account->fresh()->token_checked_at);

        $this->post(route('admin.meta-accounts.test', $account))->assertSessionHas('error', 'Connection failed: Invalid OAuth access token.');
    }

    public function test_subscribe_webhooks(): void
    {
        $account = MetaAccount::factory()->create();

        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')
            ->twice()
            ->andReturn(['ok' => true, 'fields' => ['messages', 'messaging_postbacks']], ['ok' => false, 'error' => 'Permission denied']));

        $this->post(route('admin.meta-accounts.subscribe', $account))->assertSessionHas('success', 'Webhooks subscribed: messages, messaging_postbacks');
        $this->post(route('admin.meta-accounts.subscribe', $account))->assertSessionHas('error', 'Subscribe failed: Permission denied');
    }
}
