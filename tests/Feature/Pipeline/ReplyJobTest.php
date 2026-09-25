<?php

namespace Tests\Feature\Pipeline;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SenderType;
use App\Jobs\GenerateAndSendReply;
use App\Models\BotSetting;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReplyJobTest extends TestCase
{
    use PipelineHelpers, RefreshDatabase;

    private Conversation $conversation;

    private Message $incoming;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPipeline();

        $this->conversation = Conversation::factory()->for(MetaAccount::factory())->create(['external_user_id' => '9001']);
        $this->incoming = Message::factory()->for($this->conversation)->create(['body' => 'Do you deliver?']);
    }

    private function runJob(int $attempts = 1, ?int $messageId = null): GenerateAndSendReply
    {
        $job = (new GenerateAndSendReply($messageId ?? $this->incoming->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempts;
        app()->call([$job, 'handle']);

        return $job;
    }

    private function reply(): ?Message
    {
        return Message::where('in_reply_to_id', $this->incoming->id)->first();
    }

    public function test_history_is_sent_with_roles(): void
    {
        Message::factory()->for($this->conversation)->create(['body' => 'older question', 'created_at' => now()->subMinute()]);
        $this->incoming->delete();
        Message::factory()->for($this->conversation)->fromBot()->create(['body' => 'older answer']);
        Message::factory()->for($this->conversation)->fromBot()->create(['body' => 'failed one', 'status' => MessageStatus::Failed]);
        $this->incoming = Message::factory()->for($this->conversation)->create(['body' => 'Do you deliver?']);
        $this->fakeApis('Yes!');

        $this->runJob();

        $request = Http::recorded(fn ($r) => str_contains($r->url(), 'openai'))->first()[0];
        $this->assertSame([
            ['role' => 'user', 'content' => 'older question'],
            ['role' => 'assistant', 'content' => 'older answer'],
            ['role' => 'user', 'content' => 'Do you deliver?'],
        ], $request['input']);
        $this->assertSame(MessageStatus::Sent, $this->reply()->status);
    }

    public function test_debounce_skips_when_newer_customer_message_exists(): void
    {
        Message::factory()->for($this->conversation)->create(['body' => 'also, price?']);
        $this->fakeApis();

        $job = $this->runJob();

        $job->assertNotReleased();
        $this->assertNull($this->reply());
        Http::assertNothingSent();
    }

    public function test_policy_is_rechecked_when_job_runs(): void
    {
        $this->conversation->pauseBotFor(60);
        $this->fakeApis();

        $this->runJob();

        $this->assertNull($this->reply());
        Http::assertNothingSent();
    }

    public function test_runs_twice_but_sends_once(): void
    {
        $this->fakeApis();

        $this->runJob();
        $this->runJob();

        $this->assertCount(1, $this->graphSends());
        $this->assertSame(1, $this->openAiCalls());
        $this->assertSame(1, Message::where('direction', MessageDirection::Outgoing)->count());
    }

    public function test_retry_reuses_pending_claim_and_generated_text(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id, 'direction' => MessageDirection::Outgoing,
            'sender_type' => SenderType::Bot, 'status' => MessageStatus::Pending,
            'in_reply_to_id' => $this->incoming->id, 'body' => 'Already generated text',
        ]);
        $this->fakeApis();

        $this->runJob(attempts: 2);

        $this->assertSame(0, $this->openAiCalls());
        $this->assertSame('Already generated text', $this->graphSends()[0]['message']['text']);
        $this->assertSame(MessageStatus::Sent, $this->reply()->status);
        $this->assertSame(1, Message::where('direction', MessageDirection::Outgoing)->count());
    }

    public function test_openai_retryable_error_releases_job_with_retry_after(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429, ['Retry-After' => '7'])]);

        $job = $this->runJob();

        $job->assertReleased(7);
        $this->assertSame(MessageStatus::Pending, $this->reply()->status);
        $this->assertCount(0, $this->graphSends());
    }

    public function test_openai_final_failure_sends_fallback(): void
    {
        BotSetting::create(['meta_account_id' => null, 'fallback_message' => 'Thanks! A team member will reply soon.']);
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Server error']], 500),
            'graph.facebook.com/*' => Http::response(['message_id' => 'm_fb_1']),
        ]);

        $job = $this->runJob(attempts: 4);

        $job->assertNotReleased();
        $reply = $this->reply();
        $this->assertSame(MessageStatus::Sent, $reply->status);
        $this->assertSame('Thanks! A team member will reply soon.', $reply->body);
        $this->assertStringContainsString('fallback', $reply->error);
        $this->assertSame('Thanks! A team member will reply soon.', $this->graphSends()[0]['message']['text']);
    }

    public function test_openai_non_retryable_failure_without_fallback_stays_silent(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Invalid model']], 400)]);

        $job = $this->runJob();

        $job->assertNotReleased();
        $this->assertSame(MessageStatus::Failed, $this->reply()->status);
        $this->assertCount(0, $this->graphSends());
    }

    public function test_meta_retryable_error_releases_with_usage_header_delay(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiBody('Yes we do.')),
            'graph.facebook.com/*' => Http::response(
                ['error' => ['message' => 'Calls to this api have exceeded the rate limit.', 'code' => 613, 'fbtrace_id' => 'Abc']],
                400,
                ['X-Business-Use-Case-Usage' => json_encode(['123' => [['type' => 'messenger', 'call_count' => 100, 'estimated_time_to_regain_access' => 2]]])],
            ),
        ]);

        $job = $this->runJob();

        $job->assertReleased(120);
        $reply = $this->reply();
        $this->assertSame(MessageStatus::Pending, $reply->status);
        $this->assertSame('Yes we do.', $reply->body); // reused on retry
    }

    public function test_meta_permanent_error_marks_failed_without_retry(): void
    {
        $token = $this->conversation->metaAccount->access_token;
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiBody('Yes we do.')),
            'graph.facebook.com/*' => Http::response(['error' => [
                'message' => "Error validating access token {$token} has expired", 'type' => 'OAuthException', 'code' => 190,
            ]], 400),
        ]);

        $job = $this->runJob();

        $job->assertNotReleased();
        $reply = $this->reply();
        $this->assertSame(MessageStatus::Failed, $reply->status);
        $this->assertStringContainsString('190', $reply->error);
        $this->assertStringNotContainsString($token, $reply->error);
        $this->assertStringNotContainsString($token, json_encode($this->logs));
    }

    public function test_failed_hook_marks_pending_claim_failed(): void
    {
        $claim = Message::create([
            'conversation_id' => $this->conversation->id, 'direction' => MessageDirection::Outgoing,
            'sender_type' => SenderType::Bot, 'status' => MessageStatus::Pending, 'in_reply_to_id' => $this->incoming->id,
        ]);

        (new GenerateAndSendReply($this->incoming->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(MessageStatus::Failed, $claim->fresh()->status);
    }

    public function test_openai_temperature_is_retried_without_when_rejected(): void
    {
        BotSetting::create(['meta_account_id' => null, 'temperature' => 0.7]);
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['error' => ['message' => "Unsupported parameter: 'temperature' is not supported with this model.", 'param' => 'temperature']], 400)
                ->push($this->openAiBody('Yes.')),
            'graph.facebook.com/*' => Http::response(['message_id' => 'm_1']),
        ]);

        $this->runJob();

        $calls = Http::recorded(fn ($r) => str_contains($r->url(), 'openai'))->map(fn ($p) => $p[0]->data())->values();
        $this->assertSame(0.7, $calls[0]['temperature']);
        $this->assertArrayNotHasKey('temperature', $calls[1]);
        $this->assertSame(MessageStatus::Sent, $this->reply()->status);
    }
}
