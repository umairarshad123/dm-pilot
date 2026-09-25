<?php

namespace Tests\Feature\Pipeline;

use App\Data\IncomingMetaMessage;
use App\Models\MetaAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

trait PipelineHelpers
{
    protected const OPENAI_KEY = 'sk-test-SECRETKEY1234567890';

    protected const OUR_APP_ID = '555000111';

    /** @var list<array{level: string, message: string, context: array}> */
    protected array $logs = [];

    protected function setUpPipeline(): void
    {
        config([
            'openai.api_key' => self::OPENAI_KEY,
            'openai.temperature' => null,
            'meta.app_id' => self::OUR_APP_ID,
            'meta.app_secret' => 'app-secret-xyz',
        ]);

        $this->logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->logs[] = ['level' => $e->level, 'message' => $e->message, 'context' => $e->context];
        });
    }

    protected function openAiBody(string $text): array
    {
        return [
            'id' => 'resp_1',
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'id' => 'rs_1', 'summary' => []],
                ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $text]]],
            ],
            'usage' => ['input_tokens' => 50, 'output_tokens' => 10, 'total_tokens' => 60],
        ];
    }

    protected function fakeApis(string $reply = 'Hello! How can I help?', ?array $graph = null): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiBody($reply)),
            'graph.facebook.com/*' => $graph ?? Http::response(['recipient_id' => '9001', 'message_id' => 'm_out_'.uniqid()]),
            'graph.instagram.com/*' => Http::response(['recipient_id' => '9001', 'message_id' => 'm_out_'.uniqid()]),
        ]);
    }

    protected function incoming(MetaAccount $account, array $overrides = []): IncomingMetaMessage
    {
        return new IncomingMetaMessage(...array_merge([
            'platform' => $account->platform,
            'accountExternalId' => $account->ownExternalId(),
            'senderId' => '9001',
            'recipientId' => $account->ownExternalId(),
            'messageId' => 'm_in_1',
            'text' => 'Hi, what are your prices?',
            'attachments' => [],
            'timestampMs' => now()->getTimestampMs(),
            'webhookEventId' => 1,
            'raw' => ['message' => ['mid' => 'm_in_1', 'text' => 'Hi']],
        ], $overrides));
    }

    protected function echo(MetaAccount $account, array $overrides = []): IncomingMetaMessage
    {
        return $this->incoming($account, array_merge([
            'senderId' => $account->ownExternalId(),
            'recipientId' => '9001',
            'messageId' => 'm_echo_1',
            'text' => 'Hi from the team',
            'isEcho' => true,
            'appId' => '263902037430900',
            'raw' => ['message' => ['mid' => 'm_echo_1', 'is_echo' => true]],
        ], $overrides));
    }

    /** @return list<Request> Graph requests that sent a text message */
    protected function graphSends(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'graph.') && isset($r->data()['message']))
            ->map(fn ($pair) => $pair[0])->values()->all();
    }

    protected function openAiCalls(): int
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.openai.com'))->count();
    }
}
