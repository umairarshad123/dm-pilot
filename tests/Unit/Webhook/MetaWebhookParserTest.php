<?php

namespace Tests\Unit\Webhook;

use App\Data\IncomingMetaMessage;
use App\Enums\Platform;
use App\Services\Meta\MetaWebhookParser;
use Tests\TestCase;

class MetaWebhookParserTest extends TestCase
{
    private MetaWebhookParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MetaWebhookParser;
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/meta/{$name}.json")), true);
    }

    private function payload(string $object, array $events, string $entryId = 'PAGE_1'): array
    {
        return ['object' => $object, 'entry' => [['id' => $entryId, 'time' => 1, 'messaging' => $events]]];
    }

    public function test_messenger_text_message(): void
    {
        $messages = $this->parser->parse($this->fixture('messenger_text'), 42);

        $this->assertCount(1, $messages);
        $m = $messages[0];
        $this->assertInstanceOf(IncomingMetaMessage::class, $m);
        $this->assertSame(Platform::Facebook, $m->platform);
        $this->assertSame('111222333', $m->accountExternalId);
        $this->assertSame('USER_PSID_1', $m->senderId);
        $this->assertSame('111222333', $m->recipientId);
        $this->assertSame('m_messenger_text_1', $m->messageId);
        $this->assertSame('Hello, are you open today?', $m->text);
        $this->assertSame([], $m->attachments);
        $this->assertSame(1760000000123, $m->timestampMs);
        $this->assertFalse($m->isEcho);
        $this->assertNull($m->appId);
        $this->assertSame(42, $m->webhookEventId);
        $this->assertSame('m_messenger_text_1', $m->raw['message']['mid']);
        $this->assertSame('USER_PSID_1', $m->customerId());
    }

    public function test_instagram_text_message(): void
    {
        $messages = $this->parser->parse($this->fixture('instagram_text'));

        $this->assertCount(1, $messages);
        $this->assertSame(Platform::Instagram, $messages[0]->platform);
        $this->assertSame('17841400000000000', $messages[0]->accountExternalId);
        $this->assertSame('IGSID_CUSTOMER_1', $messages[0]->senderId);
        $this->assertSame('Price?', $messages[0]->text);
        $this->assertNull($messages[0]->webhookEventId);
    }

    public function test_attachments_are_normalized(): void
    {
        $messages = $this->parser->parse($this->payload('page', [[
            'sender' => ['id' => 'U'], 'recipient' => ['id' => 'PAGE_1'], 'timestamp' => 5,
            'message' => ['mid' => 'm_att', 'attachments' => [
                ['type' => 'image', 'payload' => ['url' => 'https://cdn.example.com/x.jpg', 'sticker_id' => 369239263222822]],
                ['type' => 'location', 'payload' => ['coordinates' => ['lat' => 1, 'long' => 2]]],
                'garbage',
            ]],
        ]]));

        $this->assertCount(1, $messages);
        $this->assertNull($messages[0]->text);
        $this->assertSame([
            ['type' => 'image', 'url' => 'https://cdn.example.com/x.jpg', 'payload' => ['url' => 'https://cdn.example.com/x.jpg', 'sticker_id' => 369239263222822]],
            ['type' => 'location', 'url' => null, 'payload' => ['coordinates' => ['lat' => 1, 'long' => 2]]],
        ], $messages[0]->attachments);
    }

    public function test_echo_with_app_id(): void
    {
        $messages = $this->parser->parse($this->payload('page', [[
            'sender' => ['id' => 'PAGE_1'], 'recipient' => ['id' => 'CUSTOMER'], 'timestamp' => 7,
            'message' => ['mid' => 'm_echo', 'text' => 'bot reply', 'is_echo' => true, 'app_id' => 1234567890],
        ]]));

        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->isEcho);
        $this->assertSame('1234567890', $messages[0]->appId);
        $this->assertSame('PAGE_1', $messages[0]->senderId);
        $this->assertSame('CUSTOMER', $messages[0]->customerId());
    }

    public function test_echo_without_app_id_is_human_reply(): void
    {
        $messages = $this->parser->parse($this->payload('instagram', [[
            'sender' => ['id' => 'IG_1'], 'recipient' => ['id' => 'CUSTOMER'], 'timestamp' => 7,
            'message' => ['mid' => 'm_echo_human', 'text' => 'hi from staff', 'is_echo' => true],
        ]], 'IG_1'));

        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->isEcho);
        $this->assertNull($messages[0]->appId);
    }

    public function test_deleted_unsupported_and_empty_messages_are_skipped(): void
    {
        $base = ['sender' => ['id' => 'U'], 'recipient' => ['id' => 'PAGE_1'], 'timestamp' => 1];

        $messages = $this->parser->parse($this->payload('page', [
            $base + ['message' => ['mid' => 'm_del', 'is_deleted' => true]],
            $base + ['message' => ['mid' => 'm_uns', 'is_unsupported' => true]],
            $base + ['message' => ['text' => 'no mid']],
            $base + ['message' => ['mid' => 'm_empty']],
            $base + ['message' => ['mid' => 'm_empty2', 'text' => '', 'attachments' => []]],
        ]));

        $this->assertSame([], $messages);
    }

    public function test_read_delivery_reaction_standby_and_changes_are_skipped(): void
    {
        $messages = $this->parser->parse($this->fixture('mixed_batch'));

        $this->assertSame(['m_1', 'm_2'], array_map(fn (IncomingMetaMessage $m) => $m->messageId, $messages));
        $this->assertSame('image', $messages[1]->attachments[0]['type']);
        $this->assertSame('https://cdn.example.com/a.jpg', $messages[1]->attachments[0]['url']);
    }

    public function test_postback_is_converted_to_message(): void
    {
        $messages = $this->parser->parse($this->payload('page', [
            ['sender' => ['id' => 'U'], 'recipient' => ['id' => 'PAGE_1'], 'timestamp' => 99,
                'postback' => ['title' => 'Get Started', 'payload' => 'GET_STARTED', 'mid' => 'm_pb']],
            ['sender' => ['id' => 'U'], 'recipient' => ['id' => 'PAGE_1'], 'timestamp' => 100,
                'postback' => ['title' => 'What are your hours?', 'payload' => 'HOURS']],
        ]));

        $this->assertCount(2, $messages);
        $this->assertSame('m_pb', $messages[0]->messageId);
        $this->assertSame('Get Started', $messages[0]->text);
        $this->assertSame([], $messages[0]->attachments);
        $this->assertSame('GET_STARTED', $messages[0]->raw['postback']['payload']);

        $this->assertSame('postback_'.sha1('U|100|HOURS'), $messages[1]->messageId);
        $this->assertSame('What are your hours?', $messages[1]->text);
        $this->assertFalse($messages[1]->isEcho);
    }

    public function test_garbage_shapes_never_throw(): void
    {
        $payloads = [
            [],
            ['object' => 'page'],
            ['object' => 'page', 'entry' => 'nope'],
            ['object' => 'page', 'entry' => [null, 5, 'x', ['id' => 'P']]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => 'x']]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [null, 1, 'str', [], ['message' => 'str']]]]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [['sender' => 'x', 'recipient' => null, 'message' => ['mid' => 'm', 'text' => 'hi']]]]]],
            ['object' => 'page', 'entry' => [['id' => ['nested'], 'messaging' => [['sender' => ['id' => 'U'], 'recipient' => ['id' => 'P'], 'message' => ['mid' => 'm', 'text' => 'hi']]]]]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [['sender' => ['id' => 'U'], 'recipient' => ['id' => 'P'], 'message' => ['mid' => ['x'], 'text' => ['y']]]]]]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [['sender' => ['id' => 'U'], 'recipient' => ['id' => 'P'], 'postback' => 'x']]]]],
            ['object' => 'page', 'entry' => [['id' => 'P', 'messaging' => [['sender' => ['id' => 'U'], 'recipient' => ['id' => 'P'], 'postback' => []]]]]],
            ['object' => 'user', 'entry' => [['id' => 'P', 'messaging' => [['sender' => ['id' => 'U'], 'recipient' => ['id' => 'P'], 'message' => ['mid' => 'm', 'text' => 'hi']]]]]],
            ['object' => ['page']],
        ];

        foreach ($payloads as $payload) {
            $this->assertSame([], $this->parser->parse($payload));
        }
    }

    public function test_missing_timestamp_defaults_to_now_and_numeric_ids_are_stringified(): void
    {
        $messages = $this->parser->parse(['object' => 'page', 'entry' => [['id' => 111, 'messaging' => [[
            'sender' => ['id' => 222], 'recipient' => ['id' => 111], 'message' => ['mid' => 'm_x', 'text' => 'hi'],
        ]]]]]);

        $this->assertCount(1, $messages);
        $this->assertSame('111', $messages[0]->accountExternalId);
        $this->assertSame('222', $messages[0]->senderId);
        $this->assertGreaterThan(1_700_000_000_000, $messages[0]->timestampMs);
    }
}
