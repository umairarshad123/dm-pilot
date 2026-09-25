<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Models\Conversation;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Full chain: signed webhook → queue (sync) → parse → store → OpenAI → Graph send → stored reply. */
class EndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'e2e-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta.app_id' => '555000111',
            'meta.app_secret' => self::SECRET,
            'meta.verify_signature' => true,
            'openai.api_key' => 'sk-test-e2e',
            'openai.temperature' => null,
        ]);
    }

    private function postSigned(array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/meta', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function messengerPayload(string $pageId, string $mid, string $text, array $extra = []): array
    {
        return ['object' => 'page', 'entry' => [['id' => $pageId, 'time' => now()->getTimestampMs(), 'messaging' => [[
            'sender' => ['id' => $extra['sender'] ?? 'PSID_1'],
            'recipient' => ['id' => $extra['recipient'] ?? $pageId],
            'timestamp' => now()->getTimestampMs(),
            'message' => array_merge(['mid' => $mid, 'text' => $text], $extra['message'] ?? []),
        ]]]]];
    }

    private function fakeApis(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'status' => 'completed',
                'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'We are open 9-5 today!']]]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
            'graph.facebook.com/*' => Http::response(['recipient_id' => 'PSID_1', 'message_id' => 'm_bot_reply_1']),
        ]);
    }

    public function test_messenger_message_gets_one_ai_reply_even_when_redelivered(): void
    {
        $this->fakeApis();
        $account = MetaAccount::factory()->create(['page_id' => '111222333']);
        $payload = $this->messengerPayload('111222333', 'm_customer_1', 'Are you open today?');

        $this->postSigned($payload)->assertOk()->assertSee('EVENT_RECEIVED');
        $this->postSigned($payload)->assertOk(); // identical redelivery

        $this->assertSame(1, WebhookEvent::count());
        $conversation = Conversation::sole();
        $this->assertSame('PSID_1', $conversation->external_user_id);

        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame(SenderType::Customer, $messages[0]->sender_type);
        $this->assertSame(SenderType::Bot, $messages[1]->sender_type);
        $this->assertSame(MessageStatus::Sent, $messages[1]->status);
        $this->assertSame('We are open 9-5 today!', $messages[1]->body);
        $this->assertSame('m_bot_reply_1', $messages[1]->external_message_id);
        $this->assertSame($messages[0]->id, $messages[1]->in_reply_to_id);

        $sends = Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/111222333/messages') && isset($r['message']))->values();
        $this->assertCount(1, $sends);
        $this->assertStringNotContainsString('access_token', $sends[0][0]->url());
        $this->assertSame('PSID_1', $sends[0][0]['recipient']['id']);

        // Our own echo arrives afterwards: must not create a message nor pause the bot.
        $this->postSigned($this->messengerPayload('111222333', 'm_bot_reply_1', 'We are open 9-5 today!', [
            'sender' => '111222333', 'recipient' => 'PSID_1',
            'message' => ['is_echo' => true, 'app_id' => 555000111, 'metadata' => 'chatbot:bot'],
        ]))->assertOk();

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertFalse($conversation->fresh()->isPaused());
    }

    public function test_human_reply_from_page_inbox_pauses_the_bot(): void
    {
        $this->fakeApis();
        MetaAccount::factory()->create(['page_id' => '111222333']);

        $this->postSigned($this->messengerPayload('111222333', 'm_human_1', 'Hi, this is Sara from the team', [
            'sender' => '111222333', 'recipient' => 'PSID_1',
            'message' => ['is_echo' => true, 'app_id' => 263902037430900],
        ]))->assertOk();

        $conversation = Conversation::sole();
        $this->assertTrue($conversation->isPaused());
        $this->assertSame(SenderType::Human, $conversation->messages()->sole()->sender_type);

        // Customer writes back during the pause → stored, but no AI call and no send.
        $this->postSigned($this->messengerPayload('111222333', 'm_customer_2', 'Thanks Sara'))->assertOk();

        $this->assertSame(2, $conversation->messages()->count());
        Http::assertNothingSent();
    }

    public function test_unsigned_webhook_is_rejected_and_nothing_is_stored(): void
    {
        $this->call('POST', '/webhooks/meta', [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->messengerPayload('111222333', 'm_x', 'hi')))->assertForbidden();

        $this->assertSame(0, WebhookEvent::count());
    }
}
