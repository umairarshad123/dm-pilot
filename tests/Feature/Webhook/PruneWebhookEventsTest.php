<?php

namespace Tests\Feature\Webhook;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneWebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $status, int $daysOld): WebhookEvent
    {
        $event = WebhookEvent::create([
            'object' => 'page',
            'payload_hash' => hash('sha256', uniqid('', true)),
            'payload' => ['object' => 'page'],
            'status' => $status,
        ]);
        $event->forceFill(['created_at' => now()->subDays($daysOld)])->save();

        return $event;
    }

    public function test_prunes_old_events_but_keeps_recent_and_pending(): void
    {
        $old = $this->event('processed', 40);
        $oldFailed = $this->event('failed', 40);
        $oldPending = $this->event('pending', 40);
        $recent = $this->event('processed', 5);

        $this->artisan('meta:prune-webhook-events')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelMissing($oldFailed);
        $this->assertModelExists($oldPending);
        $this->assertModelExists($recent);
    }

    public function test_days_and_include_pending_options(): void
    {
        $pending = $this->event('pending', 10);
        $recent = $this->event('ignored', 2);

        $this->artisan('meta:prune-webhook-events', ['--days' => 7, '--include-pending' => true])->assertSuccessful();

        $this->assertModelMissing($pending);
        $this->assertModelExists($recent);
        $this->artisan('meta:prune-webhook-events', ['--days' => 0])->assertFailed();
    }
}
