<?php

namespace Tests\Feature\Admin;

use App\Enums\Platform;
use App\Models\MetaAccount;
use App\Models\User;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

class MetaConnectTest extends AdminTestCase
{
    private const SHORT = 'EAAshortLivedUserToken1234567890';

    private const LONG = 'EAAlongLivedUserToken0987654321xyz';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta.app_id' => '1122334455',
            'meta.app_secret' => 'app-secret-value',
            'meta.graph_url' => 'https://graph.facebook.com',
            'meta.graph_version' => 'v26.0',
        ]);

        Http::fake([
            'graph.facebook.com/v26.0/oauth/access_token*' => Http::response(['access_token' => self::LONG, 'token_type' => 'bearer', 'expires_in' => 5183944]),
            'graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => [
                'is_valid' => true, 'expires_at' => 0, 'type' => 'USER',
                'scopes' => ['pages_show_list', 'pages_messaging', 'pages_manage_metadata', 'pages_read_engagement', 'instagram_basic', 'instagram_manage_messages', 'business_management'],
            ]]),
            'graph.facebook.com/v26.0/me/accounts*' => Http::response(['data' => [
                ['id' => '111', 'name' => 'Page One', 'access_token' => 'EAApageTokenOne1234567890abc', 'instagram_business_account' => ['id' => '17841400000000001', 'username' => 'page.one']],
                ['id' => '222', 'name' => 'Page Two', 'access_token' => 'EAApageTokenTwo1234567890abc'],
            ]]),
            'graph.facebook.com/v26.0/me?*' => Http::response(['id' => '9988776655', 'name' => 'Owner Person']),
        ]);
    }

    public function test_command_upserts_facebook_and_instagram_rows(): void
    {
        $this->artisan('meta:connect', ['--all' => true])
            ->expectsQuestion('Paste the user access token (input hidden)', self::SHORT)
            ->doesntExpectOutputToContain('EAApageTokenOne1234567890abc')
            ->doesntExpectOutputToContain(self::LONG)
            ->assertSuccessful();

        $this->assertSame(3, MetaAccount::count());

        $fb = MetaAccount::where('platform', Platform::Facebook)->where('page_id', '111')->sole();
        $this->assertSame('EAApageTokenOne1234567890abc', $fb->access_token);
        $this->assertSame(MetaAccount::AUTH_FACEBOOK_LOGIN, $fb->auth_type);
        $this->assertNull($fb->token_expires_at);

        $ig = MetaAccount::where('platform', Platform::Instagram)->sole();
        $this->assertSame('111', $ig->page_id);
        $this->assertSame('17841400000000001', $ig->instagram_account_id);
        $this->assertSame('page.one', $ig->page_name);
        $this->assertSame('EAApageTokenOne1234567890abc', $ig->access_token);
        $this->assertSame(MetaAccount::AUTH_FACEBOOK_LOGIN, $ig->auth_type);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me/accounts')
            && $r->hasHeader('Authorization', 'Bearer '.self::LONG)
            && ! str_contains($r->url(), self::LONG)
            && $r['appsecret_proof'] === hash_hmac('sha256', self::LONG, 'app-secret-value'));
    }

    public function test_command_is_idempotent_and_updates_tokens(): void
    {
        $existing = MetaAccount::factory()->create(['page_id' => '111', 'access_token' => 'EAAoldToken123456789012345', 'active' => false]);

        $this->artisan('meta:connect', ['--page' => ['111']])
            ->expectsQuestion('Paste the user access token (input hidden)', self::SHORT)
            ->assertSuccessful();

        $this->assertSame(2, MetaAccount::count()); // FB + IG for page 111 only
        $existing->refresh();
        $this->assertSame('EAApageTokenOne1234567890abc', $existing->access_token);
        $this->assertTrue($existing->active);
    }

    public function test_command_interactive_choice_and_subscribe(): void
    {
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')->once()->andReturn(['ok' => true, 'fields' => ['messages']]));

        $this->artisan('meta:connect', ['--subscribe' => true])
            ->expectsQuestion('Paste the user access token (input hidden)', self::SHORT)
            ->expectsChoice('Which Pages should be connected? (comma-separated)', ['Page Two [222]'], ['Page One [111]', 'Page Two [222]'])
            ->expectsOutputToContain('subscribed')
            ->assertSuccessful();

        $this->assertSame(['222'], MetaAccount::pluck('page_id')->all());
    }

    public function test_command_fails_for_unknown_page(): void
    {
        $this->artisan('meta:connect', ['--page' => ['999']])
            ->expectsQuestion('Paste the user access token (input hidden)', self::SHORT)
            ->expectsOutputToContain('999')
            ->assertFailed();

        $this->assertSame(0, MetaAccount::count());
    }

    public function test_command_reports_exchange_error_without_secrets(): void
    {
        config(['meta.graph_version' => 'v99.0']); // bypass the setUp() stubs
        Http::fake(['*' => Http::response(['error' => ['message' => 'Error validating access token: '.self::SHORT.' expired', 'code' => 190]], 400)]);

        $this->artisan('meta:connect', ['--all' => true])
            ->expectsQuestion('Paste the user access token (input hidden)', self::SHORT)
            ->expectsOutputToContain('code 190')
            ->doesntExpectOutputToContain(self::SHORT)
            ->assertFailed();
    }

    public function test_ui_connect_flow(): void
    {
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')->twice()->andReturn(['ok' => true, 'fields' => ['messages']]));

        $this->post(route('admin.meta-accounts.connect.pages'), ['user_token' => self::SHORT])
            ->assertRedirect(route('admin.meta-accounts.connect.show-pages'));

        $this->get(route('admin.meta-accounts.connect.show-pages'))
            ->assertOk()
            ->assertSee('Page One')
            ->assertSee('page.one')
            ->assertDontSee('EAApageTokenOne', false)
            ->assertDontSee(self::LONG, false);

        $this->post(route('admin.meta-accounts.connect.store'), ['page_ids' => ['111'], 'subscribe' => '1'])
            ->assertRedirect(route('admin.meta-accounts.connect.done'))
            ->assertSessionHas('success');

        $this->assertSame(2, MetaAccount::count());
        $this->assertFalse(session()->has('meta_connect_pages'));
        $this->assertSame('9988776655', MetaAccount::first()->setting('connected_by_user_id'));
    }

    public function test_ui_connect_rejects_bad_token_without_flashing_it(): void
    {
        $this->from(route('admin.meta-accounts.connect'))
            ->post(route('admin.meta-accounts.connect.pages'), ['user_token' => 'short'])
            ->assertRedirect(route('admin.meta-accounts.connect'))
            ->assertSessionHasErrors('user_token');

        $this->assertFalse(session()->hasOldInput('user_token'));
        Http::assertNothingSent();
    }

    public function test_accounts_test_command(): void
    {
        $ok = MetaAccount::factory()->create(['page_name' => 'Good']);
        $bad = MetaAccount::factory()->instagram()->create(['page_name' => 'Bad']);
        MetaAccount::factory()->create(['active' => false, 'page_name' => 'Inactive']);

        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('testConnection')->twice()->andReturnUsing(
            fn (MetaAccount $a) => $a->is($ok) ? ['ok' => true, 'id' => $a->page_id, 'name' => 'Good Page'] : ['ok' => false, 'error' => 'Invalid token'],
        ));

        $this->artisan('meta:accounts:test')
            ->expectsOutputToContain('Good Page')
            ->expectsOutputToContain('Invalid token')
            ->doesntExpectOutputToContain($ok->access_token)
            ->assertFailed();

        $this->assertNotNull($ok->fresh()->token_checked_at);
        $this->assertNotNull($bad->fresh()->token_checked_at);
    }

    public function test_admin_create_command(): void
    {
        $this->artisan('admin:create', ['email' => 'Boss@Example.com', '--name' => 'Boss', '--password' => 'secret-pass-1'])
            ->assertSuccessful();

        $user = User::where('email', 'boss@example.com')->sole();
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));

        $this->artisan('admin:create', ['email' => 'x@example.com'])
            ->expectsQuestion('Password (min 8 characters)', 'longenough1')
            ->expectsQuestion('Confirm password', 'different1')
            ->assertFailed();

        $this->artisan('admin:create', ['email' => 'y@example.com', '--password' => 'short'])->assertFailed();
        $this->assertSame(0, User::whereIn('email', ['x@example.com', 'y@example.com'])->count());
    }
}
