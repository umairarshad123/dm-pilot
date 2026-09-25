<?php

namespace Tests\Unit\Admin;

use App\Services\Meta\MetaTokenService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class MetaTokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta.app_id' => '42',
            'meta.app_secret' => 'shh-secret',
            'meta.graph_url' => 'https://graph.facebook.com',
            'meta.graph_version' => 'v26.0',
        ]);
    }

    public function test_exchange_for_long_lived_token(): void
    {
        Http::fake(['graph.facebook.com/v26.0/oauth/access_token*' => Http::response(['access_token' => 'LONG_TOKEN_abc', 'expires_in' => 3600])]);

        $result = app(MetaTokenService::class)->exchangeForLongLivedUserToken('SHORT_TOKEN_xyz');

        $this->assertSame('LONG_TOKEN_abc', $result['access_token']);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $result['expires_at']->timestamp, 5);

        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'fb_exchange_token'
            && $r['client_id'] === '42'
            && $r['client_secret'] === 'shh-secret'
            && $r['fb_exchange_token'] === 'SHORT_TOKEN_xyz');
    }

    public function test_exchange_requires_app_credentials(): void
    {
        config(['meta.app_secret' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('META_APP_ID and META_APP_SECRET must be set.');

        app(MetaTokenService::class)->exchangeForLongLivedUserToken('SHORT');
    }

    public function test_graph_errors_are_scrubbed(): void
    {
        Http::fake(['*' => Http::response(['error' => [
            'message' => 'Bad token SHORT_TOKEN_xyz with client_secret=shh-secret&access_token=ZZZ',
            'type' => 'OAuthException', 'code' => 190,
        ]], 400)]);

        try {
            app(MetaTokenService::class)->exchangeForLongLivedUserToken('SHORT_TOKEN_xyz');
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('code 190', $e->getMessage());
            $this->assertStringNotContainsString('SHORT_TOKEN_xyz', $e->getMessage());
            $this->assertStringNotContainsString('shh-secret', $e->getMessage());
            $this->assertStringNotContainsString('ZZZ', $e->getMessage());
        }
    }

    public function test_connection_errors_do_not_leak_url(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://graph.facebook.com/v26.0/oauth/access_token?fb_exchange_token=SHORT_TOKEN_xyz&client_secret=shh-secret'));

        try {
            app(MetaTokenService::class)->exchangeForLongLivedUserToken('SHORT_TOKEN_xyz');
            $this->fail('Expected exception');
        } catch (RuntimeException $e) {
            $this->assertSame('Meta token exchange request failed (network error).', $e->getMessage());
        }

        Log::shouldHaveReceived('warning')->withArgs(fn (string $msg, array $ctx = []) => ! str_contains($msg.json_encode($ctx), 'SHORT_TOKEN_xyz')
            && ! str_contains($msg.json_encode($ctx), 'shh-secret'));
    }

    public function test_list_pages_paginates_with_bearer_token(): void
    {
        Http::fakeSequence('graph.facebook.com/v26.0/me/accounts*')
            ->push([
                'data' => [['id' => '1', 'name' => 'One', 'access_token' => 'PT1', 'instagram_business_account' => ['id' => '9001', 'username' => 'one.ig']]],
                'paging' => ['cursors' => ['before' => 'b', 'after' => 'CURSOR1'], 'next' => 'https://graph.facebook.com/v26.0/me/accounts?after=CURSOR1'],
            ])
            ->push([
                'data' => [['id' => '2', 'name' => 'Two', 'access_token' => 'PT2'], ['id' => '3', 'name' => 'No token']],
                'paging' => ['cursors' => ['before' => 'c', 'after' => 'CURSOR2']],
            ]);

        $pages = app(MetaTokenService::class)->listPages('USER_TOKEN');

        $this->assertSame([
            ['id' => '1', 'name' => 'One', 'access_token' => 'PT1', 'instagram_business_account' => ['id' => '9001', 'username' => 'one.ig']],
            ['id' => '2', 'name' => 'Two', 'access_token' => 'PT2', 'instagram_business_account' => null],
        ], $pages);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer USER_TOKEN')
            && ! str_contains($r->url(), 'USER_TOKEN')
            && $r['appsecret_proof'] === hash_hmac('sha256', 'USER_TOKEN', 'shh-secret')
            && str_contains($r['fields'], 'instagram_business_account{id,username}'));
        Http::assertSent(fn (Request $r) => ($r['after'] ?? null) === 'CURSOR1');
    }

    public function test_list_pages_without_app_secret_omits_proof(): void
    {
        config(['meta.app_secret' => null]);
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->assertSame([], app(MetaTokenService::class)->listPages('USER_TOKEN'));
        Http::assertSent(fn (Request $r) => ! isset($r['appsecret_proof']));
    }

    public function test_debug_token(): void
    {
        $expires = now()->addDays(60)->startOfSecond();
        Http::fake(['graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => [
            'app_id' => '42', 'type' => 'USER', 'is_valid' => true,
            'expires_at' => $expires->timestamp, 'data_access_expires_at' => 0,
            'scopes' => ['pages_messaging', 'pages_show_list'],
        ]])]);

        $info = app(MetaTokenService::class)->debugToken('SOME_TOKEN');

        $this->assertTrue($info['is_valid']);
        $this->assertSame($expires->timestamp, $info['expires_at']->timestamp);
        $this->assertNull($info['data_access_expires_at']);
        $this->assertSame(['pages_messaging', 'pages_show_list'], $info['scopes']);
        $this->assertSame('USER', $info['type']);

        Http::assertSent(fn (Request $r) => $r['input_token'] === 'SOME_TOKEN' && $r->hasHeader('Authorization', 'Bearer 42|shh-secret'));
    }

    public function test_debug_token_never_expiring(): void
    {
        Http::fake(['*' => Http::response(['data' => ['is_valid' => true, 'expires_at' => 0, 'scopes' => []]])]);

        $this->assertNull(app(MetaTokenService::class)->debugToken('X')['expires_at']);
    }
}
