<?php

namespace Tests\Feature\UI\Pages;

use App\Enums\Platform;
use App\Models\MetaAccount;
use App\Services\Meta\FacebookLoginService;
use App\Services\Meta\MetaMessagingService;
use App\Services\Meta\SignedRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\Feature\Admin\AdminTestCase;

class ConnectWizardTest extends AdminTestCase
{
    private const CODE = 'AQDcodeFromFacebook1234567890';

    private const SHORT = 'EAAshortFromCode1234567890abc';

    private const LONG = 'EAAlongLivedFromCode0987654321';

    private const PAGE_TOKEN = 'EAApageTokenSecret1234567890xyz';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'meta.app_id' => '1122334455',
            'meta.app_secret' => 'app-secret-value',
            'meta.graph_url' => 'https://graph.facebook.com',
            'meta.graph_version' => 'v26.0',
            'meta.login.config_id' => null,
        ]);

        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                str_contains($url, '/oauth/access_token') && ($request->data()['grant_type'] ?? null) === 'fb_exchange_token' => Http::response(['access_token' => self::LONG, 'expires_in' => 5184000]),
                str_contains($url, '/oauth/access_token') => Http::response(['access_token' => self::SHORT, 'expires_in' => 3600]),
                str_contains($url, '/me/accounts') => Http::response(['data' => [
                    ['id' => '111', 'name' => 'Client Bakery', 'access_token' => self::PAGE_TOKEN,
                        'picture' => ['data' => ['url' => 'https://scontent.example/bakery.jpg']],
                        'instagram_business_account' => ['id' => '17841400000000001', 'username' => 'client.bakery', 'profile_picture_url' => 'https://scontent.example/ig.jpg']],
                    ['id' => '222', 'name' => 'Another Client', 'access_token' => 'EAAotherPageToken1234567890'],
                ]]),
                str_contains($url, '/v26.0/me') => Http::response(['id' => '10229876543', 'name' => 'Agency Owner']),
                default => Http::response(['error' => ['message' => 'unexpected '.$url]], 500),
            };
        });
    }

    public function test_step_one_shows_both_methods(): void
    {
        $this->get(route('admin.meta-accounts.connect'))->assertOk()
            ->assertSee('Continue with Facebook')->assertSee(route('admin.meta-accounts.oauth.redirect'))
            ->assertSee('Paste a user access token')
            ->assertSee(route('admin.meta-accounts.oauth.callback'))
            ->assertSee('pages_messaging');
    }

    public function test_oauth_redirect_has_state_and_scopes(): void
    {
        $response = $this->get(route('admin.meta-accounts.oauth.redirect'));
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('1122334455', $query['client_id']);
        $this->assertSame(route('admin.meta-accounts.oauth.callback'), $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(session(FacebookLoginService::STATE_KEY)['value'], $query['state']);
        $this->assertGreaterThanOrEqual(32, strlen($query['state']));
        $this->assertStringContainsString('pages_messaging', $query['scope']);
        $this->assertStringContainsString('instagram_manage_messages', $query['scope']);
        $this->assertArrayNotHasKey('config_id', $query);
        $this->assertStringNotContainsString('app-secret-value', $location);
    }

    public function test_oauth_redirect_uses_config_id_when_set(): void
    {
        config(['meta.login.config_id' => '998877']);

        $location = $this->get(route('admin.meta-accounts.oauth.redirect'))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('998877', $query['config_id']);
        $this->assertSame('true', $query['override_default_response_type']);
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function test_oauth_redirect_requires_app_credentials(): void
    {
        config(['meta.app_secret' => null]);

        $this->get(route('admin.meta-accounts.oauth.redirect'))
            ->assertRedirect(route('admin.meta-accounts.connect'))->assertSessionHas('error');
    }

    public function test_callback_rejects_bad_or_missing_state(): void
    {
        $this->get(route('admin.meta-accounts.oauth.redirect'));

        $this->get(route('admin.meta-accounts.oauth.callback', ['code' => self::CODE, 'state' => 'forged']))
            ->assertRedirect(route('admin.meta-accounts.connect'))->assertSessionHas('error');

        // State is single use: even the right value no longer works after a failed attempt.
        $this->get(route('admin.meta-accounts.oauth.callback', ['code' => self::CODE]))
            ->assertRedirect(route('admin.meta-accounts.connect'))->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, MetaAccount::count());
    }

    public function test_callback_handles_user_cancel(): void
    {
        $this->get(route('admin.meta-accounts.oauth.redirect'));
        $state = session(FacebookLoginService::STATE_KEY)['value'];

        $this->get(route('admin.meta-accounts.oauth.callback', ['error' => 'access_denied', 'error_reason' => 'user_denied', 'state' => $state]))
            ->assertRedirect(route('admin.meta-accounts.connect'))
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'cancelled'));
        Http::assertNothingSent();
    }

    public function test_full_oauth_flow_saves_selected_pages_with_connected_by_user_id(): void
    {
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')->twice()
            ->andReturn(['ok' => true, 'fields' => ['messages']], ['ok' => false, 'error' => 'IG not allowed']));

        $this->get(route('admin.meta-accounts.oauth.redirect'));
        $state = session(FacebookLoginService::STATE_KEY)['value'];

        $this->get(route('admin.meta-accounts.oauth.callback', ['code' => self::CODE, 'state' => $state]))
            ->assertRedirect(route('admin.meta-accounts.connect.show-pages'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/access_token')
            && ($r->data()['code'] ?? null) === self::CODE && ($r->data()['redirect_uri'] ?? null) === route('admin.meta-accounts.oauth.callback'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/access_token') && ($r->data()['fb_exchange_token'] ?? null) === self::SHORT);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me/accounts') && $r->hasHeader('Authorization', 'Bearer '.self::LONG)
            && str_contains((string) ($r->data()['fields'] ?? ''), 'picture'));

        // Step 2: nothing pre-selected, no tokens in the HTML.
        $picker = $this->get(route('admin.meta-accounts.connect.show-pages'))->assertOk()
            ->assertSee('Client Bakery')->assertSee('@client.bakery')->assertSee('Another Client')
            ->assertSee('https://scontent.example/bakery.jpg')
            ->assertSee('Agency Owner');
        foreach ([self::SHORT, self::LONG, self::PAGE_TOKEN, self::CODE] as $secret) {
            $picker->assertDontSee($secret, false);
        }
        $this->assertStringNotContainsString(' checked', (string) preg_replace('/name="subscribe"[^>]*/', '', $picker->getContent()));

        // Step 3: save one page (+ its IG) and subscribe.
        $this->post(route('admin.meta-accounts.connect.store'), ['page_ids' => ['111'], 'subscribe' => '1'])
            ->assertRedirect(route('admin.meta-accounts.connect.done'));

        $this->assertSame(2, MetaAccount::count());
        $fb = MetaAccount::where('platform', Platform::Facebook)->sole();
        $ig = MetaAccount::where('platform', Platform::Instagram)->sole();
        $this->assertSame(self::PAGE_TOKEN, $fb->access_token);
        $this->assertSame('10229876543', $fb->setting('connected_by_user_id'));
        $this->assertSame('10229876543', $ig->setting('connected_by_user_id'));
        $this->assertSame('facebook_login', $fb->setting('connected_via'));
        $this->assertSame('https://scontent.example/bakery.jpg', $fb->setting('picture_url'));
        $this->assertSame('https://scontent.example/ig.jpg', $ig->setting('picture_url'));
        $this->assertTrue($fb->setting('webhook_subscribed'));
        $this->assertFalse($ig->setting('webhook_subscribed'));

        $this->get(route('admin.meta-accounts.connect.done'))->assertOk()
            ->assertSee("You're connected", false)->assertSee('Client Bakery')->assertSee('Subscribe failed')
            ->assertSee('Set up Bot Studio')->assertDontSee(self::PAGE_TOKEN, false);

        $this->get(route('admin.meta-accounts.index'))->assertOk()->assertDontSee(self::PAGE_TOKEN, false);
    }

    public function test_deauthorize_callback_finds_accounts_connected_through_the_wizard(): void
    {
        $this->mock(MetaMessagingService::class, fn (MockInterface $m) => $m->shouldReceive('subscribeApp')->never());

        $this->post(route('admin.meta-accounts.connect.pages'), ['user_token' => self::SHORT])
            ->assertRedirect(route('admin.meta-accounts.connect.show-pages'));
        $this->post(route('admin.meta-accounts.connect.store'), ['page_ids' => ['222']]);

        $account = MetaAccount::sole();
        $this->assertSame('10229876543', $account->setting('connected_by_user_id'));
        $this->assertSame('token', $account->setting('connected_via'));

        $signed = app(SignedRequest::class)->make(['user_id' => '10229876543']);
        $this->post('/meta/deauthorize', ['signed_request' => $signed])->assertOk();

        $this->assertFalse($account->fresh()->active);
        $this->get(route('admin.meta-accounts.index'))->assertSee('Token problem');
    }

    public function test_store_requires_a_selection_and_a_session(): void
    {
        $this->post(route('admin.meta-accounts.connect.store'), ['page_ids' => ['111']])
            ->assertRedirect(route('admin.meta-accounts.connect'))->assertSessionHas('error');

        $this->post(route('admin.meta-accounts.connect.pages'), ['user_token' => self::SHORT]);
        $this->from(route('admin.meta-accounts.connect.show-pages'))
            ->post(route('admin.meta-accounts.connect.store'), ['page_ids' => []])
            ->assertSessionHasErrors('page_ids');

        $this->assertSame(0, MetaAccount::count());
        $this->get(route('admin.meta-accounts.connect.done'))->assertRedirect(route('admin.meta-accounts.index'));
    }

    public function test_picker_marks_already_connected_pages(): void
    {
        MetaAccount::factory()->create(['page_id' => '111', 'page_name' => 'Client Bakery']);

        $this->post(route('admin.meta-accounts.connect.pages'), ['user_token' => self::SHORT]);

        $this->get(route('admin.meta-accounts.connect.show-pages'))->assertOk()
            ->assertSee('Connected')->assertSee('Refreshes token')
            ->assertViewHas('pages', fn ($pages) => collect($pages)->firstWhere('id', '111')['connected'] === true
                && collect($pages)->firstWhere('id', '222')['connected'] === false);
    }
}
