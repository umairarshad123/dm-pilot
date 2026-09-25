<?php

namespace Tests\Feature\Pipeline;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Jobs\GenerateAndSendReply;
use App\Jobs\ProcessIncomingMetaMessage;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\Bot\BotSettingsResolver;
use App\Services\Bot\ReplyPolicy;
use App\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplyPipelineTest extends TestCase
{
    use PipelineHelpers, RefreshDatabase;

    private MetaAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPipeline();
        $this->account = MetaAccount::factory()->create();
    }

    public function test_incoming_message_gets_an_ai_reply(): void
    {
        $this->fakeApis('We start at $20.');

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $conversation = Conversation::sole();
        $this->assertSame('9001', $conversation->external_user_id);
        $this->assertNotNull($conversation->last_customer_message_at);

        $incoming = Message::where('direction', MessageDirection::Incoming)->sole();
        $reply = Message::where('direction', MessageDirection::Outgoing)->sole();

        $this->assertSame(MessageStatus::Received, $incoming->status);
        $this->assertSame(SenderType::Bot, $reply->sender_type);
        $this->assertSame(MessageStatus::Sent, $reply->status);
        $this->assertSame('We start at $20.', $reply->body);
        $this->assertSame($incoming->id, $reply->in_reply_to_id);
        $this->assertStringStartsWith('m_out_', $reply->external_message_id);

        $sends = $this->graphSends();
        $this->assertCount(1, $sends);
        $this->assertSame('9001', $sends[0]['recipient']['id']);
        $this->assertSame('RESPONSE', $sends[0]['messaging_type']);
        $this->assertSame('We start at $20.', $sends[0]['message']['text']);
        $this->assertStringEndsWith('/v26.0/'.$this->account->page_id.'/messages', $sends[0]->url());

        // OpenAI request shape
        $openAi = Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.openai.com'))->first()[0];
        $this->assertSame('https://api.openai.com/v1/responses', $openAi->url());
        $this->assertSame('user', $openAi['input'][0]['role']);
        $this->assertSame('Hi, what are your prices?', $openAi['input'][0]['content']);
        $this->assertFalse($openAi['store']);
        $this->assertArrayNotHasKey('temperature', $openAi->data());
    }

    public function test_duplicate_delivery_produces_one_reply(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $this->assertSame(1, Message::where('direction', MessageDirection::Incoming)->count());
        $this->assertSame(1, Message::where('direction', MessageDirection::Outgoing)->count());
        $this->assertCount(1, $this->graphSends());
        $this->assertSame(1, $this->openAiCalls());
    }

    public function test_unknown_account_is_ignored(): void
    {
        $this->fakeApis();
        $dto = $this->incoming($this->account, ['accountExternalId' => '999999']);

        ProcessIncomingMetaMessage::dispatch($dto);

        $this->assertSame(0, Conversation::count());
        Http::assertNothingSent();
    }

    public function test_message_from_own_account_is_ignored(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, ['senderId' => $this->account->page_id]));

        $this->assertSame(0, Message::count());
    }

    public function test_echo_from_our_app_is_stored_as_bot_without_pausing(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->echo($this->account, ['appId' => self::OUR_APP_ID]));

        $message = Message::sole();
        $this->assertSame(SenderType::Bot, $message->sender_type);
        $this->assertSame(MessageDirection::Outgoing, $message->direction);
        $this->assertFalse(Conversation::sole()->isPaused());
        Http::assertNothingSent();
    }

    public function test_echo_of_an_already_stored_message_is_ignored(): void
    {
        $this->fakeApis();
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));
        $reply = Message::where('direction', MessageDirection::Outgoing)->sole();

        ProcessIncomingMetaMessage::dispatch($this->echo($this->account, [
            'messageId' => $reply->external_message_id, 'appId' => self::OUR_APP_ID, 'text' => $reply->body,
        ]));

        $this->assertSame(2, Message::count());
        $this->assertFalse(Conversation::sole()->isPaused());
    }

    public function test_echo_of_our_bot_send_is_recognised_by_metadata_and_text(): void
    {
        $this->fakeApis('Sure, we are open until 6pm.');
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        // Echo of an earlier chunk (different mid) — metadata marker, even with an unexpected app id.
        ProcessIncomingMetaMessage::dispatch($this->echo($this->account, [
            'messageId' => 'm_chunk_1', 'appId' => null,
            'raw' => ['message' => ['is_echo' => true, 'metadata' => 'chatbot:bot']],
        ]));
        // Echo without metadata (Instagram) but with text of our last reply.
        ProcessIncomingMetaMessage::dispatch($this->echo($this->account, [
            'messageId' => 'm_chunk_2', 'appId' => self::OUR_APP_ID, 'text' => 'Sure, we are open until 6pm.',
        ]));

        $this->assertSame(2, Message::count());
        $this->assertFalse(Conversation::sole()->isPaused());
    }

    public function test_echo_from_a_human_pauses_the_bot(): void
    {
        $this->fakeApis();
        BotSetting::create(['meta_account_id' => null, 'human_takeover_minutes' => 30]);

        ProcessIncomingMetaMessage::dispatch($this->echo($this->account));

        $message = Message::sole();
        $this->assertSame(SenderType::Human, $message->sender_type);
        $this->assertSame(MessageStatus::Sent, $message->status);

        $conversation = Conversation::sole();
        $this->assertTrue($conversation->isPaused());
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($conversation->bot_paused_until), 1);

        // Customer writes again: bot stays quiet.
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, ['messageId' => 'm_in_2']));
        $this->assertSame(0, $this->openAiCalls());
        $this->assertCount(0, $this->graphSends());
    }

    public function test_no_reply_when_bot_disabled_globally(): void
    {
        $this->fakeApis();
        BotSetting::create(['meta_account_id' => null, 'bot_enabled' => false]);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $this->assertSame(1, Message::count());
        Http::assertNothingSent();
    }

    public function test_no_reply_when_conversation_taken_over_or_disabled(): void
    {
        $this->fakeApis();
        Conversation::factory()->for($this->account)->create(['external_user_id' => '9001', 'human_takeover' => true]);
        Conversation::factory()->for($this->account)->create(['external_user_id' => '9002', 'bot_enabled' => false]);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));
        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, ['senderId' => '9002', 'messageId' => 'm_in_2']));

        $this->assertSame(2, Message::count());
        Http::assertNothingSent();
    }

    public function test_no_reply_outside_messaging_window(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, [
            'timestampMs' => now()->subHours(25)->getTimestampMs(),
        ]));

        $this->assertSame(1, Message::count());
        Http::assertNothingSent();
    }

    public function test_no_reply_without_openai_key(): void
    {
        $this->fakeApis();
        config(['openai.api_key' => null]);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        Http::assertNothingSent();
    }

    public function test_reply_is_dispatched_with_configured_delay(): void
    {
        Bus::fake([GenerateAndSendReply::class]);
        BotSetting::create(['meta_account_id' => $this->account->id, 'reply_delay_seconds' => 8]);

        (new ProcessIncomingMetaMessage($this->incoming($this->account)))->handle(
            app(ConversationService::class),
            app(BotSettingsResolver::class),
            app(ReplyPolicy::class),
        );

        Bus::assertDispatched(GenerateAndSendReply::class, fn ($job) => $job->delay === 8
            && $job->incomingMessageId === Message::sole()->id);
    }

    public function test_attachment_only_message_is_described_to_the_model(): void
    {
        $this->fakeApis('Nice photo!');

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account, [
            'text' => null,
            'attachments' => [['type' => 'image', 'url' => 'https://cdn.example/x.jpg', 'payload' => []]],
        ]));

        $openAi = Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.openai.com'))->first()[0];
        $this->assertSame('[customer sent an image]', $openAi['input'][0]['content']);
        $this->assertCount(1, $this->graphSends());
    }

    public function test_instagram_account_sends_without_messaging_type_or_sender_actions(): void
    {
        $account = MetaAccount::factory()->instagram()->create(['page_id' => '123456789']);
        $this->fakeApis('Hey!');

        ProcessIncomingMetaMessage::dispatch($this->incoming($account));

        $sends = $this->graphSends();
        $this->assertCount(1, $sends);
        $this->assertArrayNotHasKey('messaging_type', $sends[0]->data());
        $this->assertStringEndsWith('/v26.0/123456789/messages', $sends[0]->url());
        Http::assertNotSent(fn (Request $r) => isset($r->data()['sender_action']));
    }

    public function test_messenger_sends_sender_actions_first(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $actions = Http::recorded(fn (Request $r) => isset($r->data()['sender_action']))
            ->map(fn ($p) => $p[0]['sender_action'])->values()->all();
        $this->assertSame(['mark_seen', 'typing_on'], $actions);
    }

    public function test_long_reply_is_split_but_stored_once(): void
    {
        $sentence = str_repeat('word ', 90).'end. '; // ~455 chars
        $long = trim(str_repeat($sentence, 6));      // ~2730 chars > 2000
        $this->fakeApis($long);

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $sends = $this->graphSends();
        $this->assertCount(2, $sends);
        foreach ($sends as $send) {
            $this->assertLessThanOrEqual(2000, mb_strlen($send['message']['text']));
        }
        $reply = Message::where('direction', MessageDirection::Outgoing)->sole();
        $this->assertSame($long, $reply->body);
    }

    public function test_secrets_never_appear_in_urls_or_logs(): void
    {
        $this->fakeApis();

        ProcessIncomingMetaMessage::dispatch($this->incoming($this->account));

        $token = $this->account->access_token;
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'access_token') || str_contains($r->url(), $token));

        $send = $this->graphSends()[0];
        $this->assertSame($token, $send['access_token']); // POST token travels in the body
        $this->assertSame(hash_hmac('sha256', $token, 'app-secret-xyz'), $send['appsecret_proof']);

        $this->assertNotEmpty($this->logs);
        $dump = json_encode($this->logs);
        $this->assertStringNotContainsString($token, $dump);
        $this->assertStringNotContainsString(self::OPENAI_KEY, $dump);
        $this->assertStringNotContainsString('Hi, what are your prices?', $dump); // no prompt text in logs
    }
}
