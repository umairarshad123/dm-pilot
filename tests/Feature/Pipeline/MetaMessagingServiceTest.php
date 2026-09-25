<?php

namespace Tests\Feature\Pipeline;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Exceptions\MetaApiException;
use App\Jobs\ProcessIncomingMetaMessage;
use App\Models\Conversation;
use App\Models\MetaAccount;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaMessagingServiceTest extends TestCase
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

    public function test_instagram_login_uses_instagram_graph_host_without_proof(): void
    {
        $account = MetaAccount::factory()->instagram()->create([
            'auth_type' => MetaAccount::AUTH_INSTAGRAM_LOGIN, 'page_id' => null, 'access_token' => 'IGAAtesttoken1234567890',
        ]);
        Http::fake(['graph.instagram.com/*' => Http::response(['recipient_id' => '1', 'message_id' => 'aWdf'])]);

        $result = $this->service()->sendText($account, '1', 'Hello');

        $this->assertTrue($result->ok);
        $this->assertSame('aWdf', $result->messageId);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.instagram.com/v26.0/'.$account->instagram_account_id.'/messages'
            && ! isset($r->data()['appsecret_proof'])
            && ! isset($r->data()['messaging_type'])
            && $r['access_token'] === 'IGAAtesttoken1234567890');
    }

    public function test_instagram_limit_is_measured_in_bytes(): void
    {
        $account = MetaAccount::factory()->instagram()->create(['page_id' => '42']);
        Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'm'])]);
        $text = str_repeat('héllo wörld ✨ ', 150); // multibyte, > 1000 bytes

        $this->service()->sendText($account, '1', $text);

        $sends = $this->graphSends();
        $this->assertGreaterThan(1, count($sends));
        foreach ($sends as $send) {
            $this->assertLessThanOrEqual(1000, strlen($send['message']['text']));
            $this->assertTrue(mb_check_encoding($send['message']['text'], 'UTF-8'));
        }
    }

    public function test_graph_errors_are_classified(): void
    {
        $account = MetaAccount::factory()->create();

        $cases = [
            [['code' => 613, 'message' => 'rate'], 400, true],
            [['code' => 80006, 'message' => 'rate'], 400, true],
            [['code' => 2, 'message' => 'temporary'], 500, true],
            [['code' => 190, 'message' => 'bad token'], 400, false],
            [['code' => 10, 'error_subcode' => 2018278, 'message' => 'window'], 400, false],
            [['code' => 551, 'error_subcode' => 1545041, 'message' => 'unavailable'], 400, false],
            [['code' => 200, 'message' => 'perm'], 403, false],
        ];

        foreach ($cases as [$error, $status, $retryable]) {
            Http::fake(['graph.facebook.com/*' => Http::response(['error' => $error + ['fbtrace_id' => 'X']], $status)]);
            $result = $this->service()->sendText($account, '1', 'Hi');

            $this->assertFalse($result->ok);
            $this->assertSame($error['code'], $result->errorCode);
            $this->assertSame($retryable, $result->retryable, 'code '.$error['code']);
            Http::swap(new Factory);
        }
    }

    public function test_connection_errors_are_retryable_and_scrubbed(): void
    {
        $account = MetaAccount::factory()->create();
        $token = $account->access_token;
        Http::fake(fn () => throw new ConnectionException("cURL error 28 for https://graph.facebook.com/x?access_token={$token}"));

        $result = $this->service()->sendText($account, '1', 'Hi');

        $this->assertTrue($result->retryable);
        $this->assertStringNotContainsString($token, $result->error);
    }

    public function test_sender_action_never_throws(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake(['*' => Http::response(['error' => ['code' => 100, 'message' => 'bad']], 400)]);

        $this->assertFalse($this->service()->sendSenderAction($account, '1', 'typing_on'));
        $this->assertFalse($this->service()->sendSenderAction($account, '1', 'bogus'));
    }

    public function test_test_connection_uses_bearer_header_and_debug_token(): void
    {
        $account = MetaAccount::factory()->create();
        $expires = now()->addDays(50)->timestamp;
        Http::fake([
            'graph.facebook.com/v26.0/me*' => Http::response(['id' => $account->page_id, 'name' => 'My Page']),
            'graph.facebook.com/v26.0/debug_token*' => Http::response(['data' => [
                'is_valid' => true, 'expires_at' => $expires, 'scopes' => ['pages_messaging'],
            ]]),
        ]);

        $result = $this->service()->testConnection($account);

        $this->assertTrue($result['ok']);
        $this->assertSame('My Page', $result['name']);
        $this->assertSame(['pages_messaging'], $result['scopes']);
        $account->refresh();
        $this->assertNotNull($account->token_checked_at);
        $this->assertSame($expires, $account->token_expires_at->timestamp);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me?')
            && ! str_contains($r->url(), 'access_token')
            && $r->hasHeader('Authorization', 'Bearer '.$account->access_token));
    }

    public function test_test_connection_reports_failure(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake(['*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']], 400)]);

        $result = $this->service()->testConnection($account);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('190', $result['error']);
        $this->assertNotNull($account->fresh()->token_checked_at);
    }

    public function test_subscribe_app(): void
    {
        $account = MetaAccount::factory()->create();
        Http::fake(['*' => Http::response(['success' => true])]);

        $result = $this->service()->subscribeApp($account);

        $this->assertTrue($result['ok']);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v26.0/'.$account->page_id.'/subscribed_apps'
            && $r['subscribed_fields'] === 'messages,messaging_postbacks,message_echoes');

        $ig = MetaAccount::factory()->instagram()->create(['auth_type' => MetaAccount::AUTH_INSTAGRAM_LOGIN]);
        $this->service()->subscribeApp($ig);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.instagram.com/v26.0/me/subscribed_apps'
            && $r['subscribed_fields'] === 'messages,messaging_postbacks');
    }

    public function test_human_reply_is_stored_and_pauses_bot(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::factory()->create();
        Http::fake(['*' => Http::response(['message_id' => 'm_human_1'])]);

        $message = app(ConversationService::class)->sendHumanReply($conversation, '  Hi, Sam here. ', $user);

        $this->assertSame(SenderType::Human, $message->sender_type);
        $this->assertSame(MessageDirection::Outgoing, $message->direction);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('m_human_1', $message->external_message_id);
        $this->assertSame('Hi, Sam here.', $message->body);
        $this->assertTrue($conversation->fresh()->isPaused());
        $this->assertSame('chatbot:human', $this->graphSends()[0]['message']['metadata']);

        // The echo of that send must not create a second row.
        ProcessIncomingMetaMessage::dispatch($this->echo($conversation->metaAccount, [
            'messageId' => 'm_human_1', 'recipientId' => $conversation->external_user_id,
        ]));
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_human_reply_failure_throws_safe_exception_and_stores_failed_row(): void
    {
        $conversation = Conversation::factory()->create();
        $token = $conversation->metaAccount->access_token;
        Http::fake(['*' => Http::response(['error' => [
            'code' => 10, 'error_subcode' => 2018278, 'message' => "Outside window {$token}",
        ]], 400)]);

        try {
            app(ConversationService::class)->sendHumanReply($conversation, 'Hello?');
            $this->fail('Expected MetaApiException');
        } catch (MetaApiException $e) {
            $this->assertStringContainsString('24-hour', $e->getMessage());
            $this->assertStringNotContainsString($token, $e->getMessage());
        }

        $row = $conversation->messages()->sole();
        $this->assertSame(MessageStatus::Failed, $row->status);
        $this->assertStringNotContainsString($token, $row->error);
        $this->assertFalse($conversation->fresh()->isPaused());
    }
}
