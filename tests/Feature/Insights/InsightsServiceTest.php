<?php

namespace Tests\Feature\Insights;

use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Services\Insights\InsightsService;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    private InsightsService $insights;

    protected function setUp(): void
    {
        parent::setUp();
        $this->insights = app(InsightsService::class);
        Carbon::setTestNow('2026-09-24 12:00:00'); // Thursday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time, callable $create): mixed
    {
        Carbon::setTestNow($time);

        try {
            return $create();
        } finally {
            Carbon::setTestNow('2026-09-24 12:00:00');
        }
    }

    private function incoming(Conversation $c, string $time, string $body = 'hello'): Message
    {
        return $this->at($time, fn () => Message::factory()->for($c)->create(['body' => $body]));
    }

    private function bot(Conversation $c, string $time, ?Message $replyTo, array $extra = []): Message
    {
        return $this->at($time, fn () => Message::factory()->for($c)->fromBot()->create($extra + [
            'in_reply_to_id' => $replyTo?->id, 'body' => 'reply', 'external_message_id' => null,
        ]));
    }

    /** @return array{0: MetaAccount, 1: MetaAccount} */
    private function seedData(): array
    {
        $fb = MetaAccount::factory()->create(['page_name' => 'FB Page']);
        $ig = MetaAccount::factory()->instagram()->create(['page_name' => 'IG Account']);

        // Previous period
        $c3 = $this->at('2026-09-01 08:00:00', fn () => Conversation::factory()->for($fb)->create());
        $m5 = $this->incoming($c3, '2026-09-01 08:00:00');
        $this->bot($c3, '2026-09-01 08:00:10', $m5);

        // Current period
        $c1 = $this->at('2026-09-22 10:00:00', fn () => Conversation::factory()->for($fb)->create());
        $m1 = $this->incoming($c1, '2026-09-22 10:00:00');
        $this->bot($c1, '2026-09-22 10:00:30', $m1);
        $m2 = $this->incoming($c1, '2026-09-23 15:00:00', 'price?');
        $this->bot($c1, '2026-09-23 15:01:30', $m2);
        $this->bot($c1, '2026-09-23 15:02:00', null, ['payload' => ['source' => 'automation']]);
        $this->bot($c1, '2026-09-23 15:03:00', null, ['status' => MessageStatus::Skipped, 'sent_at' => null]);

        $c2 = $this->at('2026-09-24 09:00:00', fn () => Conversation::factory()->for($ig)->create());
        $m3 = $this->incoming($c2, '2026-09-24 09:00:00', 'mail me at x@y.com');
        $this->bot($c2, '2026-09-24 09:00:10', $m3, ['status' => MessageStatus::Failed, 'sent_at' => null, 'error' => 'AI down']);
        $this->at('2026-09-24 09:05:00', fn () => Message::factory()->for($c2)->fromHuman()->create(['body' => 'Hi, Sara here']));
        $m4 = $this->incoming($c2, '2026-09-24 09:10:00', 'thanks');
        $this->bot($c2, '2026-09-24 09:12:30', $m4, ['error' => 'AI failed; fallback sent: timeout']);

        return [$fb, $ig];
    }

    public function test_empty_database(): void
    {
        $overview = $this->insights->overview();

        $this->assertSame(['from' => '2026-09-11', 'to' => '2026-09-24', 'days' => 14, 'previous_from' => '2026-08-28', 'previous_to' => '2026-09-10'], $overview['period']);
        $this->assertSame(['value' => 0, 'previous' => 0, 'change' => 0, 'change_pct' => null], $overview['metrics']['incoming_messages']);
        $this->assertNull($overview['metrics']['first_response_median_seconds']['value']);
        $this->assertNull($overview['metrics']['bot_resolution_rate']['value']);

        $series = $this->insights->dailySeries();
        $this->assertCount(14, $series);
        $this->assertSame(['date' => '2026-09-11', 'incoming' => 0, 'bot' => 0, 'human' => 0, 'failed' => 0], $series[0]);

        $this->assertSame([], $this->insights->byPage());
        $this->assertSame(0, $this->insights->byChannel()['instagram']['incoming']);
        $this->assertSame(0, $this->insights->hourlyHeatmap()['max']);
        $this->assertSame([], $this->insights->recentActivity());
        $this->assertSame(['overview', 'daily', 'by_page', 'by_channel', 'heatmap', 'recent'], array_keys($this->insights->dashboard()));
    }

    public function test_overview_numbers(): void
    {
        $this->seedData();

        $m = $this->insights->overview()['metrics'];
        $values = array_map(fn ($x) => $x['value'], $m);
        $previous = array_map(fn ($x) => $x['previous'], $m);

        $this->assertSame([
            'conversations_started' => 2,
            'active_conversations' => 2,
            'incoming_messages' => 4,
            'bot_replies' => 4,
            'ai_replies' => 2,
            'automation_replies' => 1,
            'fallback_sends' => 1,
            'human_replies' => 1,
            'ai_failures' => 1,
            'leads_captured' => 1,
            'first_response_median_seconds' => 90,
            'first_response_avg_seconds' => 90,
            'bot_resolution_rate' => 50.0,
        ], $values);

        $this->assertSame(1, $previous['conversations_started']);
        $this->assertSame(1, $previous['bot_replies']);
        $this->assertSame(10, $previous['first_response_median_seconds']);
        $this->assertSame(100.0, $previous['bot_resolution_rate']);
        $this->assertSame(['value' => 4, 'previous' => 1, 'change' => 3, 'change_pct' => 300.0], $m['incoming_messages']);
        $this->assertNull($m['leads_captured']['change_pct']); // previous 0
        $this->assertSame(-50.0, $m['bot_resolution_rate']['change']);
    }

    public function test_page_filter_and_custom_period(): void
    {
        [$fb, $ig] = $this->seedData();

        $m = $this->insights->overview($ig->id)['metrics'];
        $this->assertSame(2, $m['incoming_messages']['value']);
        $this->assertSame(0, $m['incoming_messages']['previous']);
        $this->assertSame(1, $m['fallback_sends']['value']);
        $this->assertSame(150, $m['first_response_median_seconds']['value']);

        $period = CarbonPeriod::create('2026-09-23', '2026-09-24');
        $overview = $this->insights->overview(null, $period);
        $this->assertSame(['from' => '2026-09-23', 'to' => '2026-09-24', 'days' => 2, 'previous_from' => '2026-09-21', 'previous_to' => '2026-09-22'], $overview['period']);
        $this->assertSame(3, $overview['metrics']['incoming_messages']['value']);
        $this->assertSame(1, $overview['metrics']['incoming_messages']['previous']);
    }

    public function test_daily_series(): void
    {
        $this->seedData();

        $series = collect($this->insights->dailySeries(null, 7))->keyBy('date');

        $this->assertCount(7, $series);
        $this->assertSame(['date' => '2026-09-22', 'incoming' => 1, 'bot' => 1, 'human' => 0, 'failed' => 0], $series['2026-09-22']);
        $this->assertSame(['date' => '2026-09-23', 'incoming' => 1, 'bot' => 2, 'human' => 0, 'failed' => 0], $series['2026-09-23']);
        $this->assertSame(['date' => '2026-09-24', 'incoming' => 2, 'bot' => 1, 'human' => 1, 'failed' => 1], $series['2026-09-24']);
        $this->assertSame('2026-09-18', $series->keys()->first());
    }

    public function test_by_page_and_channel(): void
    {
        [$fb, $ig] = $this->seedData();

        $pages = $this->insights->byPage();
        $this->assertSame([
            'meta_account_id' => $fb->id, 'name' => 'FB Page', 'platform' => 'facebook', 'active' => true,
            'conversations_started' => 1, 'active_conversations' => 1,
            'incoming' => 2, 'bot' => 3, 'human' => 0, 'failed' => 0, 'leads_captured' => 0,
        ], $pages[0]);
        $this->assertSame([
            'meta_account_id' => $ig->id, 'name' => 'IG Account', 'platform' => 'instagram', 'active' => true,
            'conversations_started' => 1, 'active_conversations' => 1,
            'incoming' => 2, 'bot' => 1, 'human' => 1, 'failed' => 1, 'leads_captured' => 1,
        ], $pages[1]);
        $this->assertCount(1, $this->insights->byPage($ig->id));

        $channels = $this->insights->byChannel();
        $this->assertSame(['conversations_started' => 1, 'active_conversations' => 1, 'incoming' => 2, 'bot' => 3, 'human' => 0, 'failed' => 0], $channels['facebook']);
        $this->assertSame(['conversations_started' => 1, 'active_conversations' => 1, 'incoming' => 2, 'bot' => 1, 'human' => 1, 'failed' => 1], $channels['instagram']);
    }

    public function test_hourly_heatmap(): void
    {
        $this->seedData();

        $heatmap = $this->insights->hourlyHeatmap();

        $this->assertSame('Mon', $heatmap['days'][0]);
        $this->assertCount(7, $heatmap['matrix']);
        $this->assertCount(24, $heatmap['matrix'][0]);
        $this->assertSame(1, $heatmap['matrix'][1][10]); // Tue 10:00
        $this->assertSame(1, $heatmap['matrix'][2][15]); // Wed 15:00
        $this->assertSame(2, $heatmap['matrix'][3][9]);  // Thu 09:00
        $this->assertSame(2, $heatmap['max']);
        $this->assertSame(4, $heatmap['total']);
    }

    public function test_recent_activity(): void
    {
        [$fb, $ig] = $this->seedData();
        Conversation::query()->where('meta_account_id', $ig->id)->update(['customer_name' => 'Sara Khan']);

        $recent = $this->insights->recentActivity(null, 3);

        $this->assertCount(3, $recent);
        $this->assertSame('reply', $recent[0]['excerpt']);
        $this->assertSame('Sara Khan', $recent[0]['contact_name']);
        $this->assertSame('SK', $recent[0]['contact_initials']);
        $this->assertSame('instagram', $recent[0]['platform']);
        $this->assertSame('IG Account', $recent[0]['page_name']);
        $this->assertSame(['outgoing', 'bot', 'sent'], [$recent[0]['direction'], $recent[0]['sender_type'], $recent[0]['status']]);
        $this->assertSame('thanks', $recent[1]['excerpt']);
        $this->assertSame(
            ['id', 'conversation_id', 'contact_name', 'contact_initials', 'profile_pic_url', 'platform', 'meta_account_id', 'page_name',
                'direction', 'sender_type', 'status', 'source', 'excerpt', 'has_attachments', 'at'],
            array_keys($recent[0]),
        );

        $this->assertCount(8, $this->insights->recentActivity($fb->id, 50));
    }
}
