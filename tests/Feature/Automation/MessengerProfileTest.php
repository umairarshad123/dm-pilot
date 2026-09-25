<?php

namespace Tests\Feature\Automation;

use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Pipeline\PipelineHelpers;
use Tests\TestCase;

class MessengerProfileTest extends TestCase
{
    use PipelineHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPipeline();
    }

    private function service(): MetaMessagingService
    {
        return app(MetaMessagingService::class);
    }

    public function test_messenger_profile_is_set_with_page_token_and_saved(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake(['graph.facebook.com/*' => Http::response(['result' => 'success'])]);

        $result = $this->service()->setMessengerProfile($account, [
            'greeting' => 'Hi {{user_first_name}}, welcome!',
            'get_started' => true,
            'ice_breakers' => [
                ['question' => 'What are your hours?', 'payload' => 'HOURS'],
                ['question' => 'Do you deliver?'],
                ['question' => '   '],
            ],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['warnings']); // get_started + ice breakers

        Http::assertSentCount(1);
        $request = Http::recorded()->first()[0];
        $this->assertSame('POST', $request->method());
        $this->assertStringEndsWith('/v26.0/me/messenger_profile', $request->url());
        $this->assertSame($account->access_token, $request['access_token']);
        $this->assertArrayNotHasKey('platform', $request->data());
        $this->assertSame([['locale' => 'default', 'text' => 'Hi {{user_first_name}}, welcome!']], $request['greeting']);
        $this->assertSame(['payload' => 'GET_STARTED'], $request['get_started']);
        $this->assertSame([[
            'call_to_actions' => [
                ['question' => 'What are your hours?', 'payload' => 'HOURS'],
                ['question' => 'Do you deliver?', 'payload' => 'ICE_BREAKER_2'],
            ],
            'locale' => 'default',
        ]], $request['ice_breakers']);

        $saved = $account->fresh()->messengerProfile();
        $this->assertSame('Hi {{user_first_name}}, welcome!', $saved['greeting']);
        $this->assertTrue($saved['get_started']);
        $this->assertCount(2, $saved['ice_breakers']);
        $this->assertArrayHasKey('updated_at', $saved);
        $this->assertStringNotContainsString($account->access_token, json_encode($this->logs));
    }

    public function test_cleared_values_are_deleted_and_untouched_keys_kept(): void
    {
        $account = MetaAccount::factory()->create(['settings' => ['other' => 1, 'messenger_profile' => ['greeting' => 'Old', 'get_started' => true]]]);
        Http::fake(['graph.facebook.com/*' => Http::response(['result' => 'success'])]);

        $result = $this->service()->setMessengerProfile($account, ['get_started' => false, 'ice_breakers' => []]);

        $this->assertTrue($result['ok']);
        Http::assertSentCount(1);
        $request = Http::recorded()->first()[0];
        $this->assertSame('DELETE', $request->method());
        $this->assertSame(['get_started', 'ice_breakers'], $request['fields']);

        $fresh = $account->fresh();
        $this->assertSame(1, $fresh->setting('other'));
        $this->assertSame('Old', $fresh->messengerProfile()['greeting']);
        $this->assertFalse($fresh->messengerProfile()['get_started']);
        $this->assertSame([], $fresh->messengerProfile()['ice_breakers']);
    }

    public function test_instagram_sends_platform_and_only_ice_breakers(): void
    {
        $account = MetaAccount::factory()->instagram()->create();
        Http::fake(['graph.facebook.com/*' => Http::response(['result' => 'success'])]);

        $result = $this->service()->setMessengerProfile($account, [
            'greeting' => 'ignored', 'get_started' => true, 'ice_breakers' => [['question' => 'Prices?', 'payload' => 'PRICES']],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['warnings']);
        $request = Http::recorded()->first()[0];
        $this->assertSame('instagram', $request['platform']);
        $this->assertArrayNotHasKey('greeting', $request->data());
        $this->assertArrayNotHasKey('get_started', $request->data());
        $this->assertSame('PRICES', $request['ice_breakers'][0]['call_to_actions'][0]['payload']);
        $this->assertArrayNotHasKey('greeting', $account->fresh()->messengerProfile());
    }

    public function test_instagram_login_uses_instagram_graph_host(): void
    {
        $account = MetaAccount::factory()->instagram()->create(['auth_type' => MetaAccount::AUTH_INSTAGRAM_LOGIN, 'access_token' => 'IGAAtest1234567890abcdef']);
        Http::fake(['graph.instagram.com/*' => Http::response(['result' => 'success'])]);

        $result = $this->service()->setMessengerProfile($account, ['ice_breakers' => [['question' => 'Hi?']]]);

        $this->assertTrue($result['ok']);
        $this->assertStringStartsWith('https://graph.instagram.com/v26.0/me/messenger_profile', Http::recorded()->first()[0]->url());
    }

    public function test_validation_errors_do_not_call_meta(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake();

        $tooMany = $this->service()->setMessengerProfile($account, ['ice_breakers' => array_fill(0, 5, ['question' => 'Q'])]);
        $longGreeting = $this->service()->setMessengerProfile($account, ['greeting' => str_repeat('x', 161)]);
        $longQuestion = $this->service()->setMessengerProfile($account, ['ice_breakers' => [['question' => str_repeat('q', 81)]]]);

        $this->assertFalse($tooMany['ok']);
        $this->assertFalse($longGreeting['ok']);
        $this->assertFalse($longQuestion['ok']);
        Http::assertNothingSent();
        $this->assertSame([], $account->fresh()->messengerProfile());
    }

    public function test_graph_error_is_returned_not_thrown_and_not_saved(): void
    {
        $account = MetaAccount::factory()->create();
        $token = $account->access_token;
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => [
            'message' => "Invalid token {$token}", 'code' => 190,
        ]], 400)]);

        $result = $this->service()->setMessengerProfile($account, ['greeting' => 'Hello']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('190', $result['error']);
        $this->assertStringNotContainsString($token, $result['error']);
        $this->assertSame([], $account->fresh()->messengerProfile());
        $this->assertStringNotContainsString($token, json_encode($this->logs));
    }

    public function test_get_profile_parses_both_ice_breaker_formats(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [[
                'greeting' => [['locale' => 'en_US', 'text' => 'US'], ['locale' => 'default', 'text' => 'Hello!']],
                'get_started' => ['payload' => 'GET_STARTED'],
                'ice_breakers' => [['call_to_actions' => [['question' => 'Q1', 'payload' => 'P1']], 'locale' => 'default']],
            ]]])
            ->push(['data' => [['ice_breakers' => [['question' => 'Old', 'payload' => 'OLD']]]]])
            ->push(['data' => []]),
        ]);

        $first = $this->service()->getMessengerProfile($account);
        $legacy = $this->service()->getMessengerProfile($account);
        $empty = $this->service()->getMessengerProfile($account);

        $this->assertSame(['ok' => true, 'profile' => [
            'greeting' => 'Hello!', 'get_started' => true, 'ice_breakers' => [['question' => 'Q1', 'payload' => 'P1']],
        ]], $first);
        $this->assertSame([['question' => 'Old', 'payload' => 'OLD']], $legacy['profile']['ice_breakers']);
        $this->assertSame(['greeting' => null, 'get_started' => false, 'ice_breakers' => []], $empty['profile']);

        $request = Http::recorded()->first()[0];
        $this->assertSame('GET', $request->method());
        $this->assertStringContainsString('fields=greeting%2Cget_started%2Cice_breakers', $request->url());
        $this->assertStringNotContainsString('access_token', $request->url());
    }

    public function test_get_profile_for_instagram_and_errors(): void
    {
        $account = MetaAccount::factory()->instagram()->create();
        Http::fake(['graph.facebook.com/*' => Http::sequence()
            ->push(['data' => [['ice_breakers' => []]]])
            ->push(['error' => ['message' => 'Nope', 'code' => 100]], 400),
        ]);

        $this->assertTrue($this->service()->getMessengerProfile($account)['ok']);
        $this->assertStringContainsString('platform=instagram', Http::recorded()->first()[0]->url());

        $error = $this->service()->getMessengerProfile($account);
        $this->assertFalse($error['ok']);
        $this->assertStringContainsString('Nope', $error['error']);
    }
}
